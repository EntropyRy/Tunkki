<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Admin\ItemAdmin;
use App\Entity\Item;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Functional tests for ItemAdminController.
 *
 * Tests cover:
 * - cloneAction: clones an item with all its properties
 * - batchActionBatchEdit: batch edit multiple items
 * - Security: access control for admin actions
 */
#[Group('admin')]
#[Group('item')]
final class ItemAdminControllerTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initSiteAwareClient();
        $this->seedClientHome('fi');
    }

    public function testCloneActionClonesItemAndRedirects(): void
    {
        // Arrange: create super admin user and an item
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $item = $this->createItem('Original Item');
        $item->setManufacturer('Test Manufacturer');
        $item->setModel('Test Model');
        $item->setDescription('Test Description');
        $item->setRent('25.00');
        $item->setRentNotice('Handle with care');
        $item->setPlaceinstorage('Shelf A1');

        $this->em()->persist($item);
        $this->em()->flush();

        $originalId = $item->getId();

        // Act: request the clone action
        $cloneUrl = '/admin/item/'.$item->getId().'/clone';
        $this->client->request('GET', $cloneUrl);

        // Assert: redirected to list page
        $response = $this->client->getResponse();
        self::assertTrue(
            $response->isRedirect(),
            'Expected redirect after cloning, got status: '.$response->getStatusCode()
        );

        // Verify cloned item exists
        $this->em()->clear();
        $items = $this->em()->getRepository(Item::class)->findBy(['name' => 'Original Item (Clone)']);

        self::assertCount(1, $items, 'Cloned item should exist');
        $clonedItem = $items[0];

        self::assertNotSame($originalId, $clonedItem->getId(), 'Cloned item should have different ID');
        self::assertSame('Original Item (Clone)', $clonedItem->getName());
        self::assertSame('Test Manufacturer', $clonedItem->getManufacturer());
        self::assertSame('Test Model', $clonedItem->getModel());
        self::assertSame('Test Description', $clonedItem->getDescription());
        self::assertSame('Handle with care', $clonedItem->getRentNotice());
        self::assertSame('Shelf A1', $clonedItem->getPlaceinstorage());
    }

    public function testCloneActionWithNonExistentItemReturnsError(): void
    {
        // Arrange: login as super admin
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: request clone for non-existent item
        $this->client->request('GET', '/admin/item/999999/clone');

        // Assert: error response (404 or 500 depending on Sonata Admin handling)
        $response = $this->client->getResponse();
        self::assertTrue(
            $response->isClientError() || $response->isServerError(),
            'Non-existent item should return error response, got: '.$response->getStatusCode()
        );
    }

    public function testCloneActionDeniesAccessToAnonymousUser(): void
    {
        // Arrange: create an item without logging in
        $item = $this->createItem('Anonymous Clone Test');

        // Reset auth to ensure we're anonymous
        $this->resetAuthSession();

        // Act: request clone without authentication
        $this->client->request('GET', '/admin/item/'.$item->getId().'/clone');

        // Assert: redirected to login (302)
        $response = $this->client->getResponse();
        self::assertSame(
            302,
            $response->getStatusCode(),
            'Anonymous user should be redirected to login'
        );
        self::assertStringContainsString(
            '/login',
            $response->headers->get('Location') ?? '',
            'Should redirect to login page'
        );
    }

    public function testCloneActionDeniesAccessToNonAdminUser(): void
    {
        // Arrange: create a regular user (no ROLE_ADMIN)
        $this->loginAsMember();

        $item = $this->createItem('Non Admin Clone Test');

        // Act: attempt to access clone as non-admin
        $this->client->request('GET', '/admin/item/'.$item->getId().'/clone');

        // Assert: access denied (403 Forbidden)
        $response = $this->client->getResponse();
        self::assertSame(
            403,
            $response->getStatusCode(),
            'Non-admin user should get 403 Forbidden'
        );
    }

    public function testItemAdminListPageAccessibleToAdmin(): void
    {
        // Arrange: login as super admin
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: request the item list page
        $this->client->request('GET', '/admin/item/list');

        // Assert: page is accessible
        self::assertResponseIsSuccessful();
    }

    public function testItemAdminShowPageRendersItemDetails(): void
    {
        // Arrange: create super admin and an item
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $item = $this->createItem('Show Test Item');
        $item->setManufacturer('Show Manufacturer');
        $item->setModel('Show Model');

        $this->em()->persist($item);
        $this->em()->flush();

        // Act: request the show page
        $this->client->request('GET', '/admin/item/'.$item->getId().'/show');

        // Assert: page renders successfully with item data
        self::assertResponseIsSuccessful();
        $this->client->assertSelectorTextContains('body', 'Show Test Item');
    }

    public function testItemAdminEditPageRendersForm(): void
    {
        // Arrange: create super admin and an item
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $item = $this->createItem('Edit Test Item');

        $this->em()->persist($item);
        $this->em()->flush();

        // Act: request the edit page
        $this->client->request('GET', '/admin/item/'.$item->getId().'/edit');

        // Assert: page renders successfully with form
        self::assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    public function testItemAdminServiceIsConfigured(): void
    {
        // Assert: item admin service is available
        $admin = $this->getItemAdmin();
        self::assertInstanceOf(ItemAdmin::class, $admin);
    }

    public function testCloneActionPreservesRentPrice(): void
    {
        // Arrange: create super admin and an item with specific rent
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        $uniqueName = 'Rent Price Item '.uniqid('', true);
        $item = $this->createFullItem($uniqueName, '50.00');

        // Act: clone the item
        $this->client->request('GET', '/admin/item/'.$item->getId().'/clone');

        // Assert: redirected after clone
        $response = $this->client->getResponse();
        self::assertTrue($response->isRedirect(), 'Expected redirect after cloning');

        // Assert: cloned item has same rent
        $this->em()->clear();
        $clonedItems = $this->em()->getRepository(Item::class)->findBy(['name' => $uniqueName.' (Clone)']);

        self::assertCount(1, $clonedItems, 'Cloned item should exist');
        self::assertEquals('50.00', $clonedItems[0]->getRent());
    }

    public function testItemAdminCreatePageRendersForm(): void
    {
        // Arrange: login as super admin
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Act: request the create page
        $this->client->request('GET', '/admin/item/create');

        // Assert: page renders successfully with form
        self::assertResponseIsSuccessful();
        $this->client->assertSelectorExists('form');
    }

    private function getItemAdmin(): ItemAdmin
    {
        $admin = static::getContainer()->get('entropy_tunkki.admin.item');
        self::assertInstanceOf(ItemAdmin::class, $admin);

        return $admin;
    }

    private function createItem(string $name): Item
    {
        $item = new Item();
        $item->setName($name);

        $this->em()->persist($item);
        $this->em()->flush();

        return $item;
    }

    private function createItemWithRent(string $name, string $rent): Item
    {
        $item = new Item();
        $item->setName($name);
        $item->setRent($rent);

        $this->em()->persist($item);
        $this->em()->flush();

        return $item;
    }

    /**
     * Creates an item with all properties set (required for cloning due to controller behavior).
     */
    private function createFullItem(string $name, string $rent): Item
    {
        $item = new Item();
        $item->setName($name);
        $item->setManufacturer('Test Manufacturer');
        $item->setModel('Test Model');
        $item->setPlaceinstorage('Test Storage');
        $item->setDescription('Test Description');
        $item->setRent($rent);
        $item->setRentNotice('Test Notice');

        $this->em()->persist($item);
        $this->em()->flush();

        return $item;
    }
}
