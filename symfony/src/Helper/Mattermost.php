<?php

namespace App\Helper;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class Mattermost
{
    public function __construct(protected ParameterBagInterface $bag)
    {
    }
    public function SendToMattermost($text, $channel = null): string
    {
        $xcURL = $this->bag->get('mm_tunkki_hook');
        $botname = $this->bag->get('mm_tunkki_botname');
        $botimg = $this->bag->get('mm_tunkki_img');
        if ($_ENV["APP_ENV"] == 'dev') {
            $channel = null;
        }
        $curl = curl_init($xcURL);
        $array = [
            'username' => $botname,
            'icon_url' => $botimg,
            'channel' => $channel,
            'text' => $text,
        ];
        $cOptArr = [
            CURLOPT_URL => $xcURL,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1
        ];
        curl_setopt_array($curl, $cOptArr);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query(['payload' => json_encode($array)]));
        if (!curl_exec($curl)) {
            trigger_error(curl_error($curl));
        }
        curl_close($curl);
        return 'Done';
    }
}
