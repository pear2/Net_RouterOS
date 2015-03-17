<?php

namespace PEAR2\Net\RouterOS\Test\Misc\Connection\Persistent;

use PEAR2\Net\RouterOS\Test\Misc\Connection\Persistent;
use PEAR2\Net\Transmitter\NetworkStream;

require_once __DIR__ . '/../Persistent.php';

/**
 * @group Misc
 * @group Persistent
 * @group Unencrypted
 *
 * @requires PHP 5.3.9
 */
class UnencryptedTest extends Persistent
{

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        static::$encryption = NetworkStream::CRYPTO_OFF;
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        static::$encryption = null;
    }
}
