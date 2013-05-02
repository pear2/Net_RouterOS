<?php
namespace PEAR2\Net\RouterOS;

require_once 'UtilStateAlteringFeaturesTest.php';

class UtilStateAlteringPersistentFeaturesTest
    extends UtilStateAlteringFeaturesTest
{
    protected function setUp()
    {
        $this->util = new Util(
            $this->client = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                PORT,
                true
            )
        );
    }

    protected function tearDown()
    {
        unset($this->util);
        $this->client->close();
        unset($this->client);
    }
}