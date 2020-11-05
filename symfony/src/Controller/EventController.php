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
use App\Entity\Artist;
use App\Entity\EventArtistInfo;
use App\Form\EventArtistInfoType;
use App\Form\ArtistType;

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
            return new RedirectResponse($this->generateUrl('entropy_event_slug', [
                'year' => $eventdata->getEventDate()->format('Y'),
                'slug' => $eventdata->getUrl()
            ]));
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
        return $this->render('event.html.twig', [
                'event' => $eventdata,
    //            'page' => $page
            ]);
    }
	/**
	 * @IsGranted("ROLE_USER")
	 */
    public function artistSignUp(
        Request $request, 
        SeoPageInterface $seo,
        Security $security,
        TranslatorInterface $trans
    ){
        $artists = $security->getUser()->getMember()->getArtist();
        if (count($artists)==0){
            $this->addFlash('warning', $trans->trans('no_artsit_create_one'));
            $request->getSession()->set('referer', $request->getPathInfo());
            return new RedirectResponse($this->generateUrl('entropy_artist_create'));
        }
        $slug = $request->get('slug');
        $year = $request->get('year');
        $this->em = $this->getDoctrine()->getManager();
        $event = $this->em->getRepository(Event::class)
                          ->findEventBySlugAndYear($slug, $year);
        foreach ($artists as $key => $artist){
            foreach ($artist->getEventArtistInfos() as $info){
                if($info->getEvent() == $event){
                    unset($artists[$key]);
                }
            }
        } 
        if (count($artists)==0){
            $this->addFlash('warning', $trans->trans('all_artists_signed_up_create_one'));
            return new RedirectResponse($this->generateUrl('entropy_artist_create'));
        }
        $artisteventinfo = new EventArtistInfo();
        $artisteventinfo->setEvent($event);
        $form = $this->createForm(EventArtistInfoType::class, $artisteventinfo, ['artists' => $artists]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $artist = $form->getData();
            $artistClone = clone $artist->getArtist();
            $artistClone->setMember(null);
            $artistClone->setName($artistClone->getName().' for '.$event->getName());
            $artist->setArtistClone($artistClone);
            $this->em->persist($artistClone);
            $this->em->persist($artist);
            $this->em->flush();
            $this->addFlash('success', $trans->trans('succesfully_signed_up_for_the_party'));
            return new RedirectResponse($this->generateUrl('entropy_profile'));
        }
        //$page = $cms->retrieve()->getCurrentPage();
        return $this->render('artist/signup.html.twig', [
            'event' => $event,
            'form' => $form->createView(),
        ]);
    }
    private function setMetaData($lang, $eventdata, $page, $seo)
    {
        $page->setTitle($eventdata->getNameByLang($lang));
        $seo->addMeta('property', 'og:title',$eventdata->getNameByLang($lang))
            ->addMeta('property', 'og:description', $eventdata->getAbstract($lang))
            ;
        if($eventdata->getType() != 'announcement'){
            $seo->addMeta('property', 'og:type', 'event')
                ->addMeta('property', 'event:start_time', $eventdata->getEventDate()->format('Y-m-d H:i'));
        }

    }
}
