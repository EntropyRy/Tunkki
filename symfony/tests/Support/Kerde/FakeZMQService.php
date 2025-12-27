<?php

declare(strict_types=1);

namespace App\Tests\Support\Kerde;

use App\Service\ZMQService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Fake ZMQ Service for testing.
 *
 * Extends ZMQService to satisfy type hints but overrides methods
 * to return configurable responses without real ZMQ connections.
 */
final class FakeZMQService extends ZMQService
{
    private string $initResponse = 'connected';
    private string $openResponse = 'door opened';

    public function __construct()
    {
        // Pass a minimal parameter bag to parent
        parent::__construct(new ParameterBag([
            'door_socket' => 'tcp://localhost:5555',
        ]));
    }

    public function setInitResponse(string $response): void
    {
        $this->initResponse = $response;
    }

    public function setOpenResponse(string $response): void
    {
        $this->openResponse = $response;
    }

    #[\Override]
    public function send(string $command): string
    {
        return 'ok';
    }

    #[\Override]
    public function sendInit(?string $username, int $timestamp): string
    {
        return $this->initResponse;
    }

    #[\Override]
    public function sendOpen(?string $username, int $timestamp): string
    {
        return $this->openResponse;
    }
}
