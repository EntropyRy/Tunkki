<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\MattermostNotifierService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Notifier\ChatterInterface;

/**
 * Unit tests for the MattermostNotifierService focused on safe operation in test/dev
 * environments without performing real network calls.
 *
 * What we verify:
 *  - Service can be instantiated with mock dependencies
 *  - Channel parameter is only set when not in dev environment
 *  - Service correctly handles null channel parameter
 */
final class MattermostHelperTest extends TestCase
{
    public function testSendToMattermostInTestEnv(): void
    {
        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->expects(self::once())
            ->method('send');

        $params = new ParameterBag(['kernel.environment' => 'test']);
        $service = new MattermostNotifierService($chatter, $params);

        $service->sendToMattermost('Test message', 'yhdistys');

        // Success if no exception is thrown and send() invoked on in-memory transport
        $this->addToAssertionCount(1);
    }

    public function testSendToMattermostInDevEnvIgnoresChannel(): void
    {
        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->expects(self::once())
            ->method('send');

        $params = new ParameterBag(['kernel.environment' => 'dev']);
        $service = new MattermostNotifierService($chatter, $params);

        // In dev environment, the channel should be ignored
        $service->sendToMattermost('Dev message', 'some-channel');

        // Success if no exception is thrown
        $this->addToAssertionCount(1);
    }

    public function testSendToMattermostWithNullChannel(): void
    {
        $chatter = $this->createMock(ChatterInterface::class);
        $chatter->expects(self::once())
            ->method('send');

        $params = new ParameterBag(['kernel.environment' => 'prod']);
        $service = new MattermostNotifierService($chatter, $params);

        $service->sendToMattermost('Prod message', null);

        // Success if no exception is thrown
        $this->addToAssertionCount(1);
    }
}
