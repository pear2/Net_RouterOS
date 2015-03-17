<?php

namespace PEAR2\Net\RouterOS\Test\Util\Safe\Persistent;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Test\Util\Safe\PersistentTest;
use PEAR2\Net\RouterOS\Util;
use PEAR2\Net\Transmitter\NetworkStream;

require_once __DIR__ . '/../PersistentTest.php';

/**
 * ~
 *
 * @group Safe
 * @group Persistent
 * @group Encrypted
 *
 * @requires extension openssl
 * @requires PHP 5.3.9
 *
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class EncryptedTest extends PersistentTest
{
    protected function setUp()
    {
        $this->util = new Util(
            $this->client = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                ENC_PORT,
                true,
                null,
                NetworkStream::CRYPTO_TLS
            )
        );
    }
}
