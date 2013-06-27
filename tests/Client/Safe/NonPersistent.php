<?php

namespace PEAR2\Net\RouterOS\Client\Test\Safe;

use PEAR2\Net\RouterOS\Client\Test\SafeTest;

require_once __DIR__ . '/../SafeTest.php';

abstract class NonPersistent extends SafeTest
{
    protected function tearDown()
    {
        unset($this->object);
    }
}
