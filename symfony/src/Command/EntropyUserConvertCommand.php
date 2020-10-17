<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class EntropyUserConvertCommand extends Command
{
     private $em;
     public function __construct(EntityManagerInterface $em)
     {
       parent::__construct();
        $this->em = $em;
     }


    protected function configure()
    {
        $this
            ->setName('entropy:user-deserializion')
            ->setDescription('...')
            ->addArgument('argument', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $argument = $input->getArgument('argument');
        $em = $this->em;
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM fos_user_user m';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $old = $stmt->fetchAll();

        foreach ($old as $m){
            $array = unserialize($m['roles']);
            $output->writeln($m['id']);
            var_dump($array);
            if (empty($array))
                $array = ['ROLE_USER'];
            $repo = $this->em->getRepository(User::class);
            $query = $repo->createQueryBuilder('u')
                    ->update()
                    ->set('u.roles', ':array')
                    ->where('u.id = :id')
                    ->setParameter('array', json_encode($array))
                    ->setParameter('id', $m['id'])
                    ->getQuery();

            $query->execute();
        }
        $output->writeln('toimii');
        if ($input->getOption('option')) {
            $em->flush();
        }

    }

}
