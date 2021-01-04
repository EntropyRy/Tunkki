<?php
namespace App\Helper;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use \ZMQ;

class ZMQHelper {
    protected $bag;

    public function __construct(ParameterBagInterface $bag) {
        $this->bag = $bag;
    }
    public function connect()
    {
        $hook = $this->bag->get('door_socket');
        $context = new \ZMQContext();
        $socket = $context->getSocket(ZMQ::SOCKET_REQ);
        $socket->setSockOpt(ZMQ::SOCKOPT_RCVTIMEO, 100);
        $socket->setSockOpt(ZMQ::SOCKOPT_LINGER, 200);
        $socket->connect($hook);
        return $socket;
    }
    public function send($text)
    {
        try {
            $soc = $this->connect();
            $soc->send($text);
            $resp = $soc->recv();
        } catch (\ZMQSocketException $e) {
            $resp = $e;
        }
        if (empty($resp)){
            $resp = 'broken';
        }
        return $resp;
    }
}
