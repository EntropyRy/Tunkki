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
    public const ACTIVE_MEMBER_REFERENCE = "fixture_user_active_member";
    public const STAGE_MEMBER_REFERENCE = "fixture_user_stage_member";
    public const SUPER_ADMIN_REFERENCE = "fixture_user_super_admin";

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

        // Super admin user (full elevated privileges)
        $super = new User();
        $super->setRoles([
            "ROLE_SUPER_ADMIN",
            "ROLE_ADMIN",
            "ROLE_SONATA_ADMIN",
        ]);
        $super->setAuthId("local-super-admin");

        $superPasswordHash = $this->passwordHasher->hashPassword(
            $super,
            "superpass123",
        );
        $super->setPassword($superPasswordHash);

        $superMember = new Member();
        $superMember->setEmail("superadmin@example.com");
        $superMember->setFirstname("Super");
        $superMember->setLastname("Admin");
        $superMember->setUsername("superadmin");
        $superMember->setLocale("en");
        $superMember->setCode("S-TEST-0001");
        $superMember->setEmailVerified(true);

        $superMember->setUser($super);
        $super->setMember($superMember);

        $manager->persist($superMember);
        $manager->persist($super);
        $this->addReference(self::SUPER_ADMIN_REFERENCE, $super);

        // Active member user (represents a member with an "active" internal status)
        $activeUser = new User();
        $activeUser->setRoles([]); // base roles only
        $activeUser->setAuthId("local-active");
        $activePasswordHash = $this->passwordHasher->hashPassword(
            $activeUser,
            "activepass123",
        );
        $activeUser->setPassword($activePasswordHash);

        $activeMember = new Member();
        $activeMember->setEmail("active.member@example.com");
        $activeMember->setFirstname("Active");
        $activeMember->setLastname("Member");
        $activeMember->setUsername("active_member");
        $activeMember->setLocale("en");
        $activeMember->setCode("U-ACTIVE-0001");
        $activeMember->setEmailVerified(true);
        // Link
        $activeMember->setUser($activeUser);
        $activeUser->setMember($activeMember);

        $manager->persist($activeMember);
        $manager->persist($activeUser);
        $this->addReference(self::ACTIVE_MEMBER_REFERENCE, $activeUser);

        // Stage member (could represent a member involved in stage/production tasks)
        $stageUser = new User();
        $stageUser->setRoles([]); // keep simple unless specific ROLE_STAGE exists
        $stageUser->setAuthId("local-stage");
        $stagePasswordHash = $this->passwordHasher->hashPassword(
            $stageUser,
            "stagepass123",
        );
        $stageUser->setPassword($stagePasswordHash);

        $stageMember = new Member();
        $stageMember->setEmail("stage.member@example.com");
        $stageMember->setFirstname("Stage");
        $stageMember->setLastname("Crew");
        $stageMember->setUsername("stage_member");
        $stageMember->setLocale("fi");
        $stageMember->setCode("U-STAGE-0001");
        $stageMember->setEmailVerified(true);
        $stageMember->setUser($stageUser);
        $stageUser->setMember($stageMember);

        $manager->persist($stageMember);
        $manager->persist($stageUser);
        $this->addReference(self::STAGE_MEMBER_REFERENCE, $stageUser);

        $manager->flush();
    }
}
