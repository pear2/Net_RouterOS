<?php
namespace PEAR2\Net\RouterOS;

use PEAR2\Net\Transmitter as T;

class ConnectionTest extends \PHPUnit_Framework_TestCase
{

    public static $defaultSocketTimeout;

    public static function setUpBeforeClass()
    {
        self::$defaultSocketTimeout = ini_set('default_socket_timeout', 2);
    }

    public static function tearDownAfterClass()
    {
        ini_set('default_socket_timeout', self::$defaultSocketTimeout);
    }

    public function testNormalConnection()
    {
        try {
            $routerOS = new Client(HOSTNAME, USERNAME, PASSWORD, PORT);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS,
                'Object initialization failed.'
            );
        } catch (Exception $e) {
            $this->fail('Unable to connect normally:' . (string) $e);
        }
    }

    public function testNormalPersistentConnection()
    {
        try {
            $routerOS = new Client(HOSTNAME, USERNAME, PASSWORD, PORT, true);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS,
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
            $routerOS1 = new Client(HOSTNAME, USERNAME, PASSWORD, PORT);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS1,
                'Object initialization failed.'
            );

            $routerOS2 = new Client(HOSTNAME, USERNAME, PASSWORD, PORT);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS2,
                'Object initialization failed.'
            );
        } catch (Exception $e) {
            $this->fail('Unable to connect normally:' . (string) $e);
        }
    }

    public function testMultiplePersistentConnection()
    {
        try {
            $routerOS1 = new Client(HOSTNAME, USERNAME, PASSWORD, PORT, true);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS1,
                'Object initialization failed.'
            );

            $routerOS2 = new Client(HOSTNAME, USERNAME, PASSWORD, PORT, true);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS2,
                'Object initialization failed.'
            );

            $routerOS1->close();
            $routerOS2->close();
        } catch (Exception $e) {
            $this->fail('Unable to connect normally:' . (string) $e);
        }
    }

    public function testNormalContextConnection()
    {
        try {
            $context = stream_context_create();
            $this->assertInternalType(
                'resource', $context, 'Failed to create context.'
            );
            $this->assertEquals(
                'stream-context', get_resource_type($context),
                'Failed to create proper context.'
            );
            $routerOS = new Client(
                HOSTNAME, USERNAME, PASSWORD, PORT, false, null, $context
            );
        } catch (SocketException $e) {
            $this->fail('Unable to connect normally.');
        }
    }

    public function testInvalidUsername()
    {
        try {
            $routerOS = new Client(HOSTNAME, USERNAME_INVALID, PASSWORD, PORT);

            $this->fail(
                'No proper connection with the username "'
                . USERNAME_INVALID
                . '" should be available.'
            );
        } catch (DataFlowException $e) {
            $this->assertEquals(100, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testInvalidPassword()
    {
        try {
            $routerOS = new Client(HOSTNAME, USERNAME, PASSWORD_INVALID, PORT);

            $this->fail(
                'No proper connection with the password "'
                . PASSWORD_INVALID
                . '" should be available.'
            );
        } catch (DataFlowException $e) {
            $this->assertEquals(100, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testInvalidUsernameAndPassword()
    {
        try {
            $routerOS = new Client(
                HOSTNAME, USERNAME_INVALID, PASSWORD_INVALID, PORT
            );

            $this->fail(
                'No proper connection with the username "'
                . USERNAME_INVALID
                . '" and password "'
                . PASSWORD_INVALID
                . '" should be available.'
            );
        } catch (DataFlowException $e) {
            $this->assertEquals(100, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testInvalidHost()
    {
        try {
            $routerOS = new Client(HOSTNAME_INVALID, USERNAME, PASSWORD, PORT);

            $this->fail(
                'No proper connection over hostname "'
                . HOSTNAME_INVALID
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(101, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testSilentHost()
    {
        try {
            $routerOS = new Client(HOSTNAME_SILENT, USERNAME, PASSWORD, PORT);

            $this->fail(
                'No proper connection over hostname "'
                . HOSTNAME_SILENT
                . '" should be available.'
            );
        } catch (T\SocketException $e) {
            $this->assertEquals(7, $e->getCode());
            $this->assertEquals(10060, $e->getSocketErrorNumber());
        }
    }

    public function testInvalidPort()
    {
        try {
            $routerOS = new Client(HOSTNAME, USERNAME, PASSWORD, PORT_INVALID);

            $this->fail(
                'No proper connection over port "'
                . PORT_INVALID
                . '" should be available.'
            );
        } catch (SocketException $e) {
            $this->assertEquals(101, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testSilentPort()
    {
        try {
            $routerOS = new Client(HOSTNAME, USERNAME, PASSWORD, PORT_SILENT);

            $this->fail(
                'No proper connection over port "'
                . PORT_SILENT
                . '" should be available.'
            );
        } catch (T\SocketException $e) {
            $this->assertEquals(7, $e->getCode());
            $this->assertEquals(10061, $e->getSocketErrorNumber());
        }
    }

    public function testInvalidTimeout()
    {
        try {
            $routerOS = new Client(
                HOSTNAME, USERNAME, PASSWORD, PORT, false, 'invalidTimeout'
            );

            $this->fail('No proper connection should be available.');
        } catch (T\SocketException $e) {
            $this->assertEquals(7, $e->getCode());
        }
    }

    public function testInvalidContextNotResource()
    {
        try {
            $routerOS = new Client(
                HOSTNAME, USERNAME, PASSWORD, PORT_SILENT, false, null,
                'notContext'
            );

            $this->fail('No proper connection should be available.');
        } catch (T\SocketException $e) {
            $this->assertEquals(6, $e->getCode());
        }
    }

    public function testInvalidContextInvalidResource()
    {
        try {
            $routerOS = new Client(
                HOSTNAME, USERNAME, PASSWORD, PORT_SILENT, false, null,
                fopen(__FILE__, 'a+')
            );

            $this->fail('No proper connection should be available.');
        } catch (T\SocketException $e) {
            $this->assertEquals(6, $e->getCode());
        }
    }

    public function testInvalidSocketOnClose()
    {
        try {
            $com = new Communicator(HOSTNAME, PORT);
            Client::login($com, USERNAME, PASSWORD);

            $com->close();
            new Response($com);
            $this->fail('Receiving had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(206, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testInvalidSocketOnReceive()
    {
        try {
            $com = new Communicator(HOSTNAME, PORT);
            Client::login($com, USERNAME, PASSWORD);

            new Response($com);
            $this->fail('Receiving had to fail.');
        } catch (T\SocketException $e) {
            $this->assertEquals(4, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testInvalidSocketOnStreamReceive()
    {
        try {
            $com = new Communicator(HOSTNAME, PORT);
            Client::login($com, USERNAME, PASSWORD);

            new Response($com, true);
            $this->fail('Receiving had to fail.');
        } catch (T\SocketException $e) {
            $this->assertEquals(4, $e->getCode(), 'Improper exception code.');
        }
    }

    public function testInvalidQuerySending()
    {
        $com = new Communicator(HOSTNAME, PORT);
        Client::login($com, USERNAME, PASSWORD);

        $com->sendWord('/ip/arp/print');
        $com->close();
        try {
            Query::where('address', HOSTNAME_INVALID)->send($com);
            $com->sendWord('');
            $this->fail('The query had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(209, $e->getCode(), 'Improper exception code.');
        }
    }

}