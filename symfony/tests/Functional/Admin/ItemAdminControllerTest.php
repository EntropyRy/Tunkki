<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Admin\Rental\Inventory\ItemAdmin;
use App\Entity\Rental\Inventory\Item;
use App\Factory\Rental\Inventory\ItemFactory;
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

        $item = ItemFactory::new()
            ->cloneable()
            ->withName('Original Item')
            ->withManufacturer('Test Manufacturer')
            ->withModel('Test Model')
            ->withDescription('Test Description')
            ->withRent('25.00')
            ->withRentNotice('Handle with care')
            ->withPlaceInStorage('Shelf A1')
            ->create();

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
        $item = ItemFactory::createOne();

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

        $item = ItemFactory::createOne();

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

        $item = ItemFactory::new()
            ->withName('Show Test Item')
            ->withManufacturer('Show Manufacturer')
            ->withModel('Show Model')
            ->create();

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

        $item = ItemFactory::createOne(['name' => 'Edit Test Item']);

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
        $item = ItemFactory::new()
            ->cloneable()
            ->withName($uniqueName)
            ->withRent('50.00')
            ->create();

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

    public function testBatchActionBatchEditIsRelevantReturnsTrueForAllEntitiesSelected(): void
    {
        // Arrange
        $controller = $this->getItemAdminController();

        // Act
        $result = $controller->batchActionBatchEditIsRelevant([], true);

        // Assert
        self::assertTrue($result);
    }

    public function testBatchActionBatchEditIsRelevantReturnsTrueForTwoOrMoreSelected(): void
    {
        // Arrange
        $controller = $this->getItemAdminController();

        // Act
        $result = $controller->batchActionBatchEditIsRelevant([1, 2], false);

        // Assert
        self::assertTrue($result);
    }

    public function testBatchActionBatchEditIsRelevantReturnsErrorForLessThanTwoSelected(): void
    {
        // Arrange
        $controller = $this->getItemAdminController();

        // Act
        $resultEmpty = $controller->batchActionBatchEditIsRelevant([], false);
        $resultOne = $controller->batchActionBatchEditIsRelevant([1], false);

        // Assert
        self::assertSame('not enough selected', $resultEmpty);
        self::assertSame('not enough selected', $resultOne);
    }

    public function testBatchActionBatchEditCopiesPropertiesFromFirstItem(): void
    {
        // Arrange: create super admin and multiple items
        $this->loginAsRole('ROLE_SUPER_ADMIN');

        // Source item (first selected) - has properties to copy
        $sourceItem = ItemFactory::new()
            ->cloneable()
            ->withName('Source Item')
            ->withDescription('Source Description')
            ->withRent('100.00')
            ->withRentNotice('Source Notice')
            ->create();

        // Target items - will receive copied properties
        $targetItem1 = ItemFactory::new()
            ->cloneable()
            ->withName('Target Item 1')
            ->withDescription('Old Description 1')
            ->withRent('10.00')
            ->withRentNotice('Old Notice 1')
            ->create();

        $targetItem2 = ItemFactory::new()
            ->cloneable()
            ->withName('Target Item 2')
            ->withDescription('Old Description 2')
            ->withRent('20.00')
            ->withRentNotice('Old Notice 2')
            ->create();

        // Get CSRF token from list page
        $this->client->request('GET', '/admin/item/list');
        $csrfToken = $this->client->getCrawler()
            ->filter('input[name="all_elements"]')
            ->closest('form')
            ->filter('input[type="hidden"]')
            ->first()
            ->attr('value') ?? '';

        // Act: submit batch action
        $this->client->request('POST', '/admin/item/batch', [
            'action' => 'batchEdit',
            'idx' => [
                $sourceItem->getId(),
                $targetItem1->getId(),
                $targetItem2->getId(),
            ],
            '_sonata_csrf_token' => $csrfToken,
            'all_elements' => '0',
            'confirmation' => 'ok',
        ]);

        // Assert: redirected after batch action
        $response = $this->client->getResponse();
        self::assertTrue(
            $response->isRedirect(),
            'Expected redirect after batch edit, got: '.$response->getStatusCode()
        );

        // Verify properties were copied
        $this->em()->clear();
        $updatedTarget1 = $this->em()->getRepository(Item::class)->find($targetItem1->getId());
        $updatedTarget2 = $this->em()->getRepository(Item::class)->find($targetItem2->getId());

        self::assertNotNull($updatedTarget1);
        self::assertNotNull($updatedTarget2);

        // Target items should have source item's description, rent, and rentNotice
        self::assertSame('Source Description', $updatedTarget1->getDescription());
        self::assertEquals('100.00', $updatedTarget1->getRent());
        self::assertSame('Source Notice', $updatedTarget1->getRentNotice());

        self::assertSame('Source Description', $updatedTarget2->getDescription());
        self::assertEquals('100.00', $updatedTarget2->getRent());
        self::assertSame('Source Notice', $updatedTarget2->getRentNotice());
    }

    private function getItemAdminController(): \App\Controller\Admin\Rental\Inventory\ItemAdminController
    {
        return static::getContainer()->get(\App\Controller\Admin\Rental\Inventory\ItemAdminController::class);
    }

    private function getItemAdmin(): ItemAdmin
    {
        $admin = static::getContainer()->get('entropy.admin.item');
        self::assertInstanceOf(ItemAdmin::class, $admin);

        return $admin;
    }
}
