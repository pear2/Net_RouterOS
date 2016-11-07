<?php

namespace PEAR2\Net\RouterOS\Test\Misc;

use DateInterval;
use DateTime;
use DateTimeZone;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\InvalidArgumentException;
use PEAR2\Net\RouterOS\LengthException;
use PEAR2\Net\RouterOS\NotSupportedException;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\Script;
use PEAR2\Net\RouterOS\UnexpectedValueException;
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
class ConnectionlessTest extends PHPUnit_Framework_TestCase
{
    /**
     * @param string $command
     *
     * @return void
     *
     * @dataProvider providerNonAbsoluteCommand
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
     * @return void
     *
     * @dataProvider providerUnresolvableCommand
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
     * @return void
     *
     * @dataProvider providerInvalidCommand
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
     * @return void
     *
     * @dataProvider providerCommandTranslation
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
     * @return void
     *
     * @dataProvider providerCommandAndArgumentParsing
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
     * @return void
     *
     * @dataProvider providerCommandArgumentParsingExceptions
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
     * @return void
     *
     * @dataProvider providerInvalidArgumentName
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
     * @return void
     *
     * @dataProvider providerInvalidArgumentName
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
        $value = fopen('php://output', 'a');
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
     * @return void
     *
     * @dataProvider providerInvalidQueryArgumentAction
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

    public function testNonSeekableQueryArgumentValue()
    {
        $value = fopen('php://output', 'a');
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
     * @return void
     *
     * @dataProvider providerLengths
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
     * @return void
     *
     * @dataProvider providerLengths
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
     * @return void
     *
     * @dataProvider providerControlByte
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
            0 => array(0xF8),
            1 => array(0xF9),
            2 => array(0xFA),
            3 => array(0xFB),
            4 => array(0xFC),
            5 => array(0xFD),
            6 => array(0xFE),
            7 => array(0xFF)
        );
    }

    public function testInvalidResponseType()
    {
        $transMock = $this->createMock('PEAR2\Net\Transmitter\TcpClient');
        $transMock->method('isPersistent')->will(
            $this->onConsecutiveCalls(false, true)
        );
        $transMock->method('isAvailable')->willReturn(true);
        $transMock->method('isDataAwaiting')->willReturn(true);

        $comMock = $this->createMock(ROS_NAMESPACE . '\Communicator');
        $comMock->method('getTransmitter')->willReturn($transMock);
        $comMock->method('getNextWord')->willReturn('TEST');

        //Non persistent connection
        try {
            new Response($comMock);
            $this->fail('Getting unknown types should throw an exception.');
        } catch (UnexpectedValueException $e) {
            $this->assertSame(
                UnexpectedValueException::CODE_RESPONSE_TYPE_UNKNOWN,
                $e->getCode()
            );
        }

        //Persistent connection
        try {
            new Response($comMock);
            $this->fail('Getting unknown types should throw an exception.');
        } catch (UnexpectedValueException $e) {
            $this->assertSame(
                UnexpectedValueException::CODE_RESPONSE_TYPE_UNKNOWN,
                $e->getCode()
            );
        }
    }
    
    public function testUnexpectedLoginException()
    {
        $newLockException = new T\LockException('TEST');
        $transMock = $this->createMock('PEAR2\Net\Transmitter\TcpClient');
        $transMock->method('isPersistent')->willReturn(true);
        $transMock->method('isAvailable')->willReturn(true);
        $transMock->method('isDataAwaiting')->willReturn(true);
        $transMock->method('lock')->will(
            $this->throwException($newLockException)
        );

        $comMock = $this->createMock(ROS_NAMESPACE . '\Communicator');
        $comMock->method('getTransmitter')->willReturn($transMock);
        
        try {
            Client::login($comMock, 'TEST', 'TEST');
            $this->fail(
                'Unexpected exceptions during login should be re-thrown'
            );
        } catch (T\LockException $e) {
            $this->assertSame($e, $newLockException);
        }
    }
    
    public function testEscapeString()
    {
        $this->assertSame('ab_12', Script::escapeString('ab_12'));
        $this->assertSame('ab_12', Script::escapeString('ab_12', false));
        $this->assertSame(
            '\\61\\62\\5F\\31\\32',
            Script::escapeString('ab_12', true)
        );

        $this->assertSame('ab_12яг', Script::escapeString('ab_12яг'));
        $this->assertSame('ab_12яг', Script::escapeString('ab_12яг', false));
        $this->assertSame(
            '\\61\\62\\5F\\31\\32\\D1\\8F\\D0\\B3',
            Script::escapeString('ab_12яг', true)
        );

        $this->assertSame(
            'ab_12яг\\3F\\3A\\22\\5C\\2B',
            Script::escapeString('ab_12яг?:"\\+')
        );
        $this->assertSame(
            'ab_12яг\\3F\\3A\\22\\5C\\2B',
            Script::escapeString('ab_12яг?:"\\+', false)
        );
        $this->assertSame(
            '\\61\\62\\5F\\31\\32\\D1\\8F\\D0\\B3\\3F\\3A\\22\\5C\\2B',
            Script::escapeString('ab_12яг?:"\\+', true)
        );
    }

    public function testPrepareScript()
    {
        $msg = 'testing';
        $result = Script::prepare(
            '/log print $msg',
            array(
                'msg' => $msg,
                $msg
            )
        );
        $this->assertSame(
            ":local \"msg\" \"{$msg}\";\n:local \"{$msg}\";\n/log print \$msg",
            stream_get_contents($result)
        );

        $testParam = fopen('php://temp', 'r+b');
        fwrite($testParam, $msg);
        rewind($testParam);
        $result = Script::prepare(
            '/log print $msg',
            array('msg' => $testParam)
        );
        $this->assertSame(
            ':local "msg" "' .
            Script::escapeString($msg, true) .
            "\";\n/log print \$msg",
            stream_get_contents($result)
        );
        $this->assertSame(strlen($msg), ftell($testParam));
    }

    /**
     * @param string $value
     * @param mixed  $expected
     *
     * @return void
     *
     * @dataProvider providerScriptParseValue
     */
    public function testScriptParseValue($value, $expected)
    {
        $actual = Script::parseValue($value);
        $this->assertEquals($expected, $actual);
        $this->assertInternalType(strtolower(gettype($expected)), $actual);
    }

