<?php

namespace App\Controller;

use App\Repository\MenuRepository;
use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class MenuAdminController extends CRUDController
{
    #[\Override]
    public function listAction(Request $request = null): RedirectResponse
    {
        return new RedirectResponse($this->admin->generateUrl('tree', $request->query->all()));
    }
    public function treeAction(MenuRepository $menuR): Response
    {
        $menudata = $menuR->getRootNodes();
        $datagrid = $this->admin->getDatagrid();
        $formView = $datagrid->getForm()->createView();
        //$this->setFormTheme($formView, $this->admin->getFilterTheme());
        return $this->render('admin/menutree.html.twig', [
            'action' => 'tree',
            'menu' => $menudata,
            'form' => $formView,
            'csrf_token' => $this->getCsrfToken('sonata.batch'),
        ]);
    }
}
