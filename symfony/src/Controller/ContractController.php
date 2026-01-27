<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contract;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ContractController extends AbstractController
{
    #[Route(
        path: [
            'fi' => '/sopimus/{purpose}',
            'en' => '/contract/{purpose}',
        ],
        name: 'contract_show',
        requirements: [
            'purpose' => '[a-z0-9-]+',
        ],
        methods: ['GET'],
    )]
    public function show(
        EntityManagerInterface $em,
        string $purpose,
    ): Response {
        $purposeKey = array_search($purpose, Contract::PURPOSES, true);
        if (false === $purposeKey) {
            $purposeKey = array_search($purpose, Contract::SLUGS_FI, true);
        }
        if (false === $purposeKey) {
            throw $this->createNotFoundException();
        }
        $mappedPurpose = Contract::PURPOSES[$purposeKey];

        $contract = $em->getRepository(Contract::class)->findOneBy([
            'purpose' => $mappedPurpose,
        ]);
        if (!$contract instanceof Contract) {
            throw $this->createNotFoundException();
        }

        $labelKey = 'contract.purpose.'.$purposeKey;

        return $this->render('contract_public.html.twig', [
            'contract' => $contract,
            'label_key' => $labelKey,
        ]);
    }
}
