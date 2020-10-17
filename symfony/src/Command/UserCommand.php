<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Console\Question\Question;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class UserCommand extends Command
{
    protected static $defaultName = 'entropy:user';
    private $passwordEncoder;
    private $em;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, EntityManagerInterface $em)
    {
        parent::__construct();
        $this->passwordEncoder = $passwordEncoder;
        $this->em = $em;
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
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if (!$user){
                $user = new User();
                $user->setEmail($email);
            }
            if($input->getOption('password')){
                $question = new Question('Please enter password for the user ');
                $helper = $this->getHelper('question');
                $question->setHidden(true);
                $question->setHiddenFallback(false);
                $pass = $helper->ask($input, $output, $question);
                $user->setPassword($this->passwordEncoder->encodePassword($user,$pass));
            }
            if($input->getOption('super-admin')){
                $user->setRoles(['ROLE_SUPER_ADMIN', 'ROLE_ADMIN']);
            }
            if($input->getOption('permissions')){
                $user->setRoles([$input->getOption('permissions')]);
            }
            $this->em->persist($user);
            $this->em->flush();
        }

        $io->success('Done!');

        return 0;
    }
}
