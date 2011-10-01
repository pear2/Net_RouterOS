<?php
namespace PEAR2\Net\RouterOS;

class StateAlteringFeaturesTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var Client
     */
    protected $object;
    
    protected function setUp()
    {
        $this->object = new Client(HOSTNAME, USERNAME, PASSWORD, PORT);
    }
    
    protected function tearDown()
    {
        unset($this->object);
    }

    public function testMultipleDifferentPersistentConnection()
    {
        try {

            $routerOS1 = new Client(HOSTNAME, USERNAME2, PASSWORD2, PORT, true);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS1,
                'Object initialization failed.'
            );

            $routerOS2 = new Client(HOSTNAME, USERNAME, PASSWORD, PORT, true);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\Client', $routerOS2,
                'Object initialization failed.'
            );


            $addRequest = new Request('/queue/simple/add');
            $addRequest->setArgument('name', TEST_QUEUE_NAME);
            $responses = $routerOS2->sendSync($addRequest);
            $this->assertEquals(
                1, count($responses), 'There should be only one response.'
            );
            if (count($responses) === 1
                && $responses->getType() === Response::TYPE_FINAL
            ) {
                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $responses = $routerOS2->sendSync($removeRequest);
                $this->assertInstanceOf(
                    __NAMESPACE__ . '\ResponseCollection', $responses,
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
        $addRequest->setArgument('name', TEST_QUEUE_NAME);
        $responses = $this->object->sendSync($addRequest);
        $this->assertEquals(
            1, count($responses), 'There should be only one response.'
        );
        if (count($responses) === 1
            && $responses->getLast()->getType() === Response::TYPE_FINAL
        ) {
            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $responses = $this->object->sendSync($removeRequest);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\ResponseCollection', $responses,
                'Responses should be a collection.'
            );
            $this->assertEquals(
                1, count($responses),
                'Response should be one.'
            );
            unset($responses[0]);
            $this->assertEquals(
                1, count($responses),
                'Response should be one, even after attempted unsetting.'
            );
            $responses[] = 'my attachment';
            $this->assertEquals(
                1, count($responses),
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
        $addRequest->setArgument('name', TEST_QUEUE_NAME);
        $addRequest->setArgument('comment', $comment);
        $responses = $this->object->sendSync($addRequest);
        $this->assertEquals(
            1, count($responses), 'There should be only one response.'
        );
        if (count($responses) === 1
            && $responses->getLast()->getType() === Response::TYPE_FINAL
        ) {

            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $responses = $this->object->sendSync($removeRequest);
            $this->assertInstanceOf(
                __NAMESPACE__ . '\ResponseCollection', $responses,
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
            * (int) $systemResource[0]->getArgument('free-memory');

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
                $comment, str_pad('t', 0x4000 - strlen('=comment=') + 1, 't')
            );
            rewind($comment);

            $addRequest = new Request($addCommand);
            $addRequest->setArgument('name', TEST_QUEUE_NAME);
            $addRequest->setArgument('comment', $comment);
            $responses = $this->object->sendSync($addRequest);
            $this->assertEquals(
                1, count($responses), 'There should be only one response.'
            );
            if (count($responses) === 1
                && $responses->getLast()->getType() === Response::TYPE_FINAL
            ) {

                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $responses = $this->object->sendSync($removeRequest);
                $this->assertInstanceOf(
                    __NAMESPACE__ . '\ResponseCollection', $responses,
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
            * (int) $systemResource[0]->getArgument('free-memory');

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
                $comment, str_pad('t', 0x200000 - strlen('=comment=') + 1, 't')
            );
            rewind($comment);

            $addRequest = new Request($addCommand);
            $addRequest->setArgument('name', TEST_QUEUE_NAME);
            $addRequest->setArgument('comment', $comment);
            $responses = $this->object->sendSync($addRequest);
            $this->assertEquals(
                1, count($responses), 'There should be only one response.'
            );
            if (count($responses) === 1
                && $responses->getLast()->getType() === Response::TYPE_FINAL
            ) {

                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $responses = $this->object->sendSync($removeRequest);
                $this->assertInstanceOf(
                    __NAMESPACE__ . '\ResponseCollection', $responses,
                    'Response should be one.'
                );
            }
        }
    }

    public function testSendSyncReturningResponseLargeDataException()
    {
        //Required for this test
        $memoryLimit = ini_set('memory_limit', -1);
        try {

            $comment = fopen('php://temp', 'r+b');
            fwrite($comment, str_pad('t', 0xFFFFFF, 't'));
            for ($i = 0; $i < 14; $i++) {
                fwrite($comment, str_pad('t', 0xFFFFFF, 't'));
            }
            fwrite(
                $comment,
                str_pad('t', 0xFFFFFF + 0xF/* - strlen('=comment=') */, 't')
            );
            rewind($comment);

            $commentString = stream_get_contents($comment);
            $maxArgL = 0xFFFFFFF - strlen('=comment=');
            $this->assertGreaterThan(
                $maxArgL, strlen($commentString), '$comment is not long enough.'
            );
            $addRequest = new Request('/queue/simple/add');
            $addRequest->setArgument('name', TEST_QUEUE_NAME);
            $addRequest->setArgument('comment', $commentString);
            $responses = $this->object->sendSync($addRequest);
            if (count($responses) === 1
                && $responses->getLast()->getType() === Response::TYPE_FINAL
            ) {
                $removeRequest = new Request('/queue/simple/remove');
                $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
                $response = $this->object->sendSync($removeRequest);
            }

            $this->fail('Lengths above 0xFFFFFFF should not be supported.');
        } catch (LengthException $e) {
            $this->assertEquals(
                10, $e->getCode(), 'Improper exception thrown.'
            );
        }

        //Clearing out for other tests.
        ini_set('memory_limit', $memoryLimit);
    }
    
    public function testResponseCollectionGetArgumentMap()
    {
        $addRequest = new Request('/queue/simple/add');
        $addRequest->setArgument('name', TEST_QUEUE_NAME);
        $responses = $this->object->sendSync($addRequest);
        if (count($responses) === 1
            && $responses->getLast()->getType() === Response::TYPE_FINAL
        ) {
            $printRequest = new Request('/queue/simple/print');
            $printRequest->setArgument('.proplist', 'name,target-addresses');
            $printRequest->setQuery(
                Query::where('name', TEST_QUEUE_NAME)
                ->orWhere('target-addresses', HOSTNAME_INVALID . '/32')
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertEquals(
                array('name' => array(0, 1), 'target-addresses' => array(0)),
                $responses->getArgumentMap(),
                'Improper format of the returned array'
            );
            
            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $response = $this->object->sendSync($removeRequest);
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
        $addRequest->setArgument('name', TEST_QUEUE_NAME);
        $addRequest->setArgument('comment', 'ПРИМЕР');
        $responses = $this->object->sendSync($addRequest);
        $this->assertEquals(
            1, count($responses), 'There should be only one response.'
        );
        if (count($responses) === 1
            && $responses->getLast()->getType() === Response::TYPE_FINAL
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
                TEST_QUEUE_NAME, $responses[0]->getArgument('name')
            );
            $this->assertNotEquals(
                'ПРИМЕР', $responses[0]->getArgument('comment')
            );
            
            $this->object->setCharset($appropriateCharsets);
            $this->assertNotEquals(
                'ПРИМЕР', $responses[0]->getArgument('comment')
            );
            
            $responses = $this->object->sendSync($printRequest);
            
            $this->assertEquals(
                TEST_QUEUE_NAME, $responses[0]->getArgument('name')
            );
            $this->assertEquals(
                'ПРИМЕР', $responses[0]->getArgument('comment')
            );
            
            $this->object->setCharset(
                'ISO-8859-1', Communicator::CHARSET_REMOTE
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР', $responses[0]->getArgument('comment')
            );
            
            $this->object->setCharset(
                'ISO-8859-1', Communicator::CHARSET_LOCAL
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР', $responses[0]->getArgument('comment')
            );
            
            $this->object->setCharset($appropriateCharsets);
            $responses = $this->object->sendSync($printRequest);
            $this->assertEquals(
                'ПРИМЕР', $responses[0]->getArgument('comment')
            );
            
            $this->object->setStreamResponses(true);
            $responses = $this->object->sendSync($printRequest);
            $this->assertEquals(
                'ПРИМЕР', stream_get_contents(
                    $responses[0]->getArgument('comment')
                )
            );
            $this->object->setCharset(
                'ISO-8859-1', Communicator::CHARSET_REMOTE
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР', stream_get_contents(
                    $responses[0]->getArgument('comment')
                )
            );
            $this->object->setCharset('windows-1251');
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР', stream_get_contents(
                    $responses[0]->getArgument('comment')
                )
            );
            
            $testQueueNameAsStream = fopen('php://temp', 'r+b');
            fwrite($testQueueNameAsStream, TEST_QUEUE_NAME);
            rewind($testQueueNameAsStream);
            $printRequest->setQuery(
                Query::where('name', $testQueueNameAsStream)
            );
            $responses = $this->object->sendSync($printRequest);
            $this->assertNotEquals(
                'ПРИМЕР', stream_get_contents(
                    $responses[0]->getArgument('comment')
                )
            );
            $this->object->setCharset($appropriateCharsets);
            $responses = $this->object->sendSync($printRequest);
            $this->assertEquals(
                'ПРИМЕР', stream_get_contents(
                    $responses[0]->getArgument('comment')
                )
            );
            
            
            $removeRequest = new Request('/queue/simple/remove');
            $removeRequest->setArgument('numbers', TEST_QUEUE_NAME);
            $responses = $this->object->sendSync($removeRequest);
        }
    }

}