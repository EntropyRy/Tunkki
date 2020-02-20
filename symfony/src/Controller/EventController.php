<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use Symfony\Contracts\Translation\TranslatorInterface;
use App\Entity\Event;

class EventController extends Controller
{
    protected $em;
    public function oneAction(Request $request, CmsManagerSelector $cms, TranslatorInterface $trans)
    {
        $eventid = $request->get('id');
        if(empty($eventid)){
            throw new NotFoundHttpException();
        }
        $this->em = $this->getDoctrine()->getManager();
        $eventdata = $this->em->getRepository(Event::class)->findOneBy(['id' => $eventid]);
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
