<?php

namespace App\Command;

use App\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Member;

#[\Symfony\Component\Console\Attribute\AsCommand('entropy:member')]
class UserCommand extends Command
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordEncoder,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }
    protected function configure(): void
    {
        $this
            ->setDescription('Member management')
            ->addArgument('email', InputArgument::REQUIRED, 'member email')
            ->addOption('super-admin', null, InputOption::VALUE_NONE, 'make super admin')
            ->addOption('password', null, InputOption::VALUE_NONE, 'change password')
            ->addOption('permissions', null, InputOption::VALUE_REQUIRED, 'add permissions')
            ->addOption('create-user', null, InputOption::VALUE_NONE, 'create user too');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        if ($email) {
            $user = null;
            $member = $this->em->getRepository(Member::class)->findOneBy(['email' => $email]);
            if (is_null($member)) {
                if ($input->getOption('create-user')) {
                    $member = new Member();
                    $member->setEmail($email);
                    $member->setFirstname('admin');
                    $member->setLastname('padmin');
                    $member->setLocale('fi');
                    $user = new User();
                    $user->setMember($member);
                }
            } else {
                $user = $member->getUser();
            }
            if ($input->getOption('password')) {
                $question = new Question('Please enter password for the user ');
                $helper = $this->getHelper('question');
                $question->setHidden(true);
                $question->setHiddenFallback(false);
                $pass = $helper->ask($input, $output, $question);
                $user->setPassword($this->passwordEncoder->hashPassword($user, $pass));
            }
            if ($input->getOption('super-admin')) {
                $user->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);
            }
            if ($input->getOption('permissions')) {
                $user->setRoles([$input->getOption('permissions')]);
            }
            $this->em->persist($user);
            $this->em->flush();
        }

        $io->success('Done!');

        return 0;
    }
}
