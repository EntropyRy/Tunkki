<?php

namespace App\Helper;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SSH
{
    public function __construct(protected ParameterBagInterface $bag)
    {
    }
    public function sendCommand($text): string|bool
    {
        $stream = null;
        $connection = $this->getConnection();
        if ($text == 'start') {
            $stream = ssh2_exec($connection, 'systemctl --user start es_streaming.target');
        }
        if ($text == 'stop') {
            $stream = ssh2_exec($connection, 'systemctl --user stop es_streaming.target');
        }
        $error = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        $ret = stream_get_contents($error);
        fclose($stream);
        unset($connection);
        return $ret;
    }
    protected function getConnection(): mixed
    {
        $connection = ssh2_connect($this->bag->get('recording.host'), $this->bag->get('recording.port'));
        ssh2_auth_password($connection, $this->bag->get('recording.user'), $this->bag->get('recording.pass'));
        return $connection;
    }
}
