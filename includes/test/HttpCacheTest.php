<?php

/**
 * Tests for the HTTP cache headers sent with pages, and for the ETag that lets an expired copy
 * of a page be revalidated rather than rebuilt.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

class HttpCacheTest extends PHPUnit\Framework\TestCase
{

	private ?string $method = null;

	protected function setUp(): void
	{
		$this->method = $_SERVER['REQUEST_METHOD'] ?? null;
	}

	protected function tearDown(): void
	{
		if ($this->method === null)
		{
			unset($_SERVER['REQUEST_METHOD']);
		}
		else
		{
			$_SERVER['REQUEST_METHOD'] = $this->method;
		}
	}

	public function testPagesArePublicWithBrowserAndSharedLifetimes(): void
	{
		$headers = HttpCache::headers_for('page', false);

		$this->assertStringStartsWith('public, ', $headers['Cache-Control']);
		$this->assertStringContainsString('max-age=' . HttpCache::ttl('BROWSER', HttpCache::BROWSER_TTL),
			$headers['Cache-Control']);
		$this->assertStringContainsString('s-maxage=' . HttpCache::ttl('SHARED', HttpCache::SHARED_TTL),
			$headers['Cache-Control']);
		$this->assertStringContainsString('stale-if-error=', $headers['Cache-Control']);
	}

	public function testLongCdnLifetimeOnlyWhenACacheCanBePurged(): void
	{
		$this->assertArrayNotHasKey('CDN-Cache-Control', HttpCache::headers_for('page', false));

		$headers = HttpCache::headers_for('page', true);
		$this->assertArrayHasKey('CDN-Cache-Control', $headers);
		$this->assertStringStartsWith('max-age=' . HttpCache::ttl('CDN', HttpCache::CDN_TTL),
			$headers['CDN-Cache-Control']);
	}

	public function testPrivateAndNotFoundNeverGetTheCdnLifetime(): void
	{
		$this->assertSame(['Cache-Control' => 'private, no-store'],
			HttpCache::headers_for('private', true));

		$headers = HttpCache::headers_for('notfound', true);
		$this->assertArrayNotHasKey('CDN-Cache-Control', $headers);
		$this->assertStringContainsString('s-maxage=' . HttpCache::NOT_FOUND_SHARED_TTL,
			$headers['Cache-Control']);
	}

	public function testUnknownPolicyIsRejected(): void
	{
		$this->expectException(InvalidArgumentException::class);
		HttpCache::headers_for('forever', false);
	}

	public function testEtagMatching(): void
	{
		$etag = 'W/"abc123"';

		$this->assertTrue(HttpCache::matches($etag, 'W/"abc123"'));
		$this->assertTrue(HttpCache::matches($etag, '"abc123"'), 'Comparison is weak.');
		$this->assertTrue(HttpCache::matches($etag, '"other", W/"abc123"'));
		$this->assertTrue(HttpCache::matches($etag, '*'));
		$this->assertTrue(HttpCache::matches($etag, 'W/"abc123-gzip"'),
			'The suffix that mod_deflate adds is ignored.');
		$this->assertTrue(HttpCache::matches($etag, 'W/"abc123-br"'));

		$this->assertFalse(HttpCache::matches($etag, 'W/"abc124"'));
		$this->assertFalse(HttpCache::matches($etag, ''));
		$this->assertFalse(HttpCache::matches(false, 'W/"abc123"'));
	}

	public function testEtagChangesWhenAnEditionIsImported(): void
	{
		$db = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);

		$statement = $db->prepare('SELECT id, last_import FROM editions ORDER BY id LIMIT 1');
		$statement->execute();
		$edition = $statement->fetch(PDO::FETCH_OBJ);
		if ($edition === false)
		{
			$this->markTestSkipped('No editions in the test database.');
		}

		$before = HttpCache::etag($db);
		$this->assertMatchesRegularExpression('/^W\/"[0-9a-f]{32}"$/', $before);
		$this->assertSame($before, HttpCache::etag($db), 'The ETag is stable.');

		$update = $db->prepare('UPDATE editions SET last_import = :when WHERE id = :id');
		try
		{
			$update->execute([':when' => '2001-02-03 04:05:06', ':id' => $edition->id]);
			$this->assertNotSame($before, HttpCache::etag($db));
		}
		finally
		{
			$update->execute([':when' => $edition->last_import, ':id' => $edition->id]);
		}
	}

	public function testRouterReportsTheMatchingRouteName(): void
	{
		$router = new Router;
		$router->addRoute('default', '^(?P<route>.*)$', ['PermalinkController', 'handle']);
		$router->addRoute('admin', '^/admin(/.*)?$', 'admin/index.php');

		$this->assertSame('admin', $router->getRoute('/admin/')[2]);
		$this->assertSame('default', $router->getRoute('/1-1/')[2]);
	}

	public function testPolicyByRouteAndMethod(): void
	{
		$controller = new MasterController(['router' => new Router]);

		$_SERVER['REQUEST_METHOD'] = 'GET';
		$this->assertSame('page', $controller->cachePolicy('default'));
		$this->assertSame('page', $controller->cachePolicy('search'));
		$this->assertSame('private', $controller->cachePolicy('admin'));
		$this->assertSame('private', $controller->cachePolicy('api-activate'));
		$this->assertSame('private', $controller->cachePolicy('record-view'));

		$_SERVER['REQUEST_METHOD'] = 'HEAD';
		$this->assertSame('page', $controller->cachePolicy('default'));

		$_SERVER['REQUEST_METHOD'] = 'POST';
		$this->assertSame('private', $controller->cachePolicy('downloads'));
	}

	public function testNothingToPurgeByDefault(): void
	{
		if (defined('VARNISH_HOST') || defined('CLOUDFLARE_ZONE_ID'))
		{
			$this->markTestSkipped('A purgeable cache is configured.');
		}

		$this->assertFalse(CachePurger::configured());
		$this->assertSame([], (new CachePurger)->purge());
	}

}
