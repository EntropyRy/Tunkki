<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sonata\SeoBundle\Seo\SeoPageInterface;
use App\Entity\Event;

class EventController extends Controller
{
    protected $em;
    public function oneId(Request $request, CmsManagerSelector $cms, TranslatorInterface $trans, SeoPageInterface $seo)
    {
        $eventid = $request->get('id');
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
        
        if ($request->getLocale() == 'en'){
            $page->setTitle($eventdata->getName());
            $seo->addMeta('property', 'og:title',$eventdata->getName())
                ->addMeta('property', 'og:description', $eventdata->getContent());
        } else {
            $page->setTitle($eventdata->getNimi());
            $seo->addMeta('property', 'og:title',$eventdata->getNimi())
                ->addMeta('property', 'og:description', $eventdata->getSisallys());
        }
        return $this->render('event.html.twig', [
                'event' => $eventdata,
                'page' => $page
            ]);
    }
    public function oneSlug(Request $request, CmsManagerSelector $cms, TranslatorInterface $trans)
    {
        $slug = $request->get('slug');
        $year = $request->get('year');
        if(empty($slug)){
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $this->em = $this->getDoctrine()->getManager();
        $eventdata = $this->em->getRepository(Event::class)->findBy(['url' => $slug]);
        foreach ($eventdata as $event){
            if ($event->geteventDate()->format('Y') == $year){
                $eventdata = $event;
                break;
            }
        }
        if(!$eventdata){
            throw new NotFoundHttpException($trans->trans("event_not_found"));
        }
        $page = $cms->retrieve()->getCurrentPage();
        if ($request->getLocale() == 'en'){
            $page->setTitle($eventdata->getName());
        } else {
            $page->setTitle($eventdata->getNimi());
        }
        return $this->render('event.html.twig', [
                'event' => $eventdata,
                'page' => $page
            ]);
    }
}
