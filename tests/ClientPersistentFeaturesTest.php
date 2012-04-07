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
}