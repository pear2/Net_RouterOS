<?php
namespace PEAR2\Net\RouterOS;

require_once 'UtilFeaturesTest.php';

class UtilPersistentFeaturesTest extends UtilFeaturesTest
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
