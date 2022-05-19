<?php
/*

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Entropy\TunkkiBundle\Entity\Member;

class EntropyMemberImportCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('entropy:member-import')
            ->setDescription('...')
            ->addArgument('argument', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $argument = $input->getArgument('argument');
        $em = $this->getContainer()->get('doctrine')->getManager();
        $conn = $em->getConnection();
        $sql = 'SELECT * FROM oldmembers m';
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $old = $stmt->fetchAll();

        foreach ($old as $m){
            //if ($m['email'] != ''){
            $t = new Member();
            $splitName = explode(' ', $m['name']);
            $t->setFirstname($splitName[0]);
            $t->setLastname($splitName[1]);

            $email = str_replace(' ','',$m['email']);

            $t->setEmail($email);
            $t->setCityOfResidence($m['city']);
            $t->setPhone((string)$m['phone']);
            $t->setApplication(iconv(mb_detect_encoding($m['reason'], mb_detect_order(), true), "UTF-8",$m['reason']));
            if ($m["is_member"]=='y'){
                $t->setIsActiveMember(1);
            } else {
                $t->setIsActiveMember(0);
            }
            $t->setApplicationDate(date_create_from_format('Y-m-d H:i:s', $m["application_received_date"]));
            $t->setApplicationHandledDate(date_create_from_format('Y-m-d H:i:s',$m["application_handled_date"]));
            $t->setRejectReason($m["reject_reason"]);
            if($m["reject_reason"]){
                $t->setRejectReasonSent(1);
            }
            //$output->writeln($t->getApplication());
            $em->persist($t);
            
        }
        $output->writeln('toimii');
        if ($input->getOption('option')) {
            $em->flush();
        }

    }

}
 */
