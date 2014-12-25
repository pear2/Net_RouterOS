<?php

namespace PEAR2\Net\RouterOS\Test\Misc\Connection;

use PEAR2\Net\RouterOS\Test\Misc\Connection;

require_once __DIR__ . '/../Connection.php';

abstract class NonPersistent extends Connection
{

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        static::$persistent = false;
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        static::$persistent = null;
    }
}
