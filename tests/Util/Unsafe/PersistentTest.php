<?php

namespace PEAR2\Net\RouterOS\Util\Test\Unsafe;

use PEAR2\Net\RouterOS\Util\Test\UnsafeTest;

require_once __DIR__ . '/../UnsafeTest.php';

abstract class PersistentTest extends UnsafeTest
{
    protected function tearDown()
    {
        unset($this->util);
        $this->client->close();
        unset($this->client);
    }
}
