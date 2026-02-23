<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig\Components\Stream;

use App\Service\SSHService;
use App\Service\SSHServiceInterface;
use App\Twig\Components\Stream\Control;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ControlComponentTest extends TestCase
{
    public function testMountQueriesSshService(): void
    {
        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('has')->willReturn(false);
        $ssh = new SSHService($bag);

        $component = new Control($ssh);
        $component->mount();

        self::assertFalse($component->getStreamStatus());
    }

    public function testMountDoesNotThrowWhenSshStatusCheckFails(): void
    {
        $ssh = $this->createStub(SSHServiceInterface::class);
        $ssh
            ->method('checkStatus')
            ->willThrowException(new \RuntimeException('connection timeout'));

        $component = new Control($ssh);
        $component->mount();

        self::assertFalse($component->getStreamStatus());
    }
}
