<?php

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use App\Controllers\Api\v1\ConfigController;
use App\Core\Request;
use App\Core\Response;

/**
 * Property-Based Test for ConfigController
 *
 * Feature: business-setup-levels, Property 1: Invalid business level defaults to starter
 *
 * **Validates: Requirements 1.4**
 *
 * Property 1: For any string value of BUSINESS_LEVEL that is not one of
 * "starter", "medium", or "advanced", the ConfigController SHALL return
 * "starter" as the business_level in the response.
 *
 * Uses a manual property loop (100 iterations) generating arbitrary non-level
 * strings, as PHPUnit does not ship a built-in QuickCheck library.
 */
class ConfigControllerPropertyTest extends TestCase
{
    private ConfigController $controller;
    private Request $request;

    /** Valid levels that must NOT be generated as invalid inputs */
    private const VALID_LEVELS = ['starter', 'medium', 'advanced'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ConfigController();
        $this->request    = $this->createMock(Request::class);
    }

    protected function tearDown(): void
    {
        foreach (['BUSINESS_LEVEL', 'BUSINESS_NAME', 'INCLUDES_PHARMACY'] as $key) {
            unset($_ENV[$key]);
        }
        parent::tearDown();
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    private function callIndex(): array
    {
        $response = new Response();
        $result   = $this->controller->index($this->request, $response);
        return json_decode($result->getContent(), true);
    }

    /**
     * Generate a random string that is guaranteed NOT to be a valid level.
     * Combines several strategies to cover a wide input space:
     *   - random alphanumeric strings of varying length
     *   - numeric strings
     *   - strings with special characters
     *   - empty-ish strings (spaces, tabs)
     *   - mixed-case variants that are not exact valid levels
     */
    private function generateInvalidLevel(int $seed): string
    {
        // Deterministic but varied generation based on seed
        $strategies = [
            // Random alphanumeric strings
            fn() => substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, rand(1, 12)),
            // Numeric-only strings
            fn() => (string) rand(0, 99999),
            // Special characters
            fn() => str_repeat(chr(rand(33, 47)), rand(1, 5)),
            // Whitespace-heavy strings
            fn() => str_repeat(' ', rand(1, 5)),
            // Mixed case that is not a valid level
            fn() => strtoupper(substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, rand(4, 8))),
            // Concatenated valid-level fragments
            fn() => 'start' . rand(1, 9),
            fn() => 'med' . rand(1, 9),
            fn() => 'adv' . rand(1, 9),
            // Unicode-ish (high ASCII)
            fn() => chr(rand(128, 200)) . chr(rand(65, 90)),
            // Very long strings
            fn() => str_repeat('x', rand(20, 50)),
            // Null-byte and control characters
            fn() => chr(0),
            fn() => chr(rand(1, 31)),
        ];

        srand($seed);
        $strategy = $strategies[$seed % count($strategies)];
        $candidate = $strategy();

        // Ensure the generated string is not accidentally a valid level
        if (in_array(strtolower(trim($candidate)), self::VALID_LEVELS, true)) {
            // Append a suffix to make it invalid
            $candidate .= '_invalid_' . $seed;
        }

        return $candidate;
    }

    // ─── Property 1 ──────────────────────────────────────────────────────────

    /**
     * Property 1: Invalid business level defaults to starter
     *
     * Feature: business-setup-levels, Property 1: Invalid business level defaults to starter
     *
     * For any string that is not "starter", "medium", or "advanced",
     * ConfigController::index() MUST return business_level = "starter".
     *
     * Runs 100 iterations with varied invalid inputs.
     */
    public function testProperty1InvalidBusinessLevelDefaultsToStarter(): void
    {
        $iterations = 100;
        $failures   = [];

        for ($i = 0; $i < $iterations; $i++) {
            $invalidLevel = $this->generateInvalidLevel($i);

            // Set the env var to the invalid level
            $_ENV['BUSINESS_LEVEL'] = $invalidLevel;

            $data = $this->callIndex();

            $actual = $data['data']['business_level'] ?? null;

            if ($actual !== 'starter') {
                $failures[] = sprintf(
                    'Iteration %d: BUSINESS_LEVEL=%s → expected "starter", got "%s"',
                    $i,
                    json_encode($invalidLevel),
                    $actual
                );
            }
        }

        $this->assertEmpty(
            $failures,
            sprintf(
                "Property 1 failed on %d/%d iterations:\n%s",
                count($failures),
                $iterations,
                implode("\n", $failures)
            )
        );
    }

    /**
     * Additional coverage: explicitly test a curated set of known-invalid strings
     * to complement the random generation above.
     */
    public function testProperty1KnownInvalidLevels(): void
    {
        $knownInvalid = [
            'enterprise',
            'basic',
            'pro',
            'premium',
            'free',
            'STARTER',       // uppercase — controller lowercases, so this IS valid; skip
            'Starter',       // title-case — also valid after strtolower
            'MEDIUM',        // valid after strtolower
            'ADVANCED',      // valid after strtolower
            '0',
            '1',
            'null',
            'true',
            'false',
            'undefined',
            '   ',           // whitespace only
            'starter ',      // trailing space — trim makes it valid; skip
            ' medium',       // leading space — trim makes it valid; skip
            'starter_extra',
            'medium_plus',
            'advanced_v2',
            'level1',
            'level2',
            'level3',
            '',              // empty string
        ];

        // Filter out strings that are actually valid after the controller's
        // strtolower(trim(...)) normalisation
        $trulyInvalid = array_filter($knownInvalid, function (string $s): bool {
            return !in_array(strtolower(trim($s)), self::VALID_LEVELS, true);
        });

        foreach ($trulyInvalid as $invalidLevel) {
            $_ENV['BUSINESS_LEVEL'] = $invalidLevel;
            $data = $this->callIndex();

            $this->assertSame(
                'starter',
                $data['data']['business_level'],
                sprintf(
                    'Expected "starter" for BUSINESS_LEVEL=%s, got "%s"',
                    json_encode($invalidLevel),
                    $data['data']['business_level'] ?? 'null'
                )
            );
        }
    }
}
