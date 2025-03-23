<?php

namespace App\Helper;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class SSH
{
    public function __construct(protected ParameterBagInterface $bag)
    {
    }
    public function sendCommand(string $text): string|bool
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
            stream_set_blocking($stream, true);
            sleep(1);
            $ret = stream_get_contents($stream);
            fclose($stream);
            unset($connection);

            // Convert the string result to a boolean
            // The file probably contains "1" for enabled and "0" for disabled
            return trim($ret) === "1";
        } catch (\Exception) {
            return false;
        }
    }
    protected function getConnection(): mixed
    {
        $connection = ssh2_connect(
            $this->bag->get('recording.host'),
            $this->bag->get('recording.port'),
            [],
            [ 'timeout' => ['sec' => 2, 'usec' => 0]]
        );
        ssh2_auth_password($connection, $this->bag->get('recording.user'), $this->bag->get('recording.pass'));
        return $connection;
    }
}
