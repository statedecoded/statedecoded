<?php

/**
 * Smoke tests — verify the running site returns sane responses for key pages
 * and API endpoints after a real import.
 *
 * Skipped automatically when SMOKE_BASE_URL is not set, so the suite stays
 * green in the unit-test-only CI job that has no imported data.
 */

use PHPUnit\Framework\TestCase;

class SmokeTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        $base = getenv('SMOKE_BASE_URL');
        if ($base === false || $base === '') {
            $this->markTestSkipped('SMOKE_BASE_URL not set — skipping smoke tests.');
        }
        $this->base = rtrim($base, '/');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function get(string $path): array
    {
        $url = $this->base . $path;
        $ch = curl_init($url);
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
        $ch = curl_init($url);
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
        return [$status, $data, $body];
    }

    private function assertHtml(int $status, string $body, string $mustContain, string $path): void
    {
        $this->assertSame(200, $status, "Expected 200 for $path");
        $this->assertStringNotContainsStringIgnoringCase(
            'Fatal error', $body, "Fatal error on $path"
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'Warning:', $body, "PHP warning on $path"
        );
        $this->assertStringContainsString(
            $mustContain, $body, "Expected '$mustContain' in $path"
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

    public function testLawPage(): void
    {
        // Fetch the first available law URL from the API instead of hard-coding
        // a section number, so the test works with any imported dataset.
        [$apiStatus, $data] = $this->getJson('/api/1.0/law/?order=section&num=1&key=');
        if ($apiStatus !== 200 || empty($data[0]['url'])) {
            $this->markTestSkipped('Could not find a law via the API to test the law page.');
        }
        $url = $data[0]['url'];
        [$status, $body] = $this->get($url);
        $this->assertHtml($status, $body, 'catch_line', $url);
        $this->assertStringContainsString('<section', $body, "Law text section missing from $url");
    }

    public function testStructurePage(): void
    {
        [$status, $body] = $this->get('/browse/');
        if ($status !== 200) {
            $this->markTestSkipped('Browse page unavailable.');
        }
        // Pick the first structure link from the browse page
        if (!preg_match('#href="(/[^/"]+/)"#', $body, $m)) {
            $this->markTestSkipped('No structure links found on browse page.');
        }
        [$status2, $body2] = $this->get($m[1]);
        $this->assertHtml($status2, $body2, '</a>', $m[1]);
    }

    public function test404Page(): void
    {
        [$status, $body] = $this->get('/this-page-does-not-exist-' . uniqid() . '/');
        $this->assertSame(404, $status, 'Expected 404 for non-existent page');
    }

    public function testDownloadsPage(): void
    {
        [$status, $body] = $this->get('/downloads/');
        $this->assertHtml($status, $body, 'Downloads', '/downloads/');
    }

    public function testJsonDownload(): void
    {
        [$status, $body] = $this->get('/downloads/current/code-json.zip');
        $this->assertSame(200, $status, 'Expected 200 for code-json.zip');
        $this->assertGreaterThan(1000, strlen($body), 'code-json.zip seems too small');
    }

    // -------------------------------------------------------------------------
    // API tests
    // -------------------------------------------------------------------------

    public function testApiLaw(): void
    {
        [$status, $data, $raw] = $this->getJson('/api/1.0/law/?num=1&key=');
        $this->assertSame(200, $status, 'API /law/ did not return 200');
        $this->assertNotNull($data, "API /law/ returned invalid JSON: $raw");
    }

    public function testApiDictionaryKnownTerm(): void
    {
        [$status, $data, $raw] = $this->getJson('/api/dictionary/court/?edition_id=1&key=');
        $this->assertSame(200, $status, 'Dictionary API did not return 200');
        $this->assertNotNull($data, "Dictionary API returned invalid JSON: $raw");
        $this->assertArrayHasKey('definition', $data, 'Dictionary API response missing definition');
        $this->assertNotEmpty($data['definition'], 'Dictionary definition is empty');
    }

    public function testApiDictionaryUnknownTerm(): void
    {
        [$status, $data] = $this->getJson('/api/dictionary/xyzzy_no_such_term/?edition_id=1&key=');
        $this->assertSame(200, $status, 'Dictionary API for unknown term did not return 200');
        $this->assertArrayHasKey('definition', $data);
        $this->assertSame('Definition not available.', $data['definition']);
    }

    public function testSearchPage(): void
    {
        [$status, $body] = $this->get('/search/?q=law&edition_id=1');
        $this->assertSame(200, $status, 'Search page did not return 200');
        $this->assertStringNotContainsStringIgnoringCase('Fatal error', $body);
    }

    public function testNoPhpWarningsOnLawPage(): void
    {
        [$apiStatus, $data] = $this->getJson('/api/1.0/law/?order=section&num=1&key=');
        if ($apiStatus !== 200 || empty($data[0]['url'])) {
            $this->markTestSkipped('Could not find a law via the API.');
        }
        [$status, $body] = $this->get($data[0]['url']);
        $this->assertSame(200, $status);
        $this->assertStringNotContainsStringIgnoringCase('Deprecated:', $body);
        $this->assertStringNotContainsStringIgnoringCase('Warning:', $body);
        $this->assertStringNotContainsStringIgnoringCase('Fatal error', $body);
    }
}
