<?php

namespace PEAR2\Net\RouterOS\Test\Client\Unsafe;

use PEAR2\Net\RouterOS\Test\Client\Unsafe;

require_once __DIR__ . '/../Unsafe.php';

abstract class NonPersistent extends Unsafe
{
    protected function tearDown()
    {
        unset($this->object);
    }
}
