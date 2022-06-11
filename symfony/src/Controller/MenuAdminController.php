<?php

namespace App\Controller;

use Sonata\AdminBundle\Controller\CRUDController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;


class MenuAdminController extends CRUDController
{
    protected $em;
    public function listAction(Request $request = null)
    {
         return new RedirectResponse($this->admin->generateUrl('tree', $request->query->all()));
    }
    /**
     * @return Response
     */
    public function treeAction(Request $request)
    {
        $this->em = $this->getDoctrine()->getManager();
        $menudata = $this->em->getRepository('App:Menu')->getRootNodes();

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
