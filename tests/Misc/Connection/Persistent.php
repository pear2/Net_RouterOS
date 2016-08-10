<?php

namespace PEAR2\Net\RouterOS\Test\Misc\Connection;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\Registry;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\Test\Misc\Connection;

require_once __DIR__ . '/../Connection.php';

abstract class Persistent extends Connection
{

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        static::$persistent = true;
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        static::$persistent = null;
    }

    public function testTaglessModePassing()
    {
        $com1 = new Communicator(
            \HOSTNAME,
            static::$encryption ? ENC_PORT : PORT,
            true,
            null,
            static::$encryption
        );
        Client::login($com1, USERNAME, PASSWORD);
        $reg1 = new Registry('dummy');
        
        $com2 = new Communicator(
            \HOSTNAME,
            static::$encryption ? ENC_PORT : PORT,
            true,
            null,
            static::$encryption
        );
        $reg2 = new Registry('dummy');
        
        $this->assertNotEquals(
            $reg1->getOwnershipTag(),
            $reg2->getOwnershipTag()
        );
        
        $pingRequest1 = new Request(
            '/ping address=' . HOSTNAME,
            null,
            'ping'
        );
        $pingRequest1->send($com1, $reg1);
        
        $response1_1 = new Response($com1, false, null, null, $reg1);
        
        $cancelRequest = new Request('/cancel');
        $reg1->setTaglessMode(true);
        $cancelRequest->setArgument('tag', $reg1->getOwnershipTag() . 'ping');
        $cancelRequest->send($com1, $reg1);
        
        $pingRequest2 = new Request(
            '/ping count=2 address=' . HOSTNAME,
            null,
            'ping'
        );
        $pingRequest2->send($com2, $reg2);
        
        $response2_1 = new Response($com2, false, null, null, $reg2);
        $response2_2 = new Response($com2, false, null, null, $reg2);
        $response2_3 = new Response($com2, false, null, null, $reg2);
        $reg1->setTaglessMode(false);
        
        $com1->close();
        $com2->close();
        
        $this->assertEquals(Response::TYPE_DATA, $response2_1->getType());
        $this->assertEquals(Response::TYPE_DATA, $response2_2->getType());
        $this->assertEquals(Response::TYPE_FINAL, $response2_3->getType());
        
        $response1_2 = new Response($com1, false, null, null, $reg1);
        $response1_3 = new Response($com1, false, null, null, $reg1);
        
        $this->assertEquals(Response::TYPE_DATA, $response1_1->getType());
        $this->assertEquals(Response::TYPE_ERROR, $response1_2->getType());
        $this->assertEquals(Response::TYPE_FINAL, $response1_3->getType());
        
        $reg1->close();
        $this->assertStringStartsWith('-1_', $reg2->getOwnershipTag());
    }
}
