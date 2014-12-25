<?php

namespace PEAR2\Net\RouterOS\Test\Util\Unsafe;

use PEAR2\Net\RouterOS\Test\Util\Unsafe;

require_once __DIR__ . '/../Unsafe.php';

abstract class Persistent extends Unsafe
{
    protected function tearDown()
    {
        unset($this->util);
        $this->client->close();
        unset($this->client);
    }
}
