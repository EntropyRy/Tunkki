<?php

declare(strict_types=1);

namespace App\Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use Sonata\AdminBundle\Admin\Pool;
use Sonata\AdminBundle\Security\Handler\RoleSecurityHandler;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AdminRoleNamingTest extends KernelTestCase
{
    private Pool $adminPool;
    private RoleSecurityHandler $securityHandler;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->adminPool = $container->get('sonata.admin.pool');
        $handler = $container->get('sonata.admin.security.handler.role');
        \assert($handler instanceof RoleSecurityHandler);
        $this->securityHandler = $handler;
    }

    #[DataProvider('adminRoleProvider')]
    public function testBaseRoleMatchesEntropyPrefix(
        string $adminCode,
        string $expectedBaseRole,
    ): void {
        $admin = $this->adminPool->getAdminByAdminCode($adminCode);
        $baseRole = $this->securityHandler->getBaseRole($admin);

        $this->assertSame($expectedBaseRole.'_%s', $baseRole);
        $this->assertSame($expectedBaseRole.'_ALL', \sprintf($baseRole, 'ALL'));
    }

    public static function adminRoleProvider(): array
    {
        return [
            ['entropy.admin.item', 'ROLE_ENTROPY_ADMIN_ITEM'],
            ['entropy.admin.booking', 'ROLE_ENTROPY_ADMIN_BOOKING'],
            ['entropy.admin.reward', 'ROLE_ENTROPY_ADMIN_REWARD'],
            ['entropy.admin.event', 'ROLE_ENTROPY_ADMIN_EVENT'],
            ['entropy.admin.member', 'ROLE_ENTROPY_ADMIN_MEMBER'],
            ['entropy.admin.renter', 'ROLE_ENTROPY_ADMIN_RENTER'],
            ['entropy.admin.package', 'ROLE_ENTROPY_ADMIN_PACKAGE'],
            ['entropy.admin.accessory', 'ROLE_ENTROPY_ADMIN_ACCESSORY'],
            ['entropy.admin.accessory_choices', 'ROLE_ENTROPY_ADMIN_ACCESSORY_CHOICES'],
            ['entropy.admin.who_can_rent_choice', 'ROLE_ENTROPY_ADMIN_WHO_CAN_RENT_CHOICE'],
            ['entropy.admin.file', 'ROLE_ENTROPY_ADMIN_FILE'],
            ['entropy.admin.statusevent', 'ROLE_ENTROPY_ADMIN_STATUSEVENT'],
            ['entropy.admin.billable_event', 'ROLE_ENTROPY_ADMIN_BILLABLE_EVENT'],
        ];
    }
}
