<?php

namespace PEAR2\Net\RouterOS\Util\Test\Safe;

use PEAR2\Net\RouterOS\Util\Test\SafeTest;

require_once __DIR__ . '/../SafeTest.php';

abstract class NonPersistentTest extends SafeTest
{
    protected function tearDown()
    {
        unset($this->util);
        unset($this->client);
    }
}
