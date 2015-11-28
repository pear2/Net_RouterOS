<?php

namespace PEAR2\Net\RouterOS\Test\Communicator;

use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\InvalidArgumentException;
use PEAR2\Net\RouterOS\SocketException;
use PHPUnit_Framework_TestCase;

abstract class Safe extends PHPUnit_Framework_TestCase
{

    /**
     * @var Communicator
     */
    protected $object;

    public function testNonSeekableCommunicatorWord()
    {
        $value = fopen('php://output', 'a');
        try {
            $this->object->sendWordFromStream('', $value);
            $this->fail('Call had to fail.');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                InvalidArgumentException::CODE_SEEKABLE_REQUIRED,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testInvokability()
    {
        $com = $this->object;
        $request = new Request('/ping');
        $request('address', HOSTNAME)->setTag('p');
        $this->assertEquals(HOSTNAME, $request('address'));
        $this->assertEquals('p', $request->getTag());
        $this->assertEquals('p', $request());
        $request($com);
        $response = new Response(
            $com,
            false,
            ini_get('default_socket_timeout')
        );
        $this->assertInternalType('string', $response());
        $this->assertEquals(HOSTNAME, $response('host'));

        $request = new Request('/queue/simple/print');
        $query = Query::where('target', HOSTNAME_INVALID . '/32');
        $request($query);
        $this->assertSame($query, $request->getQuery());
        $com('/log/print');
        $com('');
    }

    public function testInvalidSocketOnReceive()
    {
        try {
            new Response($this->object);
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
            $response = new Response($this->object, true);
            $this->fail('Receiving had to fail.');
        } catch (SocketException $e) {
            $this->assertEquals(
                SocketException::CODE_NO_DATA,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }
}