    public function providerScriptParseValue()
    {
        return array(
            //// This will be moved into a separate "legacy" test,
            //// once PHP supports fractional secons in DateInterval...
            //'0s1ms2us3ns' => array(
            //    '0s1ms2us3ns',
            //    new DateInterval('PT0S')
            //),
            //// ...and this will be moved to a separate "current" test.
            //'0s1ms2us3ns' => array(
            //    '0s1ms2us3ns',
            //    new DateInterval('PT0.001002003S')
            //),
            ''                      => array('', null),
            '[]'                    => array('[]', null),
            '1'                     => array('1', 1),
            'true'                  => array('true', true),
            'yes'                   => array('yes', true),
            'false'                 => array('false', false),
            'no'                    => array('no', false),
            '"test"'                => array('"test"', 'test'),
            'test'                  => array('test', 'test'),
            '0:'                    => array('0:', new DateInterval('PT0H')),
            '1:'                    => array('1:', new DateInterval('PT1H')),
            '00:00'                 => array(
                '00:00',
                new DateInterval('PT0M0S')
            ),
            '00:01'                 => array(
                '00:01',
                new DateInterval('PT0M1S')
            ),
            '00:1'                  => array(
                '00:1',
                new DateInterval('PT0M1S')
            ),
            '1:1'                   => array(
                '1:1',
                new DateInterval('PT1M1S')
            ),
            '00:00:00'              => array(
                '00:00:00',
                new DateInterval('PT0H0M0S')
            ),
            '01:02:03'              => array(
                '01:02:03',
                new DateInterval('PT1H2M3S')
            ),
            '1:2:3'                 => array(
                '1:2:3',
                new DateInterval('PT1H2M3S')
            ),
            '1d00:00:00'            => array(
                '1d00:00:00',
                new DateInterval('P1DT0H0M0S')
            ),
            '1w00:00:00'            => array(
                '1w00:00:00',
                new DateInterval('P7DT0H0M0S')
            ),
            '1w0d00:00:00'          => array(
                '1w0d00:00:00',
                new DateInterval('P7DT0H0M0S')
            ),
            '1w1d00:00:00'          => array(
                '1w1d00:00:00',
                new DateInterval('P8DT0H0M0S')
            ),
            '0s'                    => array('0s', new DateInterval('PT0S')),
            '1s'                    => array('1s', new DateInterval('PT1S')),
            '0m'                    => array('0m', new DateInterval('PT0M')),
            '1m'                    => array('1m', new DateInterval('PT1M')),
            '0h'                    => array('0h', new DateInterval('PT0H')),
            '1h'                    => array('1h', new DateInterval('PT1H')),
            '1m2s'                  => array(
                '1m2s',
                new DateInterval('PT1M2S')
            ),
            '1h2m3s'                => array(
                '1h2m3s',
                new DateInterval('PT1H2M3S')
            ),
            '1d2h3m4s'              => array(
                '1d2h3m4s',
                new DateInterval('P1DT2H3M4S')
            ),
            '1w2s'                  => array(
                '1w2s',
                new DateInterval('P7DT2S')
            ),
            '1w2m3s'                => array(
                '1w2m3s',
                new DateInterval('P7DT2M3S')
            ),
            '1w2h3m4s'              => array(
                '1w2h3m4s',
                new DateInterval('P7DT2H3M4S')
            ),
            '1w2d3h4m5s'            => array(
                '1w2d3h4m5s',
                new DateInterval('P9DT3H4M5S')
            ),
            'Dec/21/2012'           => array(
                'Dec/21/2012',
                new DateTime('2012-12-21 00:00:00', new DateTimeZone('UTC'))
            ),
            'Dec/21/2012 12:34:56'  => array(
                'Dec/21/2012 12:34:56',
                new DateTime('2012-12-21 12:34:56', new DateTimeZone('UTC'))
            ),
            'Dec/99/9999 99:99:99'  => array(
                'Dec/99/9999 99:99:99',
                'Dec/99/9999 99:99:99'
            ),
            '{}'                    => array('{}', array()),
            '{a}'                   => array('{a}', array('a')),
            '{1;2}'                 => array('{1;2}', array(1, 2)),
            '{a;b}'                 => array('{a;b}', array('a', 'b')),
            '{"a";"b"}'             => array('{"a";"b"}', array('a', 'b')),
            '{"a;b";c}'             => array('{"a;b";c}', array('a;b', 'c')),
            '{a;"b;c"}'             => array('{a;"b;c"}', array('a', 'b;c')),
            '{"a;b";c;d}'           => array(
                '{"a;b";c;d}',
                array('a;b', 'c', 'd')
            ),
            '{a;"b;c";d}'           => array(
                '{a;"b;c";d}',
                array('a', 'b;c', 'd')
            ),
            '{a;b;"c;d"}'           => array(
                '{a;b;"c;d"}',
                array('a', 'b', 'c;d')
            ),
            '{{a;b};c}'             => array(
                '{{a;b};c}',
                array(array('a', 'b'), 'c')
            ),
            '{a;{b;c};d}'           => array(
                '{a;{b;c};d}',
                array('a', array('b', 'c'), 'd')
            ),
            '{a;b;{c;d}}'           => array(
                '{a;b;{c;d}}',
                array('a', 'b', array('c', 'd'))
            ),
            '{{a;{b;c}};d}'         => array(
                '{{a;{b;c}};d}',
                array(array('a', array('b', 'c')), 'd')
            ),
            '{a=1;b=2}'             => array(
                '{a=1;b=2}',
                array('a' => 1, 'b' => 2)
            ),
            '{a="test1";b="test2"}' => array(
                '{a="test1";b="test2"}',
                array('a' => 'test1', 'b' => 'test2')
            ),
            '{a=1;b;c=2}'           => array(
                '{a=1;b;c=2}',
                array('a' => 1, 'b', 'c' => 2)
            ),
            '{a="b;c";d=2}'         => array(
                '{a="b;c";d=2}',
                array('a' => 'b;c', 'd' => 2)
            ),
            '{a="b;c=2";d=2}'       => array(
                '{a="b;c=2";d=2}',
                array('a' => 'b;c=2', 'd' => 2)
            ),
            '{a="b";c}'             => array(
                '{a="b";c}',
                array('a' => 'b', 'c')
            ),
            '{1="test"}'            => array('{1="test"}', array(1 => 'test')),
            '{a'                    => array('{a', '{a')
        );
    }
}
