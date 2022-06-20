<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Security\Core\Security;
use App\Entity\Event;
use App\Entity\RSVP;
use App\Form\RSVPType;

class EventController extends Controller
{
    protected $em;
    public function oneId(Request $request, CmsManagerSelector $cms, TranslatorInterface $trans, SeoPageInterface $seo)
    {
        $eventid = $request->get('id');
        $lang = $request->getLocale();
        if(empty($eventid)){
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $this->em = $this->getDoctrine()->getManager();
        $eventdata = $this->em->getRepository(Event::class)->findOneBy(['id' => $eventid]);
        if(!$eventdata){
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        if(empty($eventdata->getUrl()) && $eventdata->getexternalUrl()){
                return new RedirectResponse("/");
        }
        if($eventdata->getUrl()){
            if($eventdata->getexternalUrl()){
                return new RedirectResponse($eventdata->getUrl());
            }
            return $this->redirectToRoute('entropy_event_slug', [
                'year' => $eventdata->getEventDate()->format('Y'),
                'slug' => $eventdata->getUrl()
            ]);
        }
        $page = $cms->retrieve()->getCurrentPage();
        $this->setMetaData($lang, $eventdata, $page, $seo); 
        return $this->render('event.html.twig', [
                'event' => $eventdata,
                'page' => $page
            ]);
    }
    public function oneSlug(Request $request, CmsManagerSelector $cms, TranslatorInterface $trans, SeoPageInterface $seo)
    {
        $slug = $request->get('slug');
        $year = $request->get('year');
        if(empty($slug)){
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $this->em = $this->getDoctrine()->getManager();
        $eventdata = $this->em->getRepository(Event::class)
			->findEventBySlugAndYear($slug, $year);
        if(!$eventdata){
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $lang = $request->getLocale();
        $page = $cms->retrieve()->getCurrentPage();
        $this->setMetaData($lang, $eventdata, $page, $seo); 
        if($eventdata->getRsvpSystemEnabled() && !$this->getUser()){
            $rsvp = new RSVP();
            $form = $this->createForm(RSVPType::class, $rsvp);
            $form->handleRequest($request);
            if($form->isSubmitted() && $form->isValid()){
                $rsvp = $form->getData();
                $repo = $this->em->getRepository('App:Member');
                $exists = $repo->findByEmailOrName($rsvp->getEmail(), $rsvp->getFirstName(), $rsvp->getLastName());
                if ($exists){
                    $this->addFlash('warning', $trans->trans('rsvp.email_in_use'));
                } else {
                    $rsvp->setEvent($eventdata);
                    try {
                        $this->em->persist($rsvp);
                        $this->em->flush();
                        $this->addFlash('success', $trans->trans('rsvp.rsvpd_succesfully'));
                    } catch (\Exception $e) {
                        $this->addFlash('warning', $trans->trans('rsvp.already_rsvpd'));
                    }
                }
            }
            return $this->render('event.html.twig', [
                    'event' => $eventdata,
                    'page' => $page,
                    'rsvpForm' => $form->createView()
                ]);
        }
        return $this->render('event.html.twig', [
                'event' => $eventdata,
                'page' => $page,
            ]);
    }
    private function setMetaData($lang, $eventdata, $page, $seo)
    {
        $now = new \DateTime();
        if( $eventdata->getPublished() && $eventdata->getPublishDate() < $now) {
            $title = $eventdata->getNameByLang($lang).' - '. $eventdata->getEventDate()->format('d.m.Y, H:i');
            $page->setTitle($title);
            $seo->addMeta('property', 'og:title',$title)
                ->addMeta('property', 'og:description', $eventdata->getAbstract($lang))
                ;
            if($eventdata->getType() != 'announcement'){
                $seo->addMeta('property', 'og:type', 'event')
                    ->addMeta('property', 'event:start_time', $eventdata->getEventDate()->format('Y-m-d H:i'));
            }
        }
    }
}
