<?php

/**
 * Tests for the CLI import action's edition handling.
 *
 * Guards against the regression where `import --current` set an
 * edition_args key that ParserController::handle_editions() ignored,
 * so an existing edition was never promoted to current.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

require_once __DIR__ . '/../../task/class.ImportAction.inc.php';

class ImportActionTest extends PHPUnit\Framework\TestCase
{
	private ImportAction $action;

	protected function setUp(): void
	{
		$this->action = new ImportAction(array('options' => array()));
	}

	/*
	 * Build a ParserController stub whose get_current_edition() returns
	 * the given value, without running the real constructor.
	 */
	private function stubParser($current_edition)
	{
		$parser = $this->createStub(ParserController::class);
		$parser->method('get_current_edition')->willReturn($current_edition);
		return $parser;
	}

	public function testDefaultsToExistingCurrentEdition(): void
	{
		$parser = $this->stubParser((object) array('id' => 42, 'name' => 'Test Edition'));

		$edition_args = $this->action->buildEditionArgs($parser);

		$this->assertSame('existing', $edition_args['edition_option']);
		$this->assertSame(42, $edition_args['edition']);
		$this->assertArrayNotHasKey('make_current', $edition_args,
			'Without --current, an existing edition must not be promoted.');
	}

	public function testCurrentOptionSetsMakeCurrent(): void
	{
		$this->action->options['current'] = true;
		$parser = $this->stubParser((object) array('id' => 42, 'name' => 'Test Edition'));

		$edition_args = $this->action->buildEditionArgs($parser);

		$this->assertSame('existing', $edition_args['edition_option']);
		$this->assertArrayHasKey('make_current', $edition_args,
			'--current must set the make_current key, which is the key '
			. 'that ParserController::handle_editions() honors.');
		$this->assertEquals(1, $edition_args['make_current']);
	}

	public function testFirstEditionIsCreatedCurrent(): void
	{
		$parser = $this->stubParser(false);

		$edition_args = $this->action->buildEditionArgs($parser);

		$this->assertSame('new', $edition_args['edition_option']);
		$this->assertSame('default', $edition_args['new_edition_slug']);
		$this->assertEquals(1, $edition_args['make_current'],
			'When no edition exists yet, the newly created one must be current.');
	}

	public function testEditionOptionFindsEditionBySlug(): void
	{
		$pdo = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);
		$edition = $pdo->query('SELECT id, slug FROM editions LIMIT 1')->fetch();
		if ($edition === false) {
			$this->markTestSkipped('editions table is empty — run the import before testing editions.');
		}

		$this->action->options['edition'] = $edition->slug;
		$this->action->options['current'] = true;
		$parser = $this->stubParser(false);

		$edition_args = $this->action->buildEditionArgs($parser);

		$this->assertSame('existing', $edition_args['edition_option']);
		$this->assertEquals($edition->id, $edition_args['edition']);
		$this->assertEquals(1, $edition_args['make_current']);
	}
}
