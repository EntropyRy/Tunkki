<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Member;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin-user',
    description: 'Create or update an admin user (non-interactive password supported).'
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // Required email arg
            ->addArgument('email', InputArgument::REQUIRED, 'Email for the admin account (unique)')

            // Optional attributes
            ->addOption('first-name', null, InputOption::VALUE_REQUIRED, 'Member first name', 'Admin')
            ->addOption('last-name', null, InputOption::VALUE_REQUIRED, 'Member last name', 'User')
            ->addOption('locale', null, InputOption::VALUE_REQUIRED, 'Member locale (fi|en)', 'fi')

            // Roles
            ->addOption('super-admin', null, InputOption::VALUE_NONE, 'Add ROLE_SUPER_ADMIN in addition to ROLE_ADMIN')
            ->addOption('role', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Additional roles, can be specified multiple times', [])

            // Password flow
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Password (non-interactive). Use with care.')
            ->addOption('ask-password', null, InputOption::VALUE_NONE, 'Prompt for password interactively (hidden)')

            // Update behavior
            ->addOption('update-if-exists', null, InputOption::VALUE_NONE, 'If user exists, update roles and password if provided')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = strtolower(trim((string) $input->getArgument('email')));
        if ('' === $email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid email address is required.');
        }

        $firstName = (string) $input->getOption('first-name');
        $lastName = (string) $input->getOption('last-name');
        $locale = (string) $input->getOption('locale');
        $locale = in_array($locale, ['fi', 'en'], true) ? $locale : 'fi';

        $isSuperAdmin = (bool) $input->getOption('super-admin');
        $extraRoles = (array) $input->getOption('role');
        $updateIfExists = (bool) $input->getOption('update-if-exists');

        // Resolve password
        $rawPassword = $input->getOption('password');
        $askPassword = (bool) $input->getOption('ask-password');

        if ($rawPassword && $askPassword) {
            $io->warning('Both --password and --ask-password provided. Using --password value.');
            $askPassword = false;
        }

        if (!$rawPassword && $askPassword) {
            $q = new Question('Enter password (hidden): ');
            $q->setHidden(true);
            $q->setHiddenFallback(false);
            $rawPassword = (string) $io->askQuestion($q);

            $q2 = new Question('Confirm password (hidden): ');
            $q2->setHidden(true);
            $q2->setHiddenFallback(false);
            $confirm = (string) $io->askQuestion($q2);

            if ($rawPassword !== $confirm) {
                $io->error('Password confirmation does not match.');

                return Command::FAILURE;
            }
        }

        // If creating a new user and password is missing, fail fast
        $memberRepo = $this->em->getRepository(Member::class);
        $this->em->getRepository(User::class);

        /** @var Member|null $member */
        $member = $memberRepo->findOneBy(['email' => $email]);
        /** @var User|null $user */
        $user = $member?->getUser();

        $isNewUser = null === $user;

        if ($isNewUser && !$rawPassword) {
            $io->error('Password is required when creating a new user. Provide --password or --ask-password.');

            return Command::FAILURE;
        }

        // Create member if needed
        if (null === $member) {
            $member = new Member();
            // The entity in this project has these setters according to code usage; use defensive checks where uncertain
            if (method_exists($member, 'setEmail')) {
                $member->setEmail($email);
            }
            if (method_exists($member, 'setFirstname')) {
                $member->setFirstname($firstName);
            }
            if (method_exists($member, 'setLastname')) {
                $member->setLastname($lastName);
            }
            if (method_exists($member, 'setLocale')) {
                $member->setLocale($locale);
            }
            // Some projects have username distinct from email
            if (method_exists($member, 'setUsername')) {
                $member->setUsername($email);
            }
            // Required defaults for Member invariants
            if (method_exists($member, 'setCode') && method_exists($member, 'getCode') && null === $member->getCode()) {
                $member->setCode(bin2hex(random_bytes(8)));
            }
            if (method_exists($member, 'setEmailVerified')) {
                $member->setEmailVerified(true);
            }
            if (method_exists($member, 'setAllowInfoMails')) {
                $member->setAllowInfoMails(true);
            }
            if (method_exists($member, 'setAllowActiveMemberMails')) {
                $member->setAllowActiveMemberMails(true);
            }
            $this->em->persist($member);
        } else {
            // Update simple member attributes only if empty (do not override on update unless they are not set)
            if (method_exists($member, 'getFirstname') && method_exists($member, 'setFirstname') && !$member->getFirstname()) {
                $member->setFirstname($firstName);
            }
            if (method_exists($member, 'getLastname') && method_exists($member, 'setLastname') && !$member->getLastname()) {
                $member->setLastname($lastName);
            }
            if (method_exists($member, 'getLocale') && method_exists($member, 'setLocale') && !$member->getLocale()) {
                $member->setLocale($locale);
            }
            // Backfill required defaults if missing
            if (method_exists($member, 'getCode') && method_exists($member, 'setCode') && null === $member->getCode()) {
                $member->setCode(bin2hex(random_bytes(8)));
            }
            if (method_exists($member, 'isEmailVerified') && method_exists($member, 'setEmailVerified') && null === $member->isEmailVerified()) {
                $member->setEmailVerified(true);
            }
            if (method_exists($member, 'isAllowInfoMails') && method_exists($member, 'setAllowInfoMails') && null === $member->isAllowInfoMails()) {
                $member->setAllowInfoMails(true);
            }
            if (method_exists($member, 'isAllowActiveMemberMails') && method_exists($member, 'setAllowActiveMemberMails') && null === $member->isAllowActiveMemberMails()) {
                $member->setAllowActiveMemberMails(true);
            }
        }

        // Create or update user
        if (null === $user) {
            $user = new User();
            if (method_exists($user, 'setMember')) {
                $user->setMember($member);
            }
            // Ensure required defaults on new user
            if (method_exists($user, 'getAuthId') && method_exists($user, 'setAuthId') && null === $user->getAuthId()) {
                $user->setAuthId(bin2hex(random_bytes(16)));
            }
            $this->em->persist($user);
        } else {
            if (!$updateIfExists) {
                $io->warning(sprintf('User for %s already exists. Use --update-if-exists to update roles/password.', $email));
            }
            // Ensure required defaults on existing user
            if (method_exists($user, 'getAuthId') && method_exists($user, 'setAuthId') && null === $user->getAuthId()) {
                $user->setAuthId(bin2hex(random_bytes(16)));
            }
        }

        // Roles: always ensure ROLE_USER + ROLE_ADMIN; add ROLE_SUPER_ADMIN if requested; include extras
        $roles = ['ROLE_USER', 'ROLE_ADMIN'];
        if ($isSuperAdmin) {
            $roles[] = 'ROLE_SUPER_ADMIN';
        }
        foreach ($extraRoles as $role) {
            $role = strtoupper(trim((string) $role));
            if ('' !== $role) {
                $roles[] = $role;
            }
        }
        $roles = array_values(array_unique($roles));

        if (($isNewUser || $updateIfExists) && method_exists($user, 'setRoles')) {
            $user->setRoles($roles);
        }

        if ($rawPassword && ($isNewUser || $updateIfExists)) {
            // Basic validation
            if (strlen((string) $rawPassword) < 8) {
                $io->error('Password must be at least 8 characters.');

                return Command::FAILURE;
            }
            // Hash and set password
            if (method_exists($user, 'setPassword')) {
                $user->setPassword($this->hasher->hashPassword($user, (string) $rawPassword));
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            '%s user for %s. Roles: %s%s',
            $isNewUser ? 'Created' : ($updateIfExists ? 'Updated' : 'Kept existing'),
            $email,
            implode(',', $roles),
            $rawPassword ? ' (password set)' : ($isNewUser ? '' : ' (password unchanged)')
        ));

        return Command::SUCCESS;
    }
}
