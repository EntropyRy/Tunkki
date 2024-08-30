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
        $cmd = $this->bag->get('recording.script.' . $text);
        $stream = ssh2_exec($connection, $cmd);
        $error = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
        $ret = stream_get_contents($error);
        fclose($stream);
        unset($connection);
        return $ret;
    }
    public function checkStatus(): bool
    {
        try {
            $stream = null;
            $connection = $this->getConnection();
            $cmd = $this->bag->get('recording.script.check');
            $stream = ssh2_exec($connection, $cmd);
            sleep(1);
            $ret = fgets($stream);
            fclose($stream);
            unset($connection);
            return $ret;
        } catch (\Exception $e) {
            return false;
        }
    }
    protected function getConnection(): mixed
    {
        $connection = ssh2_connect($this->bag->get('recording.host'), $this->bag->get('recording.port'));
        ssh2_auth_password($connection, $this->bag->get('recording.user'), $this->bag->get('recording.pass'));
        return $connection;
    }
}
