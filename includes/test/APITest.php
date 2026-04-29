<?php

/**
 * API integration tests — exercises the API class's key registration / activation /
 * validation lifecycle against the real test database.
 *
 * No HTTP server is required; all assertions are at the DB layer.
 *
 * PHP version 8
 *
 * @license   http://www.gnu.org/licenses/gpl.html GPL 3
 */

class APITest extends PHPUnit\Framework\TestCase
{
	private PDO $pdo;
	private API $api;

	private const TEST_EMAIL = 'api-test@example.invalid';

	protected function setUp(): void
	{
		$this->pdo = new PDO(PDO_DSN, PDO_USERNAME, PDO_PASSWORD,
			[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ]);

		// Ensure global $db is available for the API class.
		global $db;
		$db = new Database(PDO_DSN, PDO_USERNAME, PDO_PASSWORD);

		// Start each test with a clean slate for the test email.
		$this->pdo->prepare('DELETE FROM api_keys WHERE email = :e')
			->execute([':e' => self::TEST_EMAIL]);

		$this->api = new API();
		$this->api->form = new stdClass();
		$this->api->form->email = self::TEST_EMAIL;
		$this->api->form->name  = 'API Test Runner';
		$this->api->form->url   = 'https://example.invalid/';
		$this->api->suppress_activation_email = true;
	}

	protected function tearDown(): void
	{
		$this->pdo->prepare('DELETE FROM api_keys WHERE email = :e')
			->execute([':e' => self::TEST_EMAIL]);
	}


	// -------------------------------------------------------------------------
	// generate_key / generate_secret
	// -------------------------------------------------------------------------

	public function testGenerateKeyLength(): void
	{
		$api = new API();
		$api->generate_key();
		$this->assertSame(16, strlen($api->key), 'Generated key must be exactly 16 characters.');
	}

	public function testGenerateKeyIsAlphanumeric(): void
	{
		$api = new API();
		$api->generate_key();
		$this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{16}$/', $api->key,
			'Generated key must contain only alphanumeric characters.');
	}

	public function testGenerateKeyIsUnique(): void
	{
		$a = new API();
		$b = new API();
		$a->generate_key();
		$b->generate_key();
		$this->assertNotSame($a->key, $b->key, 'Two consecutively generated keys should differ.');
	}

	public function testGenerateSecretProducesValue(): void
	{
		$api = new API();
		$api->generate_secret();
		$this->assertNotEmpty($api->secret, 'generate_secret() must produce a non-empty secret.');
	}


	// -------------------------------------------------------------------------
	// register_key
	// -------------------------------------------------------------------------

	public function testRegisterKeyInsertsRow(): void
	{
		$this->api->register_key();

		$stmt = $this->pdo->prepare('SELECT * FROM api_keys WHERE email = :e');
		$stmt->execute([':e' => self::TEST_EMAIL]);
		$row = $stmt->fetch();

		$this->assertNotFalse($row, 'register_key() must insert a row into api_keys.');
		$this->assertSame(self::TEST_EMAIL, $row->email);
		$this->assertSame(16, strlen($row->api_key), 'Stored key must be 16 characters.');
	}

	public function testRegisterKeyDefaultsToUnverified(): void
	{
		$this->api->register_key();

		$stmt = $this->pdo->prepare('SELECT verified FROM api_keys WHERE email = :e');
		$stmt->execute([':e' => self::TEST_EMAIL]);
		$this->assertSame('n', $stmt->fetchColumn(),
			'A newly registered key must default to unverified.');
	}


	// -------------------------------------------------------------------------
	// activate_key
	// -------------------------------------------------------------------------

	public function testActivateKeyMarksVerified(): void
	{
		$this->api->register_key();
		$this->api->activate_key();

		$stmt = $this->pdo->prepare('SELECT verified FROM api_keys WHERE email = :e');
		$stmt->execute([':e' => self::TEST_EMAIL]);
		$this->assertSame('y', $stmt->fetchColumn(),
			'activate_key() must set verified = "y".');
	}

	public function testActivateKeyPopulatesKeyProperty(): void
	{
		$this->api->register_key();
		$this->api->activate_key();

		$this->assertNotEmpty($this->api->key,
			'activate_key() must populate $api->key with the registered key string.');
		$this->assertSame(16, strlen($this->api->key));
	}


	// -------------------------------------------------------------------------
	// validate_key
	// -------------------------------------------------------------------------

	public function testValidateKeySucceedsAfterActivation(): void
	{
		$this->api->register_key();
		$this->api->activate_key();

		$validator = new API();
		$validator->key = $this->api->key;
		$this->assertTrue($validator->validate_key(),
			'A freshly activated key must pass validation.');
	}

	public function testValidateKeyThrowsWhenKeyNotSet(): void
	{
		$this->expectException(Exception::class);
		(new API())->validate_key();
	}

	public function testValidateKeyThrowsForWrongLength(): void
	{
		$this->expectException(Exception::class);
		$api = new API();
		$api->key = 'short';
		$api->validate_key();
	}

	public function testValidateKeyThrowsForUnregisteredKey(): void
	{
		// Ensure at least one verified key exists so the "no keys at all" path
		// is not taken; we want the "key not in list" path.
		$this->api->register_key();
		$this->api->activate_key();

		$this->expectException(Exception::class);
		$validator = new API();
		$validator->key = 'aaaaaaaaaaaaaaaa'; // valid length, not in DB
		$validator->validate_key();
	}

	public function testValidateKeyThrowsForUnactivatedKey(): void
	{
		$this->api->register_key();
		// Deliberately skip activate_key() — the key is in the DB but not verified.

		$this->expectException(Exception::class);
		$validator = new API();
		$validator->key = $this->api->key;
		$validator->validate_key();
	}


	// -------------------------------------------------------------------------
	// validate_form
	// -------------------------------------------------------------------------

	public function testValidateFormPassesWithValidData(): void
	{
		$this->assertTrue($this->api->validate_form(),
			'validate_form() must return true for a valid email address.');
	}

	public function testValidateFormFailsWithEmptyEmail(): void
	{
		$this->api->form->email = '';
		$this->assertFalse($this->api->validate_form(),
			'validate_form() must return false when the email field is empty.');
	}

	public function testValidateFormFailsWithInvalidEmail(): void
	{
		$this->api->form->email = 'not-an-email';
		$this->assertFalse($this->api->validate_form(),
			'validate_form() must return false for an invalid email address.');
	}
}
