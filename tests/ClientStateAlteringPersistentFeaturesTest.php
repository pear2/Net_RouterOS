<?php
namespace PEAR2\Net\RouterOS;

require_once 'ClientStateAlteringFeaturesTest.php';

class ClientStateAlteringPersistentFeaturesTest extends ClientStateAlteringFeaturesTest
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