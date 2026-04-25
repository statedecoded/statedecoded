<?php

/**
 * API integration tests.
 *
 * These tests require a fully populated database produced by the parser/importer.
 * They are skipped in the Docker dev environment until import data is available.
 */

require_once __DIR__ . '/helper/class.TestDbHelper.inc.php';

class APITest extends PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        $this->markTestSkipped(
            'APITest requires a full parser/importer run with XML import data. ' .
            'See includes/test/README.md and DOCKER.md for setup instructions.');
    }

    protected function tearDown(): void
    {
        // Nothing to tear down when setUp skips.
    }

    public function testRegisterKey(): void
    {
        // Covered by setUp skip.
    }
}
