<?php

namespace PEAR2\Net\RouterOS\Test\Client\Safe;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Test\Client as Test;
use PEAR2\Net\RouterOS\Test\Client\Safe;
use PEAR2\Net\RouterOS\Request;

require_once __DIR__ . '/../Safe.php';

abstract class Persistent extends Safe
{
    
    protected function tearDown()
    {
        $this->object->close();
        unset($this->object);
    }
    
    public function testCancellingSeparation()
    {
        $client = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT, true);
        $pingRequest = new Request('/ping', null, 'ping');
        $pingRequest->setArgument('address', Test\HOSTNAME);
        $this->object->sendAsync($pingRequest);
        $client->sendAsync($pingRequest);
        $client->loop(2);
        $this->object->loop(2);
        $this->assertGreaterThan(
            0,
            count($client->extractNewResponses('ping'))
        );
        $this->assertGreaterThan(
            0,
            count($this->object->extractNewResponses('ping'))
        );
        unset($client);
        $this->object->loop(2);
        $this->assertGreaterThan(
            0,
            count($this->object->extractNewResponses('ping'))
        );
    }
}
