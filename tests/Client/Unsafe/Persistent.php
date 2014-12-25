<?php

namespace PEAR2\Net\RouterOS\Test\Client\Unsafe;

use PEAR2\Net\RouterOS\Test\Client\Unsafe;

require_once __DIR__ . '/../Unsafe.php';

abstract class Persistent extends Unsafe
{
    protected function tearDown()
    {
        $this->object->close();
        unset($this->object);
    }
}
