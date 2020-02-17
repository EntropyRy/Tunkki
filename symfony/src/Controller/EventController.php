<?php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController as Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Sonata\PageBundle\CmsManager\CmsManagerSelector;
use App\Entity\Event;

class EventController extends Controller
{
    protected $em;
    public function oneAction(Request $request, CmsManagerSelector $cms)
    {
        $eventid = $request->get('id');
        if(empty($eventid)){
            throw new NotFoundHttpException();
        }
        $this->em = $this->getDoctrine()->getManager();
        $eventdata = $this->em->getRepository(Event::class)->findOneBy(['id' => $eventid]);
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
