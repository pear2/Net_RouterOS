<?php

namespace PEAR2\Net\RouterOS\Test\Client\Safe;

use PEAR2\Net\RouterOS\Test\Client\Safe;

require_once __DIR__ . '/../Safe.php';

abstract class NonPersistent extends Safe
{
    protected function tearDown()
    {
        unset($this->object);
    }
}
