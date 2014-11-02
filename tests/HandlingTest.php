<?php

namespace PEAR2\Net\RouterOS\Misc\Test;

use DateInterval;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\InvalidArgumentException;
use PEAR2\Net\RouterOS\LengthException;
use PEAR2\Net\RouterOS\NotSupportedException;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Registry;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\UnexpectedValueException;
use PEAR2\Net\RouterOS\Util;
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
class HandlingTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param string $command
     * 
     * @dataProvider providerNonAbsoluteCommand
     * 
     * @return void
     */
    public function testNonAbsoluteCommand($command)
    {
        try {
            $invalidCommand = new Request($command);
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                InvalidArgumentException::CODE_ABSOLUTE_REQUIRED,
                $e->getCode(),
                "Improper exception thrown for the command '{$command}'."
            );
        }
    }
    
    public function providerNonAbsoluteCommand()
    {
        return array(
            0 => array('print'),
            1 => array(''),
            2 => array('ip arp print'),
            3 => array('login')
        );
    }

    /**
     * @param string $command
     * 
     * @dataProvider providerUnresolvableCommand
     * 
     * @return void
     */
    public function testUnresolvableCommand($command)
    {
        try {
            $invalidCommand = new Request($command);
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                InvalidArgumentException::CODE_CMD_UNRESOLVABLE,
                $e->getCode(),
                "Improper exception thrown for the command '{$command}'."
            );
        }
    }
    
    public function providerUnresolvableCommand()
    {
        return array(
            0 => array('/ip .. ..'),
            1 => array('/ip .. arp .. arp .. .. print')
        );
    }

    /**
     * @param string $command
     * 
     * @dataProvider providerInvalidCommand
     * 
     * @return void
     */
    public function testInvalidCommand($command)
    {
        try {
            $invalidCommand = new Request($command);
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                InvalidArgumentException::CODE_CMD_INVALID,
                $e->getCode(),
                "Improper exception thrown for the command '{$command}'."
            );
        }
    }
    
    public function providerInvalidCommand()
    {
        return array(
            0 => array('/ip/arp/ print'),
            1 => array('/ip /arp /print'),
            2 => array('/ip /arp /print'),
        );
    }

    /**
     * @param string $command
     * @param string $expected
     * 
     * @dataProvider providerCommandTranslation
     * 
     * @return void
     */
    public function testCommandTranslation($command, $expected)
    {
        $request = new Request('/cancel');
        $request->setCommand($command);
        $this->assertEquals(
            $expected,
            $request->getCommand(),
            "Command '{$command}' was not translated properly."
        );
    }
    
    public function providerCommandTranslation()
    {
        return array(
            array('/ip arp print', '/ip/arp/print'),
            array('/ip arp .. address print', '/ip/address/print'),
            array(
                '/queue simple .. tree .. simple print',
                '/queue/simple/print'
            ),
            array('/login goback ..', '/login')
        );
    }
    
    /**
     * @param string $command
     * @param string $expected
     * @param array  $args
     * 
     * @dataProvider providerCommandAndArgumentParsing
     * 
     * @return void
     */
    public function testCommandAndArgumentParsing($command, $expected, $args)
    {
        $request = new Request($command);
        $this->assertEquals(
            $expected,
            $request->getCommand(),
            "Command '{$command}' was not parsed properly."
        );
        $this->assertEquals(
            $args,
            $request->getIterator()->getArrayCopy(),
            "Command '{$command}' was not parsed properly."
        );
    }
    
    public function providerCommandAndArgumentParsing()
    {
        return array(
            0 => array(
                '/ip arp print detail=""',
                '/ip/arp/print',
                array(
                    'detail' => ''
                )
            ),
            1 => array(
                '/ip arp add address=192.168.0.1',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1'
                )
            ),
            2 => array(
                '/ip arp add address="192.168.0.1"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1'
                )
            ),
            3 => array(
                '/ip arp add comment="hello world"',
                '/ip/arp/add',
                array(
                    'comment' => 'hello world'
                )
            ),
            4 => array(
                '/ip arp add comment=hello world',
                '/ip/arp/add',
                array(
                    'comment' => 'hello',
                    'world' => ''
                )
            ),
            
            5 => array(
                '/ip arp add address=192.168.0.1 comment=hello world',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello',
                    'world' => ''
                )
            ),
            6 => array(
                '/ip arp add address="192.168.0.1" comment=hello world',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello',
                    'world' => ''
                )
            ),
            7 => array(
                '/ip arp add address=192.168.0.1 comment="hello world"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello world'
                )
            ),
            8 => array(
                '/ip arp add address="192.168.0.1" comment="hello world"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello world'
                )
            ),
            9 => array(
                '/ip arp add comment="hello world"',
                '/ip/arp/add',
                array(
                    'comment' => 'hello world'
                )
            ),
            10 => array(
                '/ip arp add comment=hello world',
                '/ip/arp/add',
                array(
                    'comment' => 'hello',
                    'world' => ''
                )
            ),
            11 => array(
                '/ip arp add address=192.168.0.1 comment=hello big world',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello',
                    'big' => '',
                    'world' => ''
                )
            ),
            12 => array(
                '/ip arp add address="192.168.0.1" comment=hello big world',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello',
                    'big' => '',
                    'world' => ''
                )
            ),
            13 => array(
                '/ip arp add address=192.168.0.1 comment="hello big world"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello big world'
                )
            ),
            14 => array(
                '/ip arp add address="192.168.0.1" comment="hello big world"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello big world'
                )
            ),
            15 => array(
                '/ip arp add comment="hello big world"',
                '/ip/arp/add',
                array(
                    'comment' => 'hello big world'
                )
            ),
            16 => array(
                '/ip arp add comment=hello big world',
                '/ip/arp/add',
                array(
                    'comment' => 'hello',
                    'big' => '',
                    'world' => ''
                )
            ),
            17 => array(
                '/ip arp add comment="\""',
                '/ip/arp/add',
                array(
                    'comment' => '"'
                )
            ),
            18 => array(
                '/ip/arp/add comment="\\\"',
                '/ip/arp/add',
                array(
                    'comment' => '\\'
                )
            ),
            19 => array(
                '/ip/arp/add comment="\\\\""',
                '/ip/arp/add',
                array(
                    'comment' => '\"'
                )
            ),
            20 => array(
                '/ip/arp/add comment="\~t\"\\\"',
                '/ip/arp/add',
                array(
                    'comment' => '\~t"\\'
                )
            ),
            21 => array(
                '/ip/arp/add comment="\~t\\\\""',
                '/ip/arp/add',
                array(
                    'comment' => '\~t\\"'
                )
            ),
            
            22 => array(
                '/ip/arp/print detail=""',
                '/ip/arp/print',
                array(
                    'detail' => ''
                )
            ),
            23 => array(
                '/ip/arp/add address=192.168.0.1',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1'
                )
            ),
            24 => array(
                '/ip/arp/add address="192.168.0.1"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1'
                )
            ),
            25 => array(
                '/ip/arp/add comment="hello world"',
                '/ip/arp/add',
                array(
                    'comment' => 'hello world'
                )
            ),
            26 => array(
                '/ip/arp/add comment=hello world',
                '/ip/arp/add',
                array(
                    'comment' => 'hello',
                    'world' => ''
                )
            ),
            
            27 => array(
                '/ip/arp/add address=192.168.0.1 comment=hello world',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello',
                    'world' => ''
                )
            ),
            28 => array(
                '/ip/arp/add address="192.168.0.1" comment=hello world',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello',
                    'world' => ''
                )
            ),
            29 => array(
                '/ip/arp/add address=192.168.0.1 comment="hello world"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello world'
                )
            ),
            30 => array(
                '/ip/arp/add address="192.168.0.1" comment="hello world"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello world'
                )
            ),
            31 => array(
                '/ip/arp/add comment="hello world"',
                '/ip/arp/add',
                array(
                    'comment' => 'hello world'
                )
            ),
            32 => array(
                '/ip/arp/add comment=hello world',
                '/ip/arp/add',
                array(
                    'comment' => 'hello',
                    'world' => ''
                )
            ),
            33 => array(
                '/ip/arp/add address=192.168.0.1 comment=hello big world',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello',
                    'big' => '',
                    'world' => ''
                )
            ),
            34 => array(
                '/ip/arp/add address="192.168.0.1" comment=hello big world',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello',
                    'big' => '',
                    'world' => ''
                )
            ),
            35 => array(
                '/ip/arp/add address=192.168.0.1 comment="hello big world"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello big world'
                )
            ),
            36 => array(
                '/ip/arp/add address="192.168.0.1" comment="hello big world"',
                '/ip/arp/add',
                array(
                    'address' => '192.168.0.1',
                    'comment' => 'hello big world'
                )
            ),
            37 => array(
                '/ip/arp/add comment="hello big world"',
                '/ip/arp/add',
                array(
                    'comment' => 'hello big world'
                )
            ),
            38 => array(
                '/ip/arp/add comment=hello big world',
                '/ip/arp/add',
                array(
                    'comment' => 'hello',
                    'big' => '',
                    'world' => ''
                )
            ),
            39 => array(
                '/ip/arp/add comment="\""',
                '/ip/arp/add',
                array(
                    'comment' => '"'
                )
            ),
            40 => array(
                '/ip/arp/add comment="\\\"',
                '/ip/arp/add',
                array(
                    'comment' => '\\'
                )
            ),
            41 => array(
                '/ip/arp/add comment="\\\\""',
                '/ip/arp/add',
                array(
                    'comment' => '\"'
                )
            ),
            42 => array(
                '/ip/arp/add comment="\~t\"\\\"',
                '/ip/arp/add',
                array(
                    'comment' => '\~t"\\'
                )
            ),
            43 => array(
                '/ip/arp/add comment="\~t\\\\""',
                '/ip/arp/add',
                array(
                    'comment' => '\~t\\"'
                )
            ),
            
            44 => array(
                '/ping address=192.168.0.1',
                '/ping',
                array(
                    'address' => '192.168.0.1'
                )
            ),
            45 => array(
                '/ping address="192.168.0.1"',
                '/ping',
                array(
                    'address' => '192.168.0.1'
                )
            ),
            46 => array(
                '/ping address=192.168.0.1 count=2',
                '/ping',
                array(
                    'address' => '192.168.0.1',
                    'count' => '2'
                )
            ),
            47 => array(
                '/ping address="192.168.0.1" count=2',
                '/ping',
                array(
                    'address' => '192.168.0.1',
                    'count' => '2'
                )
            ),
            48 => array(
                '/ping address=192.168.0.1 count="2"',
                '/ping',
                array(
                    'address' => '192.168.0.1',
                    'count' => '2'
                )
            ),
            49 => array(
                '/ping address="192.168.0.1" count="2"',
                '/ping',
                array(
                    'address' => '192.168.0.1',
                    'count' => '2'
                )
            ),
            50 => array(
                '/ping address=192.168.0.1
                count="2"',
                '/ping',
                array(
                    'address' => '192.168.0.1',
                    'count' => '2'
                )
            ),
            51 => array(
                '/ping address="192.168.0.1"
                count="2"',
                '/ping',
                array(
                    'address' => '192.168.0.1',
                    'count' => '2'
                )
            )
        );
    }
    
    /**
     * @param string $command
     * @param int    $code
     * 
     * @dataProvider providerCommandArgumentParsingExceptions
     * 
     * @return void
     */
    public function testCommandArgumentParsingExceptions($command, $code)
    {
        try {
            $request = new Request($command);
            $this->fail('Command had to fail.');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                $code,
                $e->getCode(),
                'Improper exception code'
            );
        }
    }
    
    public function providerCommandArgumentParsingExceptions()
    {
        return array(
            0 => array(
                '/ip arp add comment="""',
                InvalidArgumentException::CODE_NAME_UNPARSABLE
            ),
            1 => array(
                '/ip arp add comment= address=192.168.0.1',
                InvalidArgumentException::CODE_VALUE_UNPARSABLE
            )
        );
    }

    /**
     * @param string $name
     * 
     * @dataProvider providerInvalidArgumentName
     * 
     * @return void
     */
    public function testInvalidArgumentName($name)
    {
        try {
            $request = new Request('/ping');
            $request->setArgument($name);
            $this->fail('Argument should have thrown an exception');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                InvalidArgumentException::CODE_NAME_INVALID,
                $e->getCode(),
                "Improper exception code thrown for the name '{$name}'."
            );
        }
    }

    /**
     * @param string $name
     * 
     * @dataProvider providerInvalidArgumentName
     * 
     * @return void
     */
    public function testInvalidQueryArgumentName($name)
    {
        try {
            $query = Query::where($name);
            $this->fail('Argument should have thrown an exception');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                InvalidArgumentException::CODE_NAME_INVALID,
                $e->getCode(),
                "Improper exception code thrown for the name '{$name}'."
            );
        }
    }
    
    public function providerInvalidArgumentName()
    {
        return array(
            0 => array('='),
            1 => array(''),
            2 => array('=eqStart'),
            3 => array('eq=middle'),
            4 => array('eqEnd='),
            5 => array('name spaced'),
            6 => array('name with multiple spaces'),
            7 => array("Two\nLines")
        );
    }

    public function testNonSeekableArgumentValue()
    {
        $value = fopen('php://input', 'r');
        $request = new Request('/ping');
        $request->setArgument('address', $value);
        $actual = $request->getArgument('address');
        $this->assertNotSame($value, $actual);
        $this->assertInternalType('string', $actual);
        $this->assertStringStartsWith('Resource id #', $actual);
    }

    /**
     * @param string|int $action
     * 
     * @dataProvider providerInvalidQueryArgumentAction
     * 
     * @return void
     */
    public function testInvalidQueryArgumentAction($action)
    {
        try {
            $query = Query::where('address', null, $action);
            $this->fail('Argument should have thrown an exception');
        } catch (UnexpectedValueException $e) {
            $this->assertEquals(
                UnexpectedValueException::CODE_ACTION_UNKNOWN,
                $e->getCode(),
                "Improper exception thrown for the action '{$action}'."
            );
            $this->assertEquals($action, $e->getValue());
        }
    }
    
    public function providerInvalidQueryArgumentAction()
    {
        return array(
            0 => array(' '),
            1 => array('?'),
            2 => array('#'),
            3 => array('address'),
            4 => array('>='),
            5 => array('<='),
            6 => array('=>'),
            7 => array('=<'),
            8 => array(1),
            9 => array(0)
        );
    }

    public function testNonSeekableCommunicatorWord()
    {
        $value = fopen('php://input', 'r');
        $com = new Communicator(HOSTNAME, PORT);
        Client::login($com, USERNAME, PASSWORD);
        try {
            $com->sendWordFromStream('', $value);
            $this->fail('Call had to fail.');
        } catch (InvalidArgumentException $e) {
            $this->assertEquals(
                InvalidArgumentException::CODE_SEEKABLE_REQUIRED,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testNonSeekableQueryArgumentValue()
    {
        $value = fopen('php://input', 'r');
        $stringValue = (string) $value;
        $query1 = Query::where('address', $stringValue);
        $query2 = Query::where('address', $value);
        $this->assertEquals($query1, $query2);
    }

    public function testArgumentRemoval()
    {
        $request = new Request('/ip/arp/add');
        $this->assertEmpty($request);

        $request->setArgument('address', HOSTNAME_INVALID);
        $this->assertNotEmpty($request);
        $this->assertEquals(HOSTNAME_INVALID, $request->getArgument('address'));

        $request->removeAllArguments();
        $this->assertEmpty($request);
        $this->assertEquals(null, $request->getArgument('address'));

        $request->setArgument('address', HOSTNAME_INVALID);
        $this->assertNotEmpty($request);
        $this->assertEquals(HOSTNAME_INVALID, $request->getArgument('address'));
        $request->setArgument('address', null);
        $this->assertEmpty($request);
        $this->assertEquals(null, $request->getArgument('address'));
    }

    /**
     * @param string $expected
     * @param int    $length
     * 
     * @dataProvider providerLengths
     * 
     * @return void
     */
    public function testLengthEncoding($expected, $length)
    {
        $actual = Communicator::encodeLength($length);
        $this->assertEquals(
            $expected,
            $actual,
            "Length '0x" . dechex($length) .
            "' is not encoded correctly. It was encoded as '0x" .
            bin2hex($actual) . "' instead of '0x" .
            bin2hex($expected) . "'."
        );
    }

    /**
     * @param string $length
     * @param int    $expected
     * 
     * @dataProvider providerLengths
     * 
     * @return void
     */
    public function testLengthDecoding($length, $expected)
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, $length);
        rewind($stream);
        $trans = new T\Stream($stream);
        $this->assertEquals(
            $expected,
            Communicator::decodeLength($trans),
            "{$length} is not properly decoded."
        );
    }
    
    public function providerLengths()
    {
        return array(
            0 => array(chr(0), 0),
            1 => array(chr(0x1), 0x1),
            2 => array(chr(0x7E), 0x7E),
            3 => array(chr(0x7F), 0x7F),
            4 => array(chr(0x80) . chr(0x80), 0x80),
            5 => array(chr(0x80) . chr(0x81), 0x81),
            6 => array(chr(0xBF) . chr(0xFE), 0x3FFE),
            7 => array(chr(0xBF) . chr(0xFF), 0x3FFF),
            8 => array(chr(0xC0) . chr(0x40) . chr(0x00), 0x4000),
            9 => array(chr(0xC0) . chr(0x40) . chr(0x01), 0x4001),
            10 => array(chr(0xDF) . chr(0xFF) . chr(0xFE), 0x1FFFFE),
            11 => array(chr(0xDF) . chr(0xFF) . chr(0xFF), 0x1FFFFF),
            12 => array(
                chr(0xE0) . chr(0x20) . chr(0x00) . chr(0x00), 0x200000
            ),
            13 => array(
                chr(0xE0) . chr(0x20) . chr(0x00) . chr(0x01),
                0x200001
            ),
            14 => array(
                chr(0xEF) . chr(0xFF) . chr(0xFF) . chr(0xFE),
                0xFFFFFFE
            ),
            15 => array(
                chr(0xEF) . chr(0xFF) . chr(0xFF) . chr(0xFF),
                0xFFFFFFF
            ),
            16 => array(
                chr(0xF0) . chr(0x10) . chr(0x00) . chr(0x00) . chr(0x00),
                0x10000000
            ),
            17 => array(
                chr(0xF0) . chr(0x10) . chr(0x00) . chr(0x00) . chr(0x01),
                0x10000001
            ),
            18 => array(
                chr(0xF0) . chr(0xFF) . chr(0xFF) . chr(0xFF) . chr(0xFE),
                0xFFFFFFFE
            ),
            19 => array(
                chr(0xF0) . chr(0xFF) . chr(0xFF) . chr(0xFF) . chr(0xFF),
                0xFFFFFFFF
            ),
            20 => array(
                chr(0xF1) . chr(0x00) . chr(0x00) . chr(0x00) . chr(0x00),
                0x100000000
            ),
            21 => array(
                chr(0xF1) . chr(0x00) . chr(0x00) . chr(0x00) . chr(0x01),
                0x100000001
            ),
            22 => array(
                chr(0xF7) . chr(0xFF) . chr(0xFF) . chr(0xFF) . chr(0xFE),
                0x7FFFFFFFE
            ),
            23 => array(
                chr(0xF7) . chr(0xFF) . chr(0xFF) . chr(0xFF) . chr(0xFF),
                0x7FFFFFFFF
            )
        );
    }

    public function testLengthEncodingExceptions()
    {
        $smallLength = -1;
        try {
            Communicator::encodeLength($smallLength);
        } catch (LengthException $e) {
            $this->assertEquals(
                LengthException::CODE_INVALID,
                $e->getCode(),
                "Length '{$smallLength}' must not be encodable."
            );
            $this->assertEquals(
                $smallLength,
                $e->getLength(),
                'Exception is misleading.'
            );
        }
        $largeLength = 0x800000000;
        try {
            Communicator::encodeLength($largeLength);
        } catch (LengthException $e) {
            $this->assertEquals(
                LengthException::CODE_BEYOND_SHEME,
                $e->getCode(),
                "Length '{$largeLength}' must not be encodable."
            );
            $this->assertEquals(
                $largeLength,
                $e->getLength(),
                'Exception is misleading.'
            );
        }
    }

    /**
     * @param int $controlByte
     * 
     * @dataProvider providerControlByte
     * 
     * @return void
     */
    public function testControlByteException($controlByte)
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, chr($controlByte));
        rewind($stream);
        $trans = new T\Stream($stream);
        try {
            Communicator::decodeLength($trans);
        } catch (NotSupportedException $e) {
            $this->assertEquals(
                NotSupportedException::CODE_CONTROL_BYTE,
                $e->getCode(),
                'Improper exception code.'
            );
            $this->assertEquals(
                $controlByte,
                $e->getValue(),
                'Improper exception value.'
            );
        }
    }
    
    public function providerControlByte()
    {
        return array(
            0=> array(0xF8),
            1=> array(0xF9),
            2=> array(0xFA),
            3=> array(0xFB),
            4=> array(0xFC),
            5=> array(0xFD),
            6=> array(0xFE),
            7=> array(0xFF)
        );
    }

    public function testQuitMessage()
    {
        $com = new Communicator(HOSTNAME, PORT);
        Client::login($com, USERNAME, PASSWORD);

        $quitRequest = new Request('/quit');
        $quitRequest->send($com);
        $quitResponse = new Response(
            $com,
            false,
            ini_get('default_socket_timeout')
        );
        $this->assertEquals(
            1,
            count($quitResponse->getUnrecognizedWords()),
            'No message.'
        );
        $this->assertEquals(
            0,
            count($quitResponse),
            'There should be no arguments.'
        );
        $com->close();
    }

    public function testQuitMessageStream()
    {
        $com = new Communicator(HOSTNAME, PORT);
        Client::login($com, USERNAME, PASSWORD);

        $quitRequest = new Request('/quit');
        $quitRequest->send($com);
        $quitResponse = new Response(
            $com,
            true,
            ini_get('default_socket_timeout')
        );
        $this->assertEquals(
            1,
            count($quitResponse->getUnrecognizedWords()),
            'No message.'
        );
        $this->assertEquals(
            0,
            count($quitResponse),
            'There should be no arguments.'
        );
        $com->close();
    }
    
    public function testSetDefaultCharset()
    {
        $com = new Communicator(HOSTNAME, PORT);
        $this->assertNull($com->getCharset(Communicator::CHARSET_REMOTE));
        $this->assertNull($com->getCharset(Communicator::CHARSET_LOCAL));
        Communicator::setDefaultCharset('windows-1251');
        $this->assertNull($com->getCharset(Communicator::CHARSET_REMOTE));
        $this->assertNull($com->getCharset(Communicator::CHARSET_LOCAL));
        
        $com = new Communicator(HOSTNAME, PORT);
        $this->assertEquals(
            'windows-1251',
            $com->getCharset(Communicator::CHARSET_REMOTE)
        );
        $this->assertEquals(
            'windows-1251',
            $com->getCharset(Communicator::CHARSET_LOCAL)
        );
        Communicator::setDefaultCharset(
            array(
                Communicator::CHARSET_REMOTE => 'ISO-8859-1',
                Communicator::CHARSET_LOCAL  => 'ISO-8859-1'
            )
        );
        $this->assertEquals(
            'windows-1251',
            $com->getCharset(Communicator::CHARSET_REMOTE)
        );
        $this->assertEquals(
            'windows-1251',
            $com->getCharset(Communicator::CHARSET_LOCAL)
        );
        
        $com = new Communicator(HOSTNAME, PORT);
        $this->assertEquals(
            'ISO-8859-1',
            $com->getCharset(Communicator::CHARSET_REMOTE)
        );
        $this->assertEquals(
            'ISO-8859-1',
            $com->getCharset(Communicator::CHARSET_LOCAL)
        );
        Communicator::setDefaultCharset(null);
        $this->assertEquals(
            'ISO-8859-1',
            $com->getCharset(Communicator::CHARSET_REMOTE)
        );
        $this->assertEquals(
            'ISO-8859-1',
            $com->getCharset(Communicator::CHARSET_LOCAL)
        );
        
        $com = new Communicator(HOSTNAME, PORT);
        $this->assertNull($com->getCharset(Communicator::CHARSET_REMOTE));
        $this->assertNull($com->getCharset(Communicator::CHARSET_LOCAL));
        Communicator::setDefaultCharset(
            'windows-1251',
            Communicator::CHARSET_REMOTE
        );
        Communicator::setDefaultCharset(
            'ISO-8859-1',
            Communicator::CHARSET_LOCAL
        );
        $this->assertNull($com->getCharset(Communicator::CHARSET_REMOTE));
        $this->assertNull($com->getCharset(Communicator::CHARSET_LOCAL));
        
        $com = new Communicator(HOSTNAME, PORT);
        $this->assertEquals(
            'windows-1251',
            $com->getCharset(Communicator::CHARSET_REMOTE)
        );
        $this->assertEquals(
            'ISO-8859-1',
            $com->getCharset(Communicator::CHARSET_LOCAL)
        );
        Communicator::setDefaultCharset(null);
    }
    
    public function testInvokability()
    {
        $com = new Communicator(HOSTNAME, PORT);
        Client::login($com, USERNAME, PASSWORD);
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
        $com('/quit');
        $com('');
    }
    
    public function testTaglessModePassing()
    {
        $com1 = new Communicator(\HOSTNAME, PORT, true);
        Client::login($com1, USERNAME, PASSWORD);
        $reg1 = new Registry('dummy');
        
        $com2 = new Communicator(\HOSTNAME, PORT, true);
        $reg2 = new Registry('dummy');
        
        $this->assertNotEquals(
            $reg1->getOwnershipTag(),
            $reg2->getOwnershipTag()
        );
        
        $pingRequest1 = new Request(
            '/ping address=' . HOSTNAME,
            null,
            'ping'
        );
        $pingRequest1->send($com1, $reg1);
        
        $response1_1 = new Response($com1, false, null, null, $reg1);
        
        $cancelRequest = new Request('/cancel');
        $reg1->setTaglessMode(true);
        $cancelRequest->setArgument('tag', $reg1->getOwnershipTag() . 'ping');
        $cancelRequest->send($com1, $reg1);
        
        $pingRequest2 = new Request(
            '/ping count=2 address=' . HOSTNAME,
            null,
            'ping'
        );
        $pingRequest2->send($com2, $reg2);
        
        $response2_1 = new Response($com2, false, null, null, $reg2);
        $response2_2 = new Response($com2, false, null, null, $reg2);
        $response2_3 = new Response($com2, false, null, null, $reg2);
        $reg1->setTaglessMode(false);
        
        $com1->close();
        $com2->close();
        
        $this->assertEquals(Response::TYPE_DATA, $response2_1->getType());
        $this->assertEquals(Response::TYPE_DATA, $response2_2->getType());
        $this->assertEquals(Response::TYPE_FINAL, $response2_3->getType());
        
        $response1_2 = new Response($com1, false, null, null, $reg1);
        $response1_3 = new Response($com1, false, null, null, $reg1);
        
        $this->assertEquals(Response::TYPE_DATA, $response1_1->getType());
        $this->assertEquals(Response::TYPE_ERROR, $response1_2->getType());
        $this->assertEquals(Response::TYPE_FINAL, $response1_3->getType());
        
        $reg1->close();
        $this->assertStringStartsWith('-1_', $reg2->getOwnershipTag());
    }

    public function testPrepareScript()
    {
        $msg = 'testing';
        $result = Util::prepareScript(
            '/log print $msg',
            array('msg' => $msg)
        );
        $this->assertSame(
            ":local \"msg\" \"{$msg}\";\n/log print \$msg",
            stream_get_contents($result)
        );

        $testParam = fopen('php://temp', 'r+b');
        fwrite($testParam, $msg);
        rewind($testParam);
        $result = Util::prepareScript(
            '/log print $msg',
            array('msg' => $testParam)
        );
        $this->assertSame(
            ":local \"msg\" \"{$msg}\";\n/log print \$msg",
            stream_get_contents($result)
        );
        $this->assertSame(strlen($msg), ftell($testParam));
    }

    /**
     * @param string $value
     * @param mixed  $expected
     * 
     * @dataProvider providerUtilParseValue
     * 
     * @return void
     */
    public function testUtilParseValue($value, $expected)
    {
        $actual = Util::parseValue($value);
        $this->assertEquals($expected, $actual);
        $this->assertInternalType(strtolower(gettype($expected)), $actual);
    }
    
    public function providerUtilParseValue()
    {
        return array(
            0 => array('', null),
            1 => array('nil', null),
            2 => array('1', 1),
            3 => array('true', true),
            4 => array('yes', true),
            5 => array('false', false),
            6 => array('no', false),
            7 => array('"test"', 'test'),
            8 => array('test', 'test'),
            9 => array('00:00', new DateInterval('PT0H0M0S')),
            10 => array('00:00:00', new DateInterval('PT0H0M0S')),
            11 => array('1d00:00:00', new DateInterval('P1DT0H0M0S')),
            12 => array('1w00:00:00', new DateInterval('P7DT0H0M0S')),
            13 => array('1w1d00:00:00', new DateInterval('P8DT0H0M0S')),
            14 => array('{}', array()),
            15 => array('{a}', array('a')),
            16 => array('{1;2}', array(1, 2)),
            17 => array('{a;b}', array('a', 'b')),
            18 => array('{"a";"b"}', array('a', 'b')),
            19 => array('{"a;b";c}', array('a;b', 'c')),
            20 => array('{a;"b;c"}', array('a', 'b;c')),
            21 => array('{"a;b";c;d}', array('a;b', 'c', 'd')),
            22 => array('{a;"b;c";d}', array('a', 'b;c', 'd')),
            23 => array('{a;b;"c;d"}', array('a', 'b', 'c;d')),
            24 => array('{{a;b};c}', array(array('a', 'b'), 'c')),
            25 => array('{a;{b;c};d}', array('a', array('b', 'c'), 'd')),
            26 => array('{a;b;{c;d}}', array('a', 'b', array('c', 'd'))),
            27 => array(
                '{{a;{b;c}};d}',
                array(array('a', array('b', 'c')), 'd')
            ),
            28 => array(
                '{a=1;b=2}',
                array('a' => 1, 'b' => 2)
            ),
            29 => array(
                '{a="test1";b="test2"}',
                array('a' => 'test1', 'b' => 'test2')
            ),
            30 => array(
                '{a=1;b;c=2}',
                array('a' => 1, 'b', 'c' => 2)
            ),
            31 => array(
                '{a="b;c";d=2}',
                array('a' => 'b;c', 'd' => 2)
            ),
            32 => array(
                '{a="b;c=2";d=2}',
                array('a' => 'b;c=2', 'd' => 2)
            ),
            33 => array(
                '{a="b";c}',
                array('a' => 'b', 'c')
            ),
            34 => array(
                '{1="test"}',
                array(1 => 'test')
            ),
            35 => array(
                '{a',
                '{a'
            )
        );
    }
}
