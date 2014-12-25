<?php

namespace PEAR2\Net\RouterOS\Test\Misc\Connection;

use PEAR2\Net\RouterOS\Test\Misc\Connection;

require_once __DIR__ . '/../Connection.php';

abstract class Persistent extends Connection
{

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        static::$persistent = true;
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        static::$persistent = null;
    }
}
