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
        $this->assertStringContainsString('chat.entropy.fi', $location);
        $this->assertStringContainsString('oauth/authorize', $location);
    }
}
