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
        return $this->render('event.html.twig', [
                'eventdata' => $eventdata,
                'page' => $page
            ]);
        throw new NotFoundHttpException();
    }
    public function allAction(Request $request, CmsManagerSelector $cms)
    {
        $this->em = $this->getDoctrine()->getManager();
        $eventdata = $this->em->getRepository(Event::class)->findAll();
        $page = $cms->retrieve()->getCurrentPage();
        return $this->render('events.html.twig', [
                'eventdata' => $eventdata,
                'page' => $page
            ]);
        throw new NotFoundHttpException();
    }
}
