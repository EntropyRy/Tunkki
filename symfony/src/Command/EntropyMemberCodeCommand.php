<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Helper\Barcode;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Member;

#[AsCommand(
    name: 'entropy:member:code',
    description: 'Add a short description for your command',
)]
class EntropyMemberCodeCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $members = $this->em->getRepository(Member::class)->findAll();
        $barcodeG = new Barcode();
        foreach ($members as $member) {
            $code = $barcodeG->getBarcode($member);
            $member->setCode($code['0']);
            $this->em->persist($member);
        }
        $this->em->flush();
        return Command::SUCCESS;
    }
}
