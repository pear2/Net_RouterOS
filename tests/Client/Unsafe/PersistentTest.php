<?php

namespace PEAR2\Net\RouterOS\Client\Test\Unsafe;

use PEAR2\Net\RouterOS\Client\Test\UnsafeTest;

require_once __DIR__ . '/../UnsafeTest.php';

abstract class PersistentTest extends UnsafeTest
{
    protected function tearDown()
    {
        $this->object->close();
        unset($this->object);
    }
}
