<?php

namespace PEAR2\Net\RouterOS\Test\Client\Safe\Persistent;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Test\Client\Safe\Persistent;
use PEAR2\Net\Transmitter\NetworkStream;

require_once __DIR__ . '/../Persistent.php';

/**
 * ~
 *
 * @group Client
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
class EncryptedTest extends Persistent
{
    protected function setUp()
    {
        $this->object = new Client(
            \HOSTNAME,
            USERNAME,
            PASSWORD,
            ENC_PORT,
            true,
            null,
            NetworkStream::CRYPTO_TLS
        );
    }
}
