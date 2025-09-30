<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Command\UserCommand;
use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Unit tests for the entropy:member console command (UserCommand).
 *
 * Focus:
 *  - Creating a new Member + User when none exists (with --create-user, password prompt, super-admin flag).
 *  - Updating an existing Member/User (password + permissions flag).
 *
 * We mock:
 *  - EntityManagerInterface
 *  - UserPasswordHasherInterface
 *  - Repository (EntityRepository) via PHPUnit mock so it satisfies the return type of getRepository().
 *
 * We assert:
 *  - Correct roles set for super-admin path.
 *  - Roles replaced (not merged) when using --permissions.
 *  - Password hashed through the hasher.
 *  - persist() and flush() are invoked exactly once per command execution.
 */
final class UserCommandTest extends TestCase
{
    /**
     * Helper to register command in a Console Application and return a tester.
     */
    private function makeTester(UserCommand $command): CommandTester
    {
        $app = new Application();
        $app->add($command);
        $cmd = $app->find('entropy:member');
        return new CommandTester($cmd);
    }

    public function testCreateUserWithSuperAdminAndPassword(): void
    {
        $email = 'new.user@example.test';
        $capturedUser = null;

        /** @var UserPasswordHasherInterface&MockObject $hasher */
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with(
                self::callback(function ($u) use (&$capturedUser): bool {
                    $capturedUser = $u;
                    return $u instanceof User;
                }),
                'S3cretPwd!'
            )
            ->willReturn('hashed-S3cretPwd!');

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        /** @var EntityRepository&MockObject $repo */
        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn(null); // simulate missing member => creation path

        $em
            ->expects(self::once())
            ->method('getRepository')
            ->with(Member::class)
            ->willReturn($repo);

        $em
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function ($entity) use (&$capturedUser, $email) {
                self::assertInstanceOf(User::class, $entity, 'Persisted entity must be User.');
                /** @var User $entity */
                $member = $entity->getMember();
                self::assertNotNull($member, 'Member should be created and linked.');
                self::assertSame($email, $member->getEmail(), 'Member email must be set.');
                self::assertContains('ROLE_SUPER_ADMIN', $entity->getRoles(), 'Super admin role expected.');
                self::assertContains('ROLE_ADMIN', $entity->getRoles(), 'Admin role expected.');
                $capturedUser = $entity;
                return true;
            }));

        $em->expects(self::once())->method('flush');

        $command = new UserCommand($hasher, $em);
        $tester = $this->makeTester($command);

        // Simulate interactive password input
        $tester->setInputs(['S3cretPwd!']);

        $exitCode = $tester->execute([
            'command' => 'entropy:member',
            'email' => $email,
            '--create-user' => true,
            '--password' => true,
            '--super-admin' => true,
        ]);

        self::assertSame(0, $exitCode, 'Command should exit successfully.');
        self::assertNotNull($capturedUser, 'User should have been captured.');
        self::assertSame('hashed-S3cretPwd!', $capturedUser->getPassword(), 'Password should be hashed.');
    }

    public function testModifyExistingUserPasswordAndPermissions(): void
    {
        $email = 'existing@example.test';
        $capturedUser = null;

        // Existing entities
        $member = new Member();
        $member->setEmail($email);
        $member->setFirstname('First');
        $member->setLastname('Last');
        $member->setLocale('fi');

        $user = new User();
        $user->setMember($member);
        $user->setRoles(['ROLE_OLD']);
        $member->setUser($user); // ensure bidirectional link so $member->getUser() returns the User

        /** @var UserPasswordHasherInterface&MockObject $hasher */
        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher
            ->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'N3wPass!')
            ->willReturn('hashed-N3wPass!');

        /** @var EntityManagerInterface&MockObject $em */
        $em = $this->createMock(EntityManagerInterface::class);

        /** @var EntityRepository&MockObject $repo */
        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($member);

        $em
            ->expects(self::once())
            ->method('getRepository')
            ->with(Member::class)
            ->willReturn($repo);

        $em
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(function ($entity) use (&$capturedUser, $user) {
                self::assertSame($user, $entity, 'Persist should receive existing User instance.');
                $capturedUser = $entity;
                return true;
            }));

        $em->expects(self::once())->method('flush');

        $command = new UserCommand($hasher, $em);
        $tester = $this->makeTester($command);

        $tester->setInputs(['N3wPass!']);

        $exitCode = $tester->execute([
            'command' => 'entropy:member',
            'email' => $email,
            '--password' => true,
            '--permissions' => 'ROLE_VIEWER',
        ]);

        self::assertSame(0, $exitCode, 'Command should exit successfully.');
        self::assertNotNull($capturedUser, 'Existing user should have been persisted.');
        self::assertSame('hashed-N3wPass!', $capturedUser->getPassword(), 'Password should have been updated.');
        $roles = $capturedUser->getRoles();
        sort($roles);
        self::assertSame(['ROLE_USER', 'ROLE_VIEWER'], $roles, 'Roles should be replaced by permissions option (implicit ROLE_USER always added).');
    }
}
