<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Matches door page requests coming from the Kerde wifi network.
 *
 * The network's public IP is dynamic, so DOOR_ALLOWED_HOSTS accepts DDNS
 * hostnames (resolved via DNS, cached briefly) as well as literal IPs/CIDRs.
 */
final readonly class DoorRequestMatcher implements RequestMatcherInterface
{
    private const string PATH_PATTERN = '#^/(en/kerde/door|kerde/ovi)/?$#';
    private const int CACHE_TTL = 60;

    /** @var \Closure(string): list<string> */
    private \Closure $resolver;

    /**
     * @param (\Closure(string): list<string>)|null $resolver hostname => IPs; defaults to DNS A/AAAA lookup
     */
    public function __construct(
        #[Autowire(env: 'DOOR_ALLOWED_HOSTS')]
        private string $allowedHosts,
        private CacheInterface $cache,
        ?\Closure $resolver = null,
    ) {
        $this->resolver = $resolver ?? $this->resolveDns(...);
    }

    public function matches(Request $request): bool
    {
        if (1 !== preg_match(self::PATH_PATTERN, $request->getPathInfo())) {
            return false;
        }

        $clientIp = $request->getClientIp();
        if (null === $clientIp) {
            return false;
        }

        $allowed = $this->allowedIps();

        return [] !== $allowed && IpUtils::checkIp($clientIp, $allowed);
    }

    /**
     * @return list<string>
     */
    private function allowedIps(): array
    {
        return $this->cache->get(
            'door_allowed_ips_'.hash('xxh3', $this->allowedHosts),
            function (ItemInterface $item): array {
                $item->expiresAfter(self::CACHE_TTL);

                $ips = [];
                foreach (explode(',', $this->allowedHosts) as $entry) {
                    $entry = trim($entry);
                    if ('' === $entry) {
                        continue;
                    }
                    if (false !== filter_var(explode('/', $entry, 2)[0], \FILTER_VALIDATE_IP)) {
                        $ips[] = $entry;
                        continue;
                    }
                    array_push($ips, ...($this->resolver)($entry));
                }

                return $ips;
            },
        );
    }

    /**
     * @return list<string>
     */
    private function resolveDns(string $host): array
    {
        $records = @dns_get_record($host, \DNS_A | \DNS_AAAA);
        if (false === $records) {
            return [];
        }

        $ips = [];
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if (\is_string($ip)) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }
}
