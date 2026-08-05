<?php

/**
 * Smoke tests — verify the running site returns sane responses for key pages
 * and API endpoints after a real import.
 *
 * Requires the environment variable SMOKE_BASE_URL to be set (e.g.
 * http://localhost when running inside Docker, or http://localhost:8080 from
 * the host).  Tests are skipped automatically when it is not set.
 *
 * SMOKE_API_KEY should be set to a verified API key from the live database.
 * docker-test.sh injects it automatically; in CI it can be injected similarly.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
	private string $base;
	private string $key;

	protected function setUp(): void
	{
		$base = getenv('SMOKE_BASE_URL');
		if ($base === false || $base === '') {
			$this->markTestSkipped('SMOKE_BASE_URL not set — skipping smoke tests.');
		}
		$this->base = rtrim($base, '/');
		$this->key  = getenv('SMOKE_API_KEY') ?: '';
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get(string $path): array
	{
		$url = $this->base . $path;
		$ch  = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_HTTPHEADER     => ['Accept: text/html'],
		]);
		$body   = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return [$status, (string) $body];
	}

	private function getJson(string $path): array
	{
		$url = $this->base . $path;
		$ch  = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT        => 10,
			CURLOPT_HTTPHEADER     => ['Accept: application/json'],
		]);
		$body   = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$data = json_decode((string) $body, true);
		return [$status, $data, (string) $body];
	}

	private function apiPath(string $path): string
	{
		$sep = str_contains($path, '?') ? '&' : '?';
		return $path . $sep . 'key=' . urlencode($this->key);
	}

	private function assertHtml(int $status, string $body, string $mustContain, string $path): void
	{
		$this->assertSame(200, $status, "Expected HTTP 200 for $path");
		$this->assertStringNotContainsStringIgnoringCase(
			'Fatal error', $body, "PHP fatal error found on $path"
		);
		$this->assertStringNotContainsStringIgnoringCase(
			'Warning:', $body, "PHP warning found on $path"
		);
		$this->assertStringContainsString(
			$mustContain, $body, "Expected '$mustContain' in response body for $path"
		);
	}

	// -------------------------------------------------------------------------
	// Front-end page tests
	// -------------------------------------------------------------------------

	public function testHomePage(): void
	{
		[$status, $body] = $this->get('/');
		$this->assertHtml($status, $body, 'State Decoded', '/');
	}

	public function testBrowsePage(): void
	{
		[$status, $body] = $this->get('/browse/');
		$this->assertHtml($status, $body, 'browse', '/browse/');
	}

	public function testKnownLawPage(): void
	{
		[$status, $body] = $this->get('/1-1/');
		$this->assertHtml($status, $body, 'Contents and designation of Code', '/1-1/');
	}

	public function testKnownLawPageHasText(): void
	{
		[$status, $body] = $this->get('/1-1/');
		$this->assertSame(200, $status);
		$this->assertStringContainsString('<section', $body,
			'Law page for § 1-1 must contain a <section> element with text.');
	}

	public function testStructurePage(): void
	{
		[$status, $body] = $this->get('/browse/');
		if ($status !== 200) {
			$this->markTestSkipped('Browse page unavailable.');
		}
		if (!preg_match('#href="(/[^/"]+/)"#', $body, $m)) {
			$this->markTestSkipped('No structure links found on browse page.');
		}
		[$status2, $body2] = $this->get($m[1]);
		$this->assertHtml($status2, $body2, '</a>', $m[1]);
	}

	public function test404Page(): void
	{
		[$status] = $this->get('/this-page-does-not-exist-' . uniqid() . '/');
		$this->assertSame(404, $status, 'Expected HTTP 404 for a non-existent page.');
	}

	public function testDownloadsPage(): void
	{
		[$status, $body] = $this->get('/downloads/');
		$this->assertHtml($status, $body, 'Downloads', '/downloads/');
	}

	public function testNoPhpWarningsOnLawPage(): void
	{
		[$status, $body] = $this->get('/1-1/');
		$this->assertSame(200, $status);
		$this->assertStringNotContainsStringIgnoringCase('Deprecated:', $body);
		$this->assertStringNotContainsStringIgnoringCase('Warning:', $body);
		$this->assertStringNotContainsStringIgnoringCase('Fatal error', $body);
	}

	// -------------------------------------------------------------------------
	// API tests
	// -------------------------------------------------------------------------

	public function testApiSpecificLaw(): void
	{
		if ($this->key === '') {
			$this->markTestSkipped('SMOKE_API_KEY not set — skipping API tests.');
		}
		[$status, $data, $raw] = $this->getJson($this->apiPath('/api/1.0/law/1-1/'));
		$this->assertSame(200, $status, 'Law API did not return 200.');
		$this->assertNotNull($data, "Law API returned invalid JSON: $raw");
		$this->assertArrayHasKey('catch_line', (array) $data,
			'Law API response must contain catch_line.');
	}

	public function testApiLawCatchLine(): void
	{
		if ($this->key === '') {
			$this->markTestSkipped('SMOKE_API_KEY not set — skipping API tests.');
		}
		[$status, $data] = $this->getJson($this->apiPath('/api/1.0/law/1-1/'));
		$this->assertSame(200, $status);
		$this->assertSame('Contents and designation of Code', $data['catch_line'] ?? null);
	}

	public function testApiDictionaryKnownTerm(): void
	{
		if ($this->key === '') {
			$this->markTestSkipped('SMOKE_API_KEY not set — skipping API tests.');
		}
		[$status, $data, $raw] = $this->getJson($this->apiPath('/api/dictionary/adult/'));
		$this->assertSame(200, $status, 'Dictionary API did not return 200.');
		$this->assertNotNull($data, "Dictionary API returned invalid JSON: $raw");
		$first = is_array($data) ? ($data[0] ?? $data) : $data;
		$this->assertArrayHasKey('definition', (array) $first,
			'Dictionary response missing definition field.');
		$this->assertNotEmpty($first['definition'] ?? '', 'Dictionary definition is empty.');
	}

	public function testApiDictionaryUnknownTerm(): void
	{
		if ($this->key === '') {
			$this->markTestSkipped('SMOKE_API_KEY not set — skipping API tests.');
		}
		[$status, $data] = $this->getJson($this->apiPath('/api/dictionary/xyzzy_no_such_term/'));
		$this->assertSame(200, $status);
		$this->assertSame('Definition not available.', $data['definition'] ?? null);
	}

	public function testSearchPage(): void
	{
		[$status, $body] = $this->get('/search/?q=criminal&edition_id=1');
		$this->assertSame(200, $status, 'Search page did not return 200.');
		$this->assertStringNotContainsStringIgnoringCase('Fatal error', $body);

		/*
		 * A 200 is not enough: a broken engine renders a 200 page with an
		 * error message where the results belong. Demand actual results.
		 */
		$this->assertStringNotContainsString('Search failed', $body,
			'The search page reported a search engine error.');
		$this->assertMatchesRegularExpression('/[1-9][\d,]* results found/', $body,
			'A search for "criminal" must display a nonzero result count.');
	}

	public function testApiSearch(): void
	{
		[$status, $data] = $this->getJson($this->apiPath('/api/1.0/search/water/'));
		$this->assertSame(200, $status, 'API search did not return 200.');
		$this->assertIsArray($data, 'API search must return valid JSON.');
		$this->assertArrayHasKey('total_records', $data,
			'API search response must include total_records.');
		$this->assertGreaterThan(0, $data['total_records'],
			'API search for "water" must match records in the sample data.');
		$this->assertNotEmpty($data['results'], 'API search must return results.');
	}

	public function testApiSuggest(): void
	{
		[$status, $data] = $this->getJson($this->apiPath('/api/1.0/suggest/wat/'));
		$this->assertSame(200, $status, 'API suggest did not return 200.');
		$this->assertIsArray($data, 'API suggest must return valid JSON.');
		$this->assertArrayHasKey('terms', $data,
			'API suggest response must include terms.');
		$this->assertNotEmpty($data['terms'],
			'API suggest for "wat" must offer suggestions (e.g. "Water...").');
	}
}
