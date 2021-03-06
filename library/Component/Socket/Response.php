<?php

namespace Library\Component\Socket;


use Library\Component\Socket\AbstractInterface\AbstractClient;
use Library\Component\Socket\Client\TcpClient;
use Library\Component\Socket\Client\UdpClient;
use Library\Swoole\Init\Server;

class Response
{
    static function response(AbstractClient $client,$data,$eof = ''){
        if($client instanceof TcpClient){
            if($client->getClientType() == Type::WEB_SOCKET){
                return Server::getInstance()->getServer()->push($client->getFd(),$data);
            }else{
                return Server::getInstance()->getServer()->send($client->getFd(),$data.$eof,$client->getReactorId());
            }
        }else if($client instanceof UdpClient){
             return Server::getInstance()->getServer()->sendto($client->getAddress(),$client->getPort(),$data.$eof);
        }else{
            trigger_error( "client is not validate");
            return false;
        }
    }
}