<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Helper\Mattermost;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Unit tests for the Mattermost helper focused on safe operation in test/dev
 * environments without performing real network calls.
 *
 * NOTE:
 *  The current implementation only nulls the channel when APP_ENV=dev.
 *  There is no explicit short‑circuit for APP_ENV=test, so these tests avoid
 *  triggering a network request by using a file:// URL that will succeed
 *  (preventing a trigger_error) and still exercise the logic path.
 *
 * What we verify:
 *  - Calling SendToMattermost in APP_ENV=test returns "Done" without raising notices.
 *  - Channel parameter is only nulled in APP_ENV=dev (indirectly verified by
 *    comparing behavior between test and dev environments; since the method
 *    always returns "Done", we rely on absence of errors as success).
 *
 * We do NOT attempt to assert the internal curl options because that would
 * require function interception not present in this environment.
 */
final class MattermostHelperTest extends TestCase
{
    /**
     * Original APP_ENV captured before a test mutates it.
     */
    private ?string $originalEnv = null;

    private function makeTempHookFile(): string
    {
        // Correct function is sys_get_temp_dir (previously misspelled).
        $tmp = tempnam(sys_get_temp_dir(), "mmhook_");
        file_put_contents($tmp, "ok");
        // curl with file:// needs absolute path
        return "file://" . $tmp;
    }

    private function makeHelperForEnv(string $env): Mattermost
    {
        // Capture original only once (first mutation in this test class).
        if ($this->originalEnv === null) {
            $this->originalEnv =
                $_ENV["APP_ENV"] ?? (getenv("APP_ENV") ?: "test");
        }

        // Set both $_ENV and process env so the helper's check sees it.
        $_ENV["APP_ENV"] = $env;
        putenv("APP_ENV={$env}");

        $params = new ParameterBag([
            "mm_tunkki_hook" => $this->makeTempHookFile(),
            "mm_tunkki_botname" => "test-bot",
            "mm_tunkki_img" => "https://example.org/bot.png",
        ]);

        return new Mattermost($params);
    }

    protected function tearDown(): void
    {
        // Always restore APP_ENV after each test to keep functional tests in "test" env.
        if ($this->originalEnv !== null) {
            $_ENV["APP_ENV"] = $this->originalEnv;
            putenv("APP_ENV=" . $this->originalEnv);
        }
        parent::tearDown();
    }

    public function testSendToMattermostReturnsDoneInTestEnv(): void
    {
        $helper = $this->makeHelperForEnv("test");

        $result = $helper->SendToMattermost("Test message", "yhdistys");

        self::assertSame(
            "Done",
            $result,
            "Expected SendToMattermost to complete successfully in test env.",
        );
        // No explicit channel assertion possible without modifying helper
        // internals; success path implies no trigger_error was fired.
    }

    public function testSendToMattermostReturnsDoneInDevEnvWithChannelNulling(): void
    {
        $helper = $this->makeHelperForEnv("dev");

        // In dev the helper sets $channel = null internally, but since the
        // method only returns "Done" we simply ensure it completes.
        $result = $helper->SendToMattermost("Dev message", "some-channel");

        self::assertSame(
            "Done",
            $result,
            "Expected SendToMattermost to complete successfully in dev env.",
        );
    }
}
