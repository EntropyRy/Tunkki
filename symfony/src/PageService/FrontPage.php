<?php

namespace App\PageService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Sonata\PageBundle\Model\PageInterface;
use Sonata\PageBundle\Page\Service\PageServiceInterface;
use Sonata\PageBundle\Page\TemplateManager;
use App\Entity\Event;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FrontPage implements PageServiceInterface
{
    public function __construct(
        private $name,
        private readonly TemplateManager $templateManager,
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $client
    ) {
    }
    public function getName(): string
    {
        return $this->name;
    }

    public function execute(PageInterface $page, Request $request, array $parameters = [], Response $response = null): Response
    {
        $r = $this->em->getRepository(Event::class);
        $events =[];
        $future = $r->getFutureEvents();
        $announcement = $r->findOneEventByTypeWithSticky('announcement');
        //$event = $r->findOneEventByTypeWithSticky('event');
        //$clubroom = $r->findOneEventByTypeWithSticky('clubroom');
        /*if ($clubroom->getEventDate() > $event->getEventDate()){
            $events = [$clubroom, $event];
        } else {
            $events = [$event, $clubroom];
        }*/
        /*if ($announcement->getEventDate() > $events[0]->getEventDate()){
            $events = array_merge([$announcement], $events);
        } else {
            $events = array_merge($events, [$announcement]);
        }*/
        $epic = $this->getRandomPic();
        $events = array_merge($future, [$announcement]);

        return $this->templateManager->renderResponse(
            $page->getTemplateCode(),
            [...$parameters, ...[
                'events'=>$events,
                'epic'=>$epic
            ]], //'clubroom'=>$clubroom)),
            $response
        );
    }
    private function getRandomPic(): ?string
    {
        try {
            $response = $this->client->request(
                'POST',
                'https://epics.entropy.fi/api/Session::init',
                ['max_duration' => 4,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ]]
            );
            if ($response->getStatusCode() == 200) {
                $headers = $response->getHeaders();
                $xsfre = explode(";", $headers['set-cookie'][0]);
                $xsfr = explode("=", $xsfre[0]);
                $token = str_replace('%3D', '', $xsfr[1]);
                $response = $this->client->request(
                    'POST',
                    'https://epics.entropy.fi/api/Photo::getRandom',
                    ['max_duration' => 4,
                        'headers' => [
                            'Cookie' => $headers['set-cookie'][0].'; '. $headers['set-cookie'][1],
                            'X-XSRF-TOKEN' => $token,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json'
                        ]
                    ]
                );
                if ($response->getStatusCode() == 200) {
                    $array = json_decode($response->getContent(), true);
                    if (!is_null($array['size_variants']['thumb2x'])){
                        $url = $array['size_variants']['thumb2x']['url'];
                        return 'https://epics.entropy.fi/'.$url;
                    } else { return null; }

                }
            }
        } catch (TransportExceptionInterface $e) {
            return $e->getMessage();
        }
    }
}
