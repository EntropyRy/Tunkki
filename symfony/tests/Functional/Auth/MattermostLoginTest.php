<?php

declare(strict_types=1);

namespace App\Tests\Functional\Auth;

use App\Tests\_Base\FixturesWebTestCase;

final class MattermostLoginTest extends FixturesWebTestCase
{
    public function testUserCanInitiateMattermostLogin(): void
    {
        $this->client()->request('GET', '/oauth');

        $this->assertResponseRedirects();

        $location = $this->client()->getResponse()->headers->get('Location');
        $this->assertNotFalse($location);
        $parts = parse_url($location);
        $this->assertNotFalse($parts);
        $this->assertSame('chat.entropy.fi', $parts['host'] ?? null);
        $this->assertMatchesRegularExpression('#/oauth/authorize#', $parts['path'] ?? '');
    }
}
