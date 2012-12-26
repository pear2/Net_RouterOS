<?php
namespace PEAR2\Net\RouterOS;

require_once 'ClientFeaturesTest.php';

class ClientPersistentFeaturesTest extends ClientFeaturesTest
{
    
    protected function setUp()
    {
        $this->object = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT, true);
    }
    
    protected function tearDown()
    {
        $this->object->close();
        unset($this->object);
    }
    
    public function testCancellingSeparation()
    {
        $client = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT, true);
        $pingRequest = new Request('/ping', null, 'ping');
        $pingRequest->setArgument('address', HOSTNAME);
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
