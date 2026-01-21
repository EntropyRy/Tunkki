<?php

declare(strict_types=1);

namespace App\Tests\Functional\Login;

use App\Entity\AccessGroups;
use App\Tests\_Base\FixturesWebTestCase;
use App\Tests\Support\LoginHelperTrait;
use Sonata\AdminBundle\Admin\Pool;
use Sonata\AdminBundle\Security\Handler\RoleSecurityHandler;

final class RentalAccessGroupAdminAccessTest extends FixturesWebTestCase
{
    use LoginHelperTrait;

    private const RENTAL_ACCESS_GROUP_NAME = 'RENTAL';
    private const RENTAL_ACCESS_GROUP_ROLE = 'RENTERS';

    /**
     * Roles granted to the RENTERS access group (security.yaml role_hierarchy).
     *
     * @var string[]
     */
    private const RENTERS_ROLES = [
        'ROLE_ADMIN',
        'ROLE_SONATA_CLASSIFICATION_ADMIN_CATEGORY_ALL',
        'ROLE_SONATA_CLASSIFICATION_ADMIN_TAG_ALL',
        'ROLE_ENTROPY_ADMIN_PACKAGE_ALL',
        'ROLE_ENTROPY_ADMIN_INVOICEE_EDIT',
        'ROLE_ENTROPY_ADMIN_INVOICEE_LIST',
        'ROLE_ENTROPY_ADMIN_INVOICEE_CREATE',
        'ROLE_ENTROPY_ADMIN_INVOICEE_VIEW',
        'ROLE_ENTROPY_ADMIN_BILLABLE_EVENT_ALL',
        'ROLE_ENTROPY_ADMIN_ACCESSORY_CHOICES_LIST',
        'ROLE_ENTROPY_ADMIN_ACCESSORY_CHOICES_CREATE',
        'ROLE_ENTROPY_ADMIN_ACCESSORY_CHOICES_VIEW',
        'ROLE_SONATA_MEDIA_ADMIN_MEDIA_ALL',
        'ROLE_ENTROPY_ADMIN_ITEM_ALL',
        'ROLE_ENTROPY_ADMIN_EVENT_ALL',
        'ROLE_ENTROPY_ADMIN_FILE_ALL',
        'ROLE_ENTROPY_ADMIN_RENTER_ALL',
        'ROLE_ENTROPY_ADMIN_ACCESSORY_CHOICES_EDIT',
        'ROLE_ENTROPY_ADMIN_ACCESSORY_ALL',
        'ROLE_ENTROPY_ADMIN_WHO_CAN_RENT_CHOICE_EDIT',
        'ROLE_ENTROPY_ADMIN_WHO_CAN_RENT_CHOICE_LIST',
        'ROLE_ENTROPY_ADMIN_WHO_CAN_RENT_CHOICE_CREATE',
        'ROLE_ENTROPY_ADMIN_WHO_CAN_RENT_CHOICE_VIEW',
        'ROLE_ENTROPY_ADMIN_BOOKING_EDIT',
        'ROLE_ENTROPY_ADMIN_BOOKING_LIST',
        'ROLE_ENTROPY_ADMIN_BOOKING_CREATE',
        'ROLE_ENTROPY_ADMIN_BOOKING_VIEW',
        'ROLE_ENTROPY_ADMIN_CONTRACT_EDIT',
        'ROLE_ENTROPY_ADMIN_CONTRACT_LIST',
        'ROLE_ENTROPY_ADMIN_CONTRACT_VIEW',
        'ROLE_ENTROPY_ADMIN_STATUSEVENT_EDIT',
        'ROLE_ENTROPY_ADMIN_STATUSEVENT_LIST',
        'ROLE_ENTROPY_ADMIN_STATUSEVENT_CREATE',
    ];

    public function testRentalAccessGroupCanAccessConfiguredAdmins(): void
    {
        $email = \sprintf(
            'rental-admin-%s@example.test',
            bin2hex(random_bytes(4)),
        );

        $user = $this->createUserWithRoles([], $email);
        $accessGroup = (new AccessGroups())
            ->setName(self::RENTAL_ACCESS_GROUP_NAME)
            ->setRoles([self::RENTAL_ACCESS_GROUP_ROLE])
            ->setActive(true);
        $accessGroup->addUser($user);
        $user->addAccessGroup($accessGroup);

        $this->em()->persist($accessGroup);
        $this->em()->persist($user);
        $this->em()->flush();

        $this->loginAsEmail($email);

        $container = static::getContainer();
        $adminPool = $container->get('sonata.admin.pool');
        $roleHandler = $container->get('sonata.admin.security.handler.role');
        \assert($adminPool instanceof Pool);
        \assert($roleHandler instanceof RoleSecurityHandler);

        $targetAdmins = $this->adminsAllowedForRenters(
            $adminPool,
            $roleHandler,
        );

        self::assertNotEmpty(
            $targetAdmins,
            'No admin services matched RENTERS role map; check security.yaml configuration.',
        );

        foreach ($targetAdmins as $adminCode) {
            $admin = $adminPool->getAdminByAdminCode($adminCode);

            self::assertTrue(
                $admin->isGranted('LIST'),
                \sprintf(
                    'RENTAL access group should grant LIST access for admin "%s".',
                    $adminCode,
                ),
            );
        }
    }

    /**
     * @return string[]
     */
    private function adminsAllowedForRenters(
        Pool $adminPool,
        RoleSecurityHandler $roleHandler,
    ): array {
        $admins = [];

        foreach ($adminPool->getAdminServiceIds() as $adminCode) {
            $admin = $adminPool->getAdminByAdminCode($adminCode);
            $baseRole = $roleHandler->getBaseRole($admin);
            $roleAll = \sprintf($baseRole, 'ALL');
            $roleList = \sprintf($baseRole, 'LIST');

            if (
                !\in_array($roleAll, self::RENTERS_ROLES, true)
                && !\in_array($roleList, self::RENTERS_ROLES, true)
            ) {
                continue;
            }

            $admins[] = $adminCode;
        }

        sort($admins);

        return $admins;
    }
}
