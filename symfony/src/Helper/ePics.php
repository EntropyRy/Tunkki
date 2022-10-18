<?php

namespace App\Helper;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ePics
{
    public function __construct(
        private readonly HttpClientInterface $client
    ) { }
    public function getRandomPic(): ?array
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
                        $pic['url'] = 'https://epics.entropy.fi/'. $array['size_variants']['thumb2x']['url'];
                        $pic['taken'] = $array['taken_at'];
                        return $pic;
                    } else { return null; }
                }
            }
        } catch (TransportExceptionInterface $e) {
            // return $e->getMessage();
            return null;
        }
        return null;
    }
}
