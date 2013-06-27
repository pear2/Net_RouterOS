<?php

namespace PEAR2\Net\RouterOS\Client\Test\Unsafe;

use PEAR2\Net\RouterOS\Client\Test\UnsafeTest;

require_once __DIR__ . '/../UnsafeTest.php';

abstract class NonPersistentTest extends UnsafeTest
{
    protected function tearDown()
    {
        unset($this->object);
    }
}
