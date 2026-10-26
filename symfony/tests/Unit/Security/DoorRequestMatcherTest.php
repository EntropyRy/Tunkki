<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\DoorRequestMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

final class DoorRequestMatcherTest extends TestCase
{
    private function matcher(string $allowed, ?\Closure $resolver = null): DoorRequestMatcher
    {
        return new DoorRequestMatcher(
            $allowed,
            new ArrayAdapter(),
            $resolver ?? static fn (string $host): array => 'e-werk.example.test' === $host ? ['203.0.113.7'] : [],
        );
    }

    private function request(string $path, string $ip): Request
    {
        return Request::create($path, 'GET', server: ['REMOTE_ADDR' => $ip]);
    }

    public static function doorPaths(): array
    {
        return [['/kerde/ovi'], ['/en/kerde/door'], ['/en/kerde/door/']];
    }

    #[DataProvider('doorPaths')]
    public function testMatchesClientFromResolvedHostname(string $path): void
    {
        $this->assertTrue($this->matcher('e-werk.example.test')->matches($this->request($path, '203.0.113.7')));
    }

    public function testRejectsClientNotInResolvedHostname(): void
    {
        $this->assertFalse($this->matcher('e-werk.example.test')->matches($this->request('/kerde/ovi', '198.51.100.1')));
    }

    public function testMatchesLiteralIpAndCidrEntries(): void
    {
        $matcher = $this->matcher('127.0.0.1, 10.1.0.0/16');

        $this->assertTrue($matcher->matches($this->request('/kerde/ovi', '127.0.0.1')));
        $this->assertTrue($matcher->matches($this->request('/kerde/ovi', '10.1.2.3')));
        $this->assertFalse($matcher->matches($this->request('/kerde/ovi', '10.2.0.1')));
    }

    public function testDoesNotMatchOtherPaths(): void
    {
        $matcher = $this->matcher('203.0.113.7');

        $this->assertFalse($matcher->matches($this->request('/kerde', '203.0.113.7')));
        $this->assertFalse($matcher->matches($this->request('/kerde/ovi/extra', '203.0.113.7')));
    }

    public function testEmptyConfigurationAndFailedResolutionDenyAll(): void
    {
        $this->assertFalse($this->matcher('')->matches($this->request('/kerde/ovi', '127.0.0.1')));
        $this->assertFalse($this->matcher('unknown.example.test')->matches($this->request('/kerde/ovi', '127.0.0.1')));
    }

    public function testResolutionIsCached(): void
    {
        $calls = 0;
        $matcher = $this->matcher('e-werk.example.test', static function () use (&$calls): array {
            ++$calls;

            return ['203.0.113.7'];
        });

        $matcher->matches($this->request('/kerde/ovi', '203.0.113.7'));
        $matcher->matches($this->request('/kerde/ovi', '203.0.113.8'));

        $this->assertSame(1, $calls);
    }
}
