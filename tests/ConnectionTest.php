<?php
namespace PEAR2\Net\RouterOS\Misc\Test;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\DataFlowException;
use PEAR2\Net\RouterOS\Exception;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\SocketException;
use PEAR2\Net\Transmitter as T;
use PHPUnit_Framework_TestCase;

/**
 * ~
 * 
 * @group Misc
 * 
 * @category Net
 * @package  PEAR2_Net_RouterOS
 * @author   Vasil Rangelov <boen.robot@gmail.com>
 * @license  http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link     http://pear2.php.net/PEAR2_Net_RouterOS
 */
class ConnectionTest extends PHPUnit_Framework_TestCase
{
    
    /**
     * @var int
     */
    public static $defaultSocketTimeout;

    public static function setUpBeforeClass()
    {
        self::$defaultSocketTimeout = ini_set('default_socket_timeout', 2);
    }

    public static function tearDownAfterClass()
    {
        ini_set('default_socket_timeout', self::$defaultSocketTimeout);
    }
    
    public function testNormalEncryptedConnection()
    {
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                ENC_PORT,
                false,
                null,
                T\NetworkStream::CRYPTO_TLS
            );
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS,
                'Object initialization failed.'
            );
        } catch (Exception $e) {
            $this->fail('Unable to connect normally:' . (string) $e);
        }
    }

    public function testNormalConnection()
    {
        try {
            $routerOS = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT);
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS,
                'Object initialization failed.'
            );
        } catch (Exception $e) {
            $this->fail('Unable to connect normally:' . (string) $e);
        }
    }

    public function testNormalPersistentConnection()
    {
        try {
            $routerOS = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT, true);
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS,
                'Object initialization failed.'
            );
            $routerOS->close();
        } catch (Exception $e) {
            $this->fail('Unable to connect normally:' . (string) $e);
        }
    }

    public function testMultipleNormalConnection()
    {
        try {
            $routerOS1 = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT);
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS1,
                'Object initialization failed.'
            );

            $routerOS2 = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT);
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS2,
                'Object initialization failed.'
            );
        } catch (Exception $e) {
            $this->fail('Unable to connect normally:' . (string) $e);
        }
    }

    public function testMultiplePersistentConnection()
    {
        try {
            $routerOS1 = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT, true);
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS1,
                'Object initialization failed.'
            );

            $routerOS2 = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT, true);
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS2,
                'Object initialization failed.'
            );

            $routerOS1->close();
            $routerOS2->close();
        } catch (Exception $e) {
            $this->fail('Unable to connect normally:' . (string) $e);
        }
    }

    public function testNormalAnsiConnection()
    {
        $oldCharsets = Communicator::setDefaultCharset(
            array(
                Communicator::CHARSET_LOCAL => 'UTF-8',
                Communicator::CHARSET_REMOTE => ANSI_PASSWORD_CHARSET
            )
        );
        try {
            $routerOS = new Client(
                \HOSTNAME,
                ANSI_USERNAME,
                ANSI_PASSWORD,
                PORT
            );
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\Client',
                $routerOS,
                'Object initialization failed.'
            );
            Communicator::setDefaultCharset($oldCharsets);
        } catch (Exception $e) {
            Communicator::setDefaultCharset($oldCharsets);
            $this->fail('Unable to connect normally:' . (string) $e);
        }
    }

    public function testNormalContextConnection()
    {
        try {
            $context = stream_context_create();
            $this->assertInternalType(
                'resource',
                $context,
                'Failed to create context.'
            );
            $this->assertEquals(
                'stream-context',
                get_resource_type($context),
                'Failed to create proper context.'
            );
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                PORT,
                false,
                null,
                null,
                $context
            );
        } catch (SocketException $e) {
            $this->fail('Unable to connect normally.');
        }
    }

    public function testInvalidUsername()
    {
        try {
            $routerOS = new Client(\HOSTNAME, USERNAME_INVALID, PASSWORD, PORT);

            $this->fail(
                'No proper connection with the username "'
                . USERNAME_INVALID
                . '" should be available.'
            );
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_INVALID_CREDENTIALS,
                $e->getCode(),
                'Improper exception code.'
            );
        }
        
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME_INVALID,
                PASSWORD,
                PORT,
                true
            );

            $this->fail(
                'No proper connection with the username "'
                . USERNAME_INVALID
                . '" should be available.'
            );
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_INVALID_CREDENTIALS,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testInvalidPassword()
    {
        try {
            $routerOS = new Client(\HOSTNAME, USERNAME, PASSWORD_INVALID, PORT);

            $this->fail(
                'No proper connection with the password "'
                . PASSWORD_INVALID
                . '" should be available.'
            );
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_INVALID_CREDENTIALS,
                $e->getCode(),
                'Improper exception code.'
            );
        }
        
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD_INVALID,
                PORT,
                true
            );

            $this->fail(
                'No proper connection with the password "'
                . PASSWORD_INVALID
                . '" should be available.'
            );
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_INVALID_CREDENTIALS,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testInvalidUsernameAndPassword()
    {
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME_INVALID,
                PASSWORD_INVALID,
                PORT
            );

            $this->fail(
                'No proper connection with the username "'
                . USERNAME_INVALID
                . '" and password "'
                . PASSWORD_INVALID
                . '" should be available.'
            );
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_INVALID_CREDENTIALS,
                $e->getCode(),
                'Improper exception code.'
            );
        }
        
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME_INVALID,
                PASSWORD_INVALID,
                PORT,
                true
            );

            $this->fail(
                'No proper connection with the username "'
                . USERNAME_INVALID
                . '" and password "'
                . PASSWORD_INVALID
                . '" should be available.'
            );
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_INVALID_CREDENTIALS,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testInvalidHost()
    {
        try {
            $routerOS = new Client(\HOSTNAME_INVALID, USERNAME, PASSWORD, PORT);

            $this->fail(
                'No proper connection over hostname "'
                . \HOSTNAME_INVALID
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_SERVICE_INCOMPATIBLE,
                $e->getCode(),
                'Improper exception code.'
            );
        }
        
        try {
            $routerOS = new Client(
                \HOSTNAME_INVALID,
                USERNAME,
                PASSWORD,
                PORT,
                true
            );

            $this->fail(
                'No proper connection over hostname "'
                . \HOSTNAME_INVALID
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_SERVICE_INCOMPATIBLE,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testSilentHost()
    {
        try {
            $routerOS = new Client(\HOSTNAME_SILENT, USERNAME, PASSWORD, PORT);

            $this->fail(
                'No proper connection over hostname "'
                . \HOSTNAME_SILENT
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_CONNECTION_FAIL,
                $e->getCode()
            );
            $this->assertTrue($e->getPrevious() instanceof T\SocketException);
            $this->assertEquals(8, $e->getPrevious()->getCode());
            $this->assertEquals(
                10060,
                $e->getPrevious()->getSocketErrorNumber()
            );
        }
        
        try {
            $routerOS = new Client(
                \HOSTNAME_SILENT,
                USERNAME,
                PASSWORD,
                PORT,
                true
            );

            $this->fail(
                'No proper connection over hostname "'
                . \HOSTNAME_SILENT
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_CONNECTION_FAIL,
                $e->getCode()
            );
            $this->assertTrue($e->getPrevious() instanceof T\SocketException);
            $this->assertEquals(8, $e->getPrevious()->getCode());
            $this->assertEquals(
                10060,
                $e->getPrevious()->getSocketErrorNumber()
            );
        }
    }

    public function testInvalidPort()
    {
        try {
            $routerOS = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT_INVALID);

            $this->fail(
                'No proper connection over port "'
                . PORT_INVALID
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_SERVICE_INCOMPATIBLE,
                $e->getCode()
            );
        }
        
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                PORT_INVALID,
                true
            );

            $this->fail(
                'No proper connection over port "'
                . PORT_INVALID
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_SERVICE_INCOMPATIBLE,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testSilentPort()
    {
        try {
            $routerOS = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT_SILENT);

            $this->fail(
                'No proper connection over port "'
                . PORT_SILENT
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_CONNECTION_FAIL,
                $e->getCode()
            );
            $this->assertTrue($e->getPrevious() instanceof T\SocketException);
            $this->assertEquals(8, $e->getPrevious()->getCode());
            $this->assertEquals(
                10061,
                $e->getPrevious()->getSocketErrorNumber()
            );
        }
        
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                PORT_SILENT,
                true
            );

            $this->fail(
                'No proper connection over port "'
                . PORT_SILENT
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_CONNECTION_FAIL,
                $e->getCode()
            );
            $this->assertTrue($e->getPrevious() instanceof T\SocketException);
            $this->assertEquals(8, $e->getPrevious()->getCode());
            $this->assertEquals(
                10061,
                $e->getPrevious()->getSocketErrorNumber()
            );
        }
    }

    public function testInvalidTimeout()
    {
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                PORT,
                false,
                'invalidTimeout'
            );

            $this->fail('No proper connection should be available.');
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_CONNECTION_FAIL,
                $e->getCode()
            );
            $this->assertTrue($e->getPrevious() instanceof T\SocketException);
            $this->assertEquals(7, $e->getPrevious()->getCode());
        }
    }

    public function testInvalidContextNotResource()
    {
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                PORT_SILENT,
                false,
                null,
                null,
                'notContext'
            );

            $this->fail('No proper connection should be available.');
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_CONNECTION_FAIL,
                $e->getCode()
            );
            $this->assertTrue($e->getPrevious() instanceof T\SocketException);
            $this->assertEquals(6, $e->getPrevious()->getCode());
        }
    }

    public function testInvalidContextInvalidResource()
    {
        try {
            $routerOS = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                PORT_SILENT,
                false,
                null,
                null,
                fopen(__FILE__, 'a+')
            );

            $this->fail('No proper connection should be available.');
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_CONNECTION_FAIL,
                $e->getCode()
            );
            $this->assertTrue($e->getPrevious() instanceof T\SocketException);
            $this->assertEquals(6, $e->getPrevious()->getCode());
        }
    }

    public function testInvalidSocketOnClose()
    {
        try {
            $com = new Communicator(\HOSTNAME, PORT);
            Client::login($com, USERNAME, PASSWORD);

            $com->close();
            new Response($com);
            $this->fail('Receiving had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_NO_DATA,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testInvalidSocketOnReceive()
    {
        try {
            $com = new Communicator(\HOSTNAME, PORT);
            Client::login($com, USERNAME, PASSWORD);

            new Response($com);
            $this->fail('Receiving had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_NO_DATA,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testInvalidSocketOnStreamReceive()
    {
        try {
            $com = new Communicator(\HOSTNAME, PORT);
            Client::login($com, USERNAME, PASSWORD);

            new Response($com, true);
            $this->fail('Receiving had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_NO_DATA,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testInvalidQuerySending()
    {
        $com = new Communicator(\HOSTNAME, PORT);
        Client::login($com, USERNAME, PASSWORD);

        $com->sendWord('/ip/arp/print');
        $com->close();
        try {
            Query::where('address', HOSTNAME_INVALID)->send($com);
            $com->sendWord('');
            $this->fail('The query had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_QUERY_SEND_FAIL,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }
}
