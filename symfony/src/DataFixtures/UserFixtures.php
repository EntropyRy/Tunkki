<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Member;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    public const USER_REFERENCE = "fixture_user_user";
    public const ADMIN_REFERENCE = "fixture_user_admin";

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Normal user
        $user = new User();
        // Leave roles empty; ROLE_USER will be added automatically by getRoles()
        $user->setRoles([]);
        $user->setAuthId("local-user");

        $userPasswordHash = $this->passwordHasher->hashPassword(
            $user,
            "userpass123",
        );
        $user->setPassword($userPasswordHash);

        $member = new Member();
        $member->setEmail("testuser@example.com");
        $member->setFirstname("Test");
        $member->setLastname("User");
        $member->setUsername("testuser");
        $member->setLocale("en");
        $member->setCode("U-TEST-0001");
        $member->setEmailVerified(true);

        // Link user <-> member both ways if supported
        $member->setUser($user);
        $user->setMember($member);

        $manager->persist($member);
        $manager->persist($user);
        $this->addReference(self::USER_REFERENCE, $user);

        // Admin user
        $admin = new User();
        // Ensure admin can access admin UI
        $admin->setRoles(["ROLE_ADMIN", "ROLE_SONATA_ADMIN"]);
        $admin->setAuthId("local-admin");

        $adminPasswordHash = $this->passwordHasher->hashPassword(
            $admin,
            "adminpass123",
        );
        $admin->setPassword($adminPasswordHash);

        $adminMember = new Member();
        $adminMember->setEmail("admin@example.com");
        $adminMember->setFirstname("Admin");
        $adminMember->setLastname("User");
        $adminMember->setUsername("admin");
        $adminMember->setLocale("fi");
        $adminMember->setCode("A-TEST-0001");
        $adminMember->setEmailVerified(true);

        $adminMember->setUser($admin);
        $admin->setMember($adminMember);

        $manager->persist($adminMember);
        $manager->persist($admin);
        $this->addReference(self::ADMIN_REFERENCE, $admin);

        $manager->flush();
    }
}
