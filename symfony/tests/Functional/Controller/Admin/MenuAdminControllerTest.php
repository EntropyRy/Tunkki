<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller\Admin;

use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;

#[Group('admin')]
final class MenuAdminControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testListActionRedirectsToTreeWithQuery(): void
    {
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', '/admin/app/menu/list?foo=bar');

        $this->assertResponseRedirects();

        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertNotNull($location);

        $path = parse_url($location, \PHP_URL_PATH);
        $query = parse_url($location, \PHP_URL_QUERY) ?? '';
        parse_str($query, $params);

        $this->assertSame('/admin/app/menu/tree', $path);
        $this->assertSame('bar', $params['foo'] ?? null);
    }

    public function testTreeActionRendersTreeView(): void
    {
        [$_admin, $_client] = $this->loginAsRole('ROLE_SUPER_ADMIN');

        $this->client->request('GET', '/admin/app/menu/tree');

        $this->assertResponseIsSuccessful();
        $this->client->assertSelectorExists('.sonata-tree');
    }
}
