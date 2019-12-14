<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelectorInterface;

// Form
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;


class RenterHashController extends Controller
{
    protected $em;
    public function indexAction(Request $request)
    {
        $bookingid = $request->get('bookingid');
        $hash = $request->get('hash');
        $renterid = $request->get('renterid');
        if(empty($bookingid) || empty($hash) || empty($renterid)){
            throw new NotFoundHttpException();
        }
        $this->em = $this->getDoctrine()->getManager();
        $renter = $this->em->getRepository('EntropyTunkkiBundle:Renter')
                ->findOneBy(['id' => $renterid]);
        $bookingdata = $this->em->getRepository('EntropyTunkkiBundle:Booking')
            ->getBookingData($bookingid, $hash, $renter);
        if (is_array($bookingdata[0])){
            $translator = $this->get('translator');
            $object = $bookingdata[1];
            if($object->getRenterConsent()){
                $class='hidden';
            } else {
                $class='btn btn-large btn-success';
            }
            $form = $this->createFormBuilder($object)
                ->add('renterConsent',CheckboxType::class,[
                    'required' => true,
                    'label' => $translator->trans('renter_gives_consent'),
                    'label_attr' => ['style'=>'margin-right: 5px;']
                ])
                ->add('agree', SubmitType::class, [
                    'attr'=>['class'=>$class],
                    'label'=> $translator->trans('agree')
                ])
                ->getForm();
			if($request->getMethod() == 'POST'){
				$form->handleRequest($request);
				if($form->isValid() && $form->isSubmitted()){
					//$bookingdata = $form->getData();
					$this->em->persist($object);
					$this->em->flush();
				}
			}
            $cms = $this->container->get("sonata.page.cms_manager_selector")->retrieve();
            $page = $cms->getCurrentPage();
            return $this->render('EntropyTunkkiBundle::stufflist_for_renter.html.twig', [
                'renter' => $renter, 
                'bookingdata' => $bookingdata[0],
                'form' => $form->createView(), 
                'page' => $page
            ]);
        }
        else {
            throw new NotFoundHttpException();
        }
    }
}
