<?php

namespace PEAR2\Net\RouterOS\Test\Communicator\Safe\NonPersistent;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\Test\Communicator\Safe\NonPersistent;
use PEAR2\Net\Transmitter\NetworkStream;

require_once __DIR__ . '/../NonPersistent.php';

/**
 * ~
 *
 * @group Communicator
 * @group Safe
 * @group NonPersistent
 * @group Encrypted
 *
 * @requires extension openssl
 *
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class EncryptedTest extends NonPersistent
{
    protected function setUp()
    {
        $this->object = new Communicator(
            \HOSTNAME,
            ENC_PORT,
            false,
            null,
            NetworkStream::CRYPTO_TLS
        );
        Client::login($this->object, USERNAME, PASSWORD);
    }
}
