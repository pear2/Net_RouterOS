<?php

namespace PEAR2\Net\RouterOS\Test\Util\Safe;

use PEAR2\Net\RouterOS\Test\Util\Safe;

require_once __DIR__ . '/../Safe.php';

abstract class NonPersistentTest extends Safe
{
    protected function tearDown()
    {
        unset($this->util);
        unset($this->client);
    }
}
