<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Console\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Member;

#[\Symfony\Component\Console\Attribute\AsCommand('entropy:user')]
class UserCommand extends Command
{

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordEncoder,
        private readonly EntityManagerInterface $em
    )
    {
        parent::__construct();
    }
    protected function configure()
    {
        $this
            ->setDescription('User management')
            ->addArgument('email', InputArgument::REQUIRED, 'user email')
            ->addOption('super-admin', null, InputOption::VALUE_NONE, 'make super admin')
            ->addOption('password', null, InputOption::VALUE_NONE, 'change password')
            ->addOption('permissions', null, InputOption::VALUE_REQUIRED, 'add permissions')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');
        if ($email) {
            $user = $this->em->getRepository(Member::class)->findOneBy(['email' => $email])->getUser();
            /*if (!$user){
                $user = new User();
                $member = new Member();
                $member->setEmail($email);
                $member->setUser($user);
            }*/
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
