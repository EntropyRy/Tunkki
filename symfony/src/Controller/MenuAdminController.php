<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Menu;

class MenuAdminController extends CRUDController
{
    public function listAction(Request $request = null): RedirectResponse
    {
        return new RedirectResponse($this->admin->generateUrl('tree', $request->query->all()));
    }
    public function treeAction(EntityManagerInterface $em): Response
    {
        $menudata = $em->getRepository(Menu::class)->getRootNodes();
        $datagrid = $this->admin->getDatagrid();
        $formView = $datagrid->getForm()->createView();
        //$this->setFormTheme($formView, $this->admin->getFilterTheme());
        return $this->renderWithExtraParams('admin/menutree.html.twig', [
            'action' => 'tree',
            'menu' => $menudata,
            'form' => $formView,
            'csrf_token' => $this->getCsrfToken('sonata.batch'),
        ]);
    }
}
