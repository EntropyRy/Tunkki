<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MattermostNotifierService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Notifier\Bridge\Mattermost\MattermostOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

/**
 * @covers \App\Service\MattermostNotifierService
 */
final class MattermostNotifierServiceTest extends TestCase
{
    private function makeService(
        string $env,
        ChatterInterface $chatter,
    ): MattermostNotifierService {
        $params = $this->createStub(ParameterBagInterface::class);
        $params
            ->method('get')
            ->willReturnCallback(static function (string $name) use ($env) {
                if ('kernel.environment' === $name) {
                    return $env;
                }
                if ('mattermost_channels' === $name) {
                    return [
                        'yhdistys' => 'us4g1ifbn7df7b3ds373y94uow',
                        'vuokraus' => '9fmbdfkutpdnfnn8oqupqmw3yy',
                        'nakkikone' => 'ixph3m1jxfny7rmy78yff3en5h',
                        'kerde' => 'kb7j8tb5b3bsfg8tym9jd9ro9r',
                    ];
                }

                return null;
            });

        return new MattermostNotifierService($chatter, $params);
    }

    public function testSetsRecipientWhenChannelProvidedOutsideDev(): void
    {
        $captured = null;

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter
            ->expects(self::once())
            ->method('send')
            ->with(
                self::callback(static function ($message) use (&$captured) {
                    self::assertInstanceOf(ChatMessage::class, $message);
                    $captured = $message;

                    return true;
                }),
            )
            // Throw to avoid dealing with a concrete SentMessage return; service swallows exceptions.
            ->willThrowException(
                new \RuntimeException('transport failure (expected by test)'),
            );

        $service = $this->makeService('test', $chatter);
        $service->sendToMattermost('Hello world', 'yhdistys');

        self::assertInstanceOf(ChatMessage::class, $captured);
        $options = $captured->getOptions();
        self::assertInstanceOf(MattermostOptions::class, $options);

        // MattermostOptions should reflect the chosen channel in its array form.
        // We don't assert on a particular key name; instead, ensure the provided channel is present somewhere.
        self::assertSame(
            'us4g1ifbn7df7b3ds373y94uow',
            $options->getRecipientId(),
            'Recipient should be set when channel is provided outside dev.',
        );
    }

    public function testDoesNotOverrideRecipientInDevEnvironment(): void
    {
        $captured = null;

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter
            ->expects(self::once())
            ->method('send')
            ->with(
                self::callback(static function ($message) use (&$captured) {
                    self::assertInstanceOf(ChatMessage::class, $message);
                    $captured = $message;

                    return true;
                }),
            )
            ->willThrowException(
                new \RuntimeException('transport failure (expected by test)'),
            );

        $service = $this->makeService('dev', $chatter);
        $service->sendToMattermost('Hello dev', '#devops');

        self::assertInstanceOf(ChatMessage::class, $captured);
        $options = $captured->getOptions();
        self::assertInstanceOf(MattermostOptions::class, $options);

        self::assertNull(
            $options->getRecipientId(),
            'Recipient must not be overridden in dev.',
        );
    }

    public function testSwallowsExceptionsFromTransport(): void
    {
        $chatter = $this->createMock(ChatterInterface::class);
        $chatter
            ->expects(self::once())
            ->method('send')
            ->willThrowException(
                new \RuntimeException('simulated send failure'),
            );

        $service = $this->makeService('test', $chatter);

        // Should not throw despite the transport failure.
        $service->sendToMattermost('Will not throw');
        self::assertTrue(
            true,
            'No exception bubbled up from sendToMattermost().',
        );
    }

    public function testNoChannelLeavesOptionsUnspecified(): void
    {
        $captured = null;

        $chatter = $this->createMock(ChatterInterface::class);
        $chatter
            ->expects(self::once())
            ->method('send')
            ->with(
                self::callback(static function ($message) use (&$captured) {
                    self::assertInstanceOf(ChatMessage::class, $message);
                    $captured = $message;

                    return true;
                }),
            )
            ->willThrowException(
                new \RuntimeException('transport failure (expected by test)'),
            );

        $service = $this->makeService('prod', $chatter);
        $service->sendToMattermost('No channel specified', null);

        self::assertInstanceOf(ChatMessage::class, $captured);
        $options = $captured->getOptions();
        self::assertInstanceOf(MattermostOptions::class, $options);

        self::assertNull(
            $options->getRecipientId(),
            'Recipient should not be set when no channel is provided.',
        );
    }

    private function arrayContainsChannel(array $arr, string $channel): bool
    {
        $needle = ltrim($channel, '#');
        $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($arr));
        foreach ($it as $value) {
            if (\is_string($value) && ltrim($value, '#') === $needle) {
                return true;
            }
        }

        return false;
    }
}
