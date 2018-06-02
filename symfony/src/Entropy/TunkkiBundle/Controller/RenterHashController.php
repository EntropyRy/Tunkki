<?php
namespace Entropy\TunkkiBundle\Controller;

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
            if($bookingdata[1]->getRenterConsent()){
                $class='hidden';
            } else {
                $class='button';
            }
            $form = $this->createFormBuilder($bookingdata[1])
                ->add('renterConsent',CheckboxType::class,[
                    'required' => true,
                    'label' => $translator->trans('renter_gives_consent')
                ])
                ->add('agree', SubmitType::class, ['attr'=>['class'=>$class]])
                ->getForm();
			if($request->getMethod() == 'POST'){
				$form->handleRequest($request);
				if($form->isValid() && $form->isSubmitted()){
					//$bookingdata = $form->getData();
					$this->em->persist($bookingdata[1]);
					$this->em->flush();
				}
			}
            $cms = $this->container->get("sonata.page.cms.page");
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
