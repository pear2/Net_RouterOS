<?php

namespace PEAR2\Net\RouterOS\Test\Communicator\Safe;

use PEAR2\Net\RouterOS\Test\Communicator\Safe;

require_once __DIR__ . '/../Safe.php';

abstract class Persistent extends Safe
{
    
    protected function tearDown()
    {
        $this->object->close();
        unset($this->object);
    }
}
