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
        $lang = $request->getLocale();
        $page = $cms->retrieve()->getCurrentPage();
        $this->setMetaData($lang, $eventdata, $page, $seo); 
        return $this->render('event.html.twig', [
                'event' => $eventdata,
                'page' => $page
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
