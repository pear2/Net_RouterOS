<?php

namespace PEAR2\Net\RouterOS\Test\Client\Unsafe\Persistent;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Test\Client\Unsafe\Persistent;
use PEAR2\Net\Transmitter\NetworkStream;

require_once __DIR__ . '/../Persistent.php';

/**
 * ~
 *
 * @group Client
 * @group Unsafe
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

    public function testMultipleDifferentPersistentConnection()
    {
        try {
            $routerOS1 = new Client(
                \HOSTNAME,
                USERNAME2,
                PASSWORD2,
                PORT,
                true,
                null,
                NetworkStream::CRYPTO_TLS
            );
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS1,
                'Object initialization failed.'
            );

            $routerOS2 = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                PORT,
                true,
                null,
                NetworkStream::CRYPTO_TLS
            );
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS2,
                'Object initialization failed.'
            );


            $addRequest = new Request('/queue/simple/add');
            $addRequest->setArgument('name', TEST_QUEUE_NAME)
                ->setArgument('target', '0.0.0.0/0');
            $responses = $routerOS2->sendSync($addRequest);
            $this->assertEquals(
                1,
                count($responses),
                'There should be only one response.'
            );
            if (count($responses) === 1
                && $responses->getType() === Response::TYPE_FINAL
            ) {
                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $responses = $routerOS2->sendSync($removeRequest);
                $this->assertInstanceOf(
                    ROS_NAMESPACE . '\ResponseCollection',
                    $responses,
                    'Response should be one.'
                );
            }

            $routerOS1->close();
            $routerOS2->close();
        } catch (Exception $e) {
            $this->fail('Unable to connect normally.');
        }
    }
}
