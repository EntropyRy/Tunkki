<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\_Base\FixturesWebTestCase;

final class ResetPasswordControllerTest extends FixturesWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testCheckEmailPageLoadsWithTryAgainLink(): void
    {
        $this->client->request('GET', '/reset-password/check-email');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('a[href="/reset-password"]');
    }

    public function testResetTokenRedirectsAndInvalidTokenReturnsToRequest(): void
    {
        $this->client->request('GET', '/reset-password/reset/fake-token');

        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame(
            '/reset-password/reset',
            parse_url($location, \PHP_URL_PATH),
        );

        $this->client->request('GET', '/reset-password/reset');
        $response = $this->client->getResponse();
        $this->assertSame(302, $response->getStatusCode());
        $location = $response->headers->get('Location');
        $this->assertNotNull($location);
        $this->assertSame(
            '/reset-password',
            parse_url($location, \PHP_URL_PATH),
        );
    }
}
