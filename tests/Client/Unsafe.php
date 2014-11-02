<?php

namespace PEAR2\Net\RouterOS\Client\Test;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\Exception;
use PEAR2\Net\RouterOS\LengthException;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\SocketException;
use PHPUnit_Framework_TestCase;

abstract class Unsafe extends PHPUnit_Framework_TestCase
{

    /**
     * @var Client
     */
    protected $object;
    
    /**
     * Runs the test in a separate process for the sake of
     * peristent connections.
     * 
     * @runInSeparateProcess
     * 
     * @return void
     */
    public function testSystemReboot()
    {
        $this->object->sendSync(new Request('/system/reboot'));
        $this->object->close();
        $this->object = null;
        sleep(1);
        while (true) {
            try {
                $this->object = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT);
                $this->assertTrue(true);
                return;
            } catch (SocketException $e) {
                //Connection is expected to fail several times before success.
            }
        }
    }

    public function testMultipleDifferentPersistentConnection()
    {
        try {

            $routerOS1 = new Client(
                \HOSTNAME,
                USERNAME2,
                PASSWORD2,
                PORT,
                true
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
                true
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

    public function testSendSyncReturningCollectionWithOneResponse()
    {
        $addRequest = new Request('/queue/simple/add');
        $addRequest->setArgument('name', TEST_QUEUE_NAME)
            ->setArgument('target', '0.0.0.0/0');
        $responses = $this->object->sendSync($addRequest);
        $this->assertEquals(
            1,
            count($responses),
            'There should be only one response.'
        );
        if (count($responses) === 1
            && $responses[-1]->getType() === Response::TYPE_FINAL
        ) {
            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $responses = $this->object->sendSync($removeRequest);
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\ResponseCollection',
                $responses,
                'Responses should be a collection.'
            );
            $this->assertEquals(
                1,
                count($responses),
                'Response should be one.'
            );
            unset($responses[0]);
            $this->assertEquals(
                1,
                count($responses),
                'Response should be one, even after attempted unsetting.'
            );
            $responses[] = 'my attachment';
            $this->assertEquals(
                1,
                count($responses),
                'Response should be one, even after attempted setting.'
            );
        }
    }

    public function testSendSyncReturningResponseStreamData()
    {

        $comment = fopen('php://temp', 'r+b');
        fwrite($comment, str_pad('t', 0xFFF, 't'));
        rewind($comment);

        $addRequest = new Request('/queue/simple/add');
        $addRequest->setArgument('name', TEST_QUEUE_NAME)
            ->setArgument('target', '0.0.0.0/0');
        $addRequest->setArgument('comment', $comment);
        $responses = $this->object->sendSync($addRequest);
        $this->assertEquals(
            1,
            count($responses),
            'There should be only one response.'
        );
        if (count($responses) === 1
            && $responses[-1]->getType() === Response::TYPE_FINAL
        ) {

            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $responses = $this->object->sendSync($removeRequest);
            $this->assertInstanceOf(
                ROS_NAMESPACE . '\ResponseCollection',
                $responses,
                'Response should be one.'
            );
        }
    }

    public function testSendSyncReturningResponseLarge3bytesLength()
    {
        $this->markTestIncomplete(
            'For some reason, my RouterOS v5.6 doesn not work with this (bug?).'
        );
        $systemResource = $this->object->sendSync(
            new Request('/system/resource/print')
        );
        $this->assertEquals(2, count($systemResource));
        $freeMemory = 1024
            * (int) $systemResource[0]->getProperty('free-memory');

        $addCommand = '/queue/simple/add';
        $requiredMemory = 0x4000
            + strlen($addCommand) + 1
            + strlen('=name=') + strlen(TEST_QUEUE_NAME) + 1
            + strlen('=comment=') + 1
            + (8 * 1024 * 1024) /* 8MiB for processing's sake */;
        if ($freeMemory < $requiredMemory) {
            $this->markTestSkipped('Not enough memory on router.');
        } else {
            $comment = fopen('php://temp', 'r+b');
            fwrite(
                $comment,
                str_pad('t', 0x4000 - strlen('=comment=') + 1, 't')
            );
            rewind($comment);

            $addRequest = new Request($addCommand);
            $addRequest->setArgument('name', TEST_QUEUE_NAME)
                ->setArgument('target', '0.0.0.0/0');
            $addRequest->setArgument('comment', $comment);
            $responses = $this->object->sendSync($addRequest);
            $this->assertEquals(
                1,
                count($responses),
                'There should be only one response.'
            );
            if (count($responses) === 1
                && $responses[-1]->getType() === Response::TYPE_FINAL
            ) {

                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $responses = $this->object->sendSync($removeRequest);
                $this->assertInstanceOf(
                    ROS_NAMESPACE . '\ResponseCollection',
                    $responses,
                    'Response should be one.'
                );
            }
        }
    }

    public function testSendSyncReturningResponseLarge4bytesLength()
    {
        $this->markTestIncomplete(
            'For some reason, my RouterOS v5.6 doesn not work with this (bug?).'
        );
        $systemResource = $this->object->sendSync(
            new Request('/system/resource/print')
        );
        $this->assertEquals(2, count($systemResource));
        $freeMemory = 1024
            * (int) $systemResource[0]->getProperty('free-memory');

        $addCommand = '/queue/simple/add';
        $requiredMemory = 0x200000
            + strlen($addCommand) + 1
            + strlen('=name=') + strlen(TEST_QUEUE_NAME) + 1
            + strlen('=comment=') + 1
            + (8 * 1024 * 1024) /* 8MiB for processing's sake */;
        if ($freeMemory < $requiredMemory) {
            $this->markTestSkipped('Not enough memory on router.');
        } else {
            $comment = fopen('php://temp', 'r+b');
            fwrite(
                $comment,
                str_pad('t', 0x200000 - strlen('=comment=') + 1, 't')
            );
            rewind($comment);

            $addRequest = new Request($addCommand);
            $addRequest->setArgument('name', TEST_QUEUE_NAME)
                ->setArgument('target', '0.0.0.0/0');
            $addRequest->setArgument('comment', $comment);
            $responses = $this->object->sendSync($addRequest);
            $this->assertEquals(
                1,
                count($responses),
                'There should be only one response.'
            );
            if (count($responses) === 1
                && $responses[-1]->getType() === Response::TYPE_FINAL
            ) {

                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $responses = $this->object->sendSync($removeRequest);
                $this->assertInstanceOf(
                    ROS_NAMESPACE . '\ResponseCollection',
                    $responses,
                    'Response should be one.'
                );
            }
        }
    }

    public function testSendSyncReturningResponseLargeDataException()
    {
        $this->markTestIncomplete(
            'TODO: A known issue; Requests with excessively long words "leak".'
        );
        //Required for this test
        $memoryLimit = ini_set('memory_limit', -1);
        try {

            $comment = fopen('php://temp', 'r+b');
            fwrite($comment, str_repeat('t', 0xFFFFFF));
            for ($i = 0; $i < 14; $i++) {
                fwrite($comment, str_repeat('t', 0xFFFFFF));
            }
            fwrite(
                $comment,
                str_repeat('t', 0xFFFFFF + 0xF/* - strlen('=comment=') */)
            );
            rewind($comment);

            $commentString = stream_get_contents($comment);
            $maxArgL = 0xFFFFFFF - strlen('=comment=');
            $this->assertGreaterThan(
                $maxArgL,
                strlen($commentString),
                '$comment is not long enough.'
            );
            unset($commentString);
            rewind($comment);
            $addRequest = new Request('/queue/simple/add');
            $addRequest->setArgument('name', TEST_QUEUE_NAME)
                ->setArgument('target', '0.0.0.0/0');
            $addRequest->setArgument('comment', $comment);
            $responses = $this->object->sendSync($addRequest);
            if (count($responses) === 1
                && $responses[-1]->getType() === Response::TYPE_FINAL
            ) {
                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $response = $this->object->sendSync($removeRequest);
            }
            
            //Clearing out for other tests.
            ini_set('memory_limit', $memoryLimit);
            $this->fail('Lengths above 0xFFFFFFF should not be supported.');
        } catch (LengthException $e) {
            $this->assertEquals(
                LengthException::CODE_UNSUPPORTED,
                $e->getCode(),
                'Improper exception thrown.'
            );
        }

        //Clearing out for other tests.
        ini_set('memory_limit', $memoryLimit);
    }

    public function testResponseCollectionGetArgumentMap()
    {
        $addRequest = new Request('/queue/simple/add');
        $addRequest->setArgument('name', TEST_QUEUE_NAME)
            ->setArgument('target', '0.0.0.0/0')
            ->setArgument('comment', 'API_TEST');
        $responses = $this->object->sendSync($addRequest);
        if (count($responses) === 1
            && $responses[-1]->getType() === Response::TYPE_FINAL
        ) {
            $printRequest = new Request('/queue/simple/print');
            $printRequest->setArgument('.proplist', 'name,comment');
            $printRequest->setQuery(
                Query::where('name', TEST_QUEUE_NAME)
                ->orWhere('target', HOSTNAME_INVALID . '/32')
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertEquals(
                array('name' => array(0, 1), 'comment' => array(1)),
                $responses->getPropertyMap(),
                'Improper format of the returned array'
            );
            
            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $response = $this->object->sendSync($removeRequest);
        } else {
            $this->fail('Failed to add test queue.');
        }
    }

    public function testResponseCollectionIndex()
    {
        $queueComment = 'API_TEST';
        $addRequest = new Request('/queue/simple/add');
        $addRequest->setArgument('name', TEST_QUEUE_NAME)
            ->setArgument('target', '0.0.0.0/0')
            ->setArgument('comment', $queueComment);
        $responses = $this->object->sendSync($addRequest);
        if (count($responses) === 1
            && $responses[-1]->getType() === Response::TYPE_FINAL
        ) {
            $printRequest = new Request('/queue/simple/print');
            $printRequest->setArgument('.proplist', 'name,comment');
            $responses = $this->object->sendSync($printRequest)
                ->setIndex('name');
            $this->assertSame('name', $responses->getIndex());

            $this->assertSame(
                $queueComment,
                $responses[TEST_QUEUE_NAME]('comment')
            );
            $this->assertSame(
                count($responses->toArray(true)),
                count($responses->toArray(false)) - 1//!done
            );

            $this->assertNotSame(
                $responses->current(),
                $responses->seek(TEST_QUEUE_NAME)
            );
            $this->assertSame(
                $responses->current(),
                $responses[TEST_QUEUE_NAME]
            );

            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $response = $this->object->sendSync($removeRequest);
        } else {
            $this->fail('Failed to add test queue.');
        }
    }

    public function testSetCharset()
    {
        if (!extension_loaded('iconv') || !function_exists('iconv')) {
            $this->markTestSkipped('iconv is not enabled.');
        }
        $this->assertEquals(
            array(
                Communicator::CHARSET_REMOTE => null,
                Communicator::CHARSET_LOCAL  => null
            ),
            $this->object->setCharset(
                array(
                    Communicator::CHARSET_REMOTE => 'windows-1251',
                    Communicator::CHARSET_LOCAL  => 'UTF-8'
                )
            )
        );

        $addRequest = new Request('/queue/simple/add');
        $addRequest->setArgument('name', TEST_QUEUE_NAME)
            ->setArgument('target', '0.0.0.0/0');
        $addRequest->setArgument('comment', 'ПРИМЕР');
        $responses = $this->object->sendSync($addRequest);
        $this->assertEquals(
            1,
            count($responses),
            'There should be only one response.'
        );
        if (count($responses) === 1
            && $responses[-1]->getType() === Response::TYPE_FINAL
        ) {
            $appropriateCharsets = $this->object->setCharset(
                array(
                    Communicator::CHARSET_REMOTE  => 'ISO-8859-1',
                    Communicator::CHARSET_LOCAL => 'UTF-8'
                )
            );
            $printRequest = new Request('/queue/simple/print');
            $printRequest->setQuery(Query::where('name', TEST_QUEUE_NAME));
            $responses = $this->object->sendSync($printRequest);

            $this->assertEquals(
                TEST_QUEUE_NAME,
                $responses[0]->getProperty('name')
            );
            $this->assertNotEquals(
                'ПРИМЕР',
                $responses[0]->getProperty('comment')
            );

            $this->object->setCharset($appropriateCharsets);
            $this->assertNotEquals(
                'ПРИМЕР',
                $responses[0]->getProperty('comment')
            );

            $responses = $this->object->sendSync($printRequest);

            $this->assertEquals(
                TEST_QUEUE_NAME,
                $responses[0]->getProperty('name')
            );
            $this->assertEquals(
                'ПРИМЕР',
                $responses[0]->getProperty('comment')
            );

            $this->object->setCharset(
                'ISO-8859-1',
                Communicator::CHARSET_REMOTE
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР',
                $responses[0]->getProperty('comment')
            );

            $this->object->setCharset(
                'ISO-8859-1',
                Communicator::CHARSET_LOCAL
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР',
                $responses[0]->getProperty('comment')
            );

            $this->object->setCharset($appropriateCharsets);
            $responses = $this->object->sendSync($printRequest);
            $this->assertEquals(
                'ПРИМЕР',
                $responses[0]->getProperty('comment')
            );

            $this->object->setStreamingResponses(true);
            $responses = $this->object->sendSync($printRequest);
            $this->assertEquals(
                'ПРИМЕР',
                stream_get_contents(
                    $responses[0]->getProperty('comment')
                )
            );
            $this->object->setCharset(
                'ISO-8859-1',
                Communicator::CHARSET_REMOTE
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР',
                stream_get_contents(
                    $responses[0]->getProperty('comment')
                )
            );
            $this->object->setCharset('windows-1251');
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР',
                stream_get_contents(
                    $responses[0]->getProperty('comment')
                )
            );

            $testQueueNameStream = fopen('php://temp', 'r+b');
            fwrite($testQueueNameStream, TEST_QUEUE_NAME);
            rewind($testQueueNameStream);
            $printRequest->setQuery(
                Query::where('name', $testQueueNameStream)
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР',
                stream_get_contents(
                    $responses[0]->getProperty('comment')
                )
            );
            $this->object->setCharset($appropriateCharsets);
            $responses = $this->object->sendSync($printRequest);
            $this->assertEquals(
                'ПРИМЕР',
                stream_get_contents(
                    $responses[0]->getProperty('comment')
                )
            );

            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $responses = $this->object->sendSync($removeRequest);
        }
    }
}
