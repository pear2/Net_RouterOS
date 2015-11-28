<?php

namespace PEAR2\Net\RouterOS\Test\Communicator\Unsafe\NonPersistent;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\Test\Communicator\Unsafe\NonPersistent;

require_once __DIR__ . '/../NonPersistent.php';

/**
 * ~
 *
 * @group Communicator
 * @group Unsafe
 * @group NonPersistent
 * @group Unencrypted
 *
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class UnencryptedTest extends NonPersistent
{
    protected function setUp()
    {
        $this->object = new Communicator(\HOSTNAME, PORT);
        Client::login($this->object, USERNAME, PASSWORD);
    }
}
