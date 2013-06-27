<?php

namespace PEAR2\Net\RouterOS\Client\Test\Unsafe\Persistent;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Client\Test\Unsafe\PersistentTest;

require_once __DIR__ . '/../PersistentTest.php';

/**
 * ~
 * 
 * @group Unsafe
 * @group Persistent
 * @group Unencrypted
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class UnencryptedTest extends PersistentTest
{
    protected function setUp()
    {
        $this->object = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT, true);
    }
}
