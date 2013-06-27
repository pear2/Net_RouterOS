<?php

namespace PEAR2\Net\RouterOS\Client\Test\Unsafe\NonPersistent;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Client\Test\Unsafe\NonPersistentTest;

require_once __DIR__ . '/../NonPersistentTest.php';

/**
 * ~
 * 
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
class UnencryptedTest extends NonPersistentTest
{
    protected function setUp()
    {
        $this->object = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT);
    }
}
