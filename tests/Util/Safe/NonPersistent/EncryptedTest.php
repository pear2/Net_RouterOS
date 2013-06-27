<?php

namespace PEAR2\Net\RouterOS\Util\Test\Safe\NonPersistent;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Util;
use PEAR2\Net\RouterOS\Util\Test\Safe\NonPersistentTest;
use PEAR2\Net\Transmitter\NetworkStream;

require_once __DIR__ . '/../NonPersistentTest.php';

/**
 * ~
 * 
 * @group Safe
 * @group NonPersistent
 * @group Encrypted
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class EncryptedTest extends NonPersistentTest
{
    protected function setUp()
    {
        $this->util = new Util(
            $this->client = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                ENC_PORT,
                false,
                null,
                NetworkStream::CRYPTO_TLS
            )
        );
    }
}
