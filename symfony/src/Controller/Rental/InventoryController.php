<?php

declare(strict_types=1);

namespace App\Controller\Rental;

use App\Repository\Rental\Inventory\ItemRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class InventoryController extends AbstractController
{
    #[Route(
        path: [
            'en' => '/inventory/needs-fixing',
            'fi' => '/inventaario/korjattavat',
        ],
        name: 'inventory_needs_fixing',
    ),]
    public function needsFixing(ItemRepository $itemRepository): Response
    {
        $needsFixing = $itemRepository->findBy(['needsFixing' => true]);

        return $this->render('fix.html.twig', [
            'fix' => $needsFixing,
        ]);
    }
}
