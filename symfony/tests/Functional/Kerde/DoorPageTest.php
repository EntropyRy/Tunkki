<?php

declare(strict_types=1);

namespace App\Tests\Functional\Kerde;

use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;

/**
 * Functional tests for the Kerde Door page.
 *
 * NOTE: The door route is IP-restricted (security.yaml). Tests expecting 2xx responses
 * will get 403 unless the test IP matches DOOR_ALLOWED_IPS configuration.
 * In CI/test environments without configured allowed IPs, we verify:
 * - Unauthenticated access redirects to login
 * - Authenticated access gets 403 (expected without allowed IP)
 * - Bar code service integration (unit tested separately)
 */
final class DoorPageTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('en');
    }

    public function testDoorPageIpRestrictedForActiveMember(): void
    {
        // Login as an active member
        [$user, $client] = $this->loginAsActiveMember();
        $member = $user->getMember();
        $memberCode = $member->getCode();
        $this->assertNotEmpty($memberCode, 'Member should have a non-empty code');

        // Without allowed IP, door route returns 403 (IP restriction working)
        $client->setServerParameter('REMOTE_ADDR', '10.0.0.1');
        $crawler = $client->request('GET', '/en/kerde/door');

        $response = $client->getResponse();
        $this->assertSame(403, $response->getStatusCode(), 'Door page should be IP-restricted (403 when IP not allowed)');
    }

    public function testFinnishDoorRouteIsIpRestricted(): void
    {
        [$user, $client] = $this->loginAsActiveMember();

        // Finnish route should also be IP-restricted
        $client->setServerParameter('REMOTE_ADDR', '10.0.0.1');
        $crawler = $client->request('GET', '/kerde/ovi');

        $response = $client->getResponse();
        $this->assertSame(403, $response->getStatusCode(), 'Finnish door route should be IP-restricted');
    }

    public function testUnauthenticatedUserCannotAccessDoorPage(): void
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $this->client()->request('GET', '/en/kerde/door');

        // Should redirect to login
        $response = $this->client()->getResponse();
        $this->assertTrue($response->isRedirect(), 'Expected redirect for unauthenticated user');
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }
}
