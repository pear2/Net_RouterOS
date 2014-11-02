<?php

namespace PEAR2\Net\RouterOS\Client\Test;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\DataFlowException;
use PEAR2\Net\RouterOS\LengthException;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\UnexpectedValueException;
use PHPUnit_Framework_Assert;
use PHPUnit_Framework_TestCase;

abstract class Safe extends PHPUnit_Framework_TestCase
{

    /**
     * @var Client
     */
    protected $object;

    public function testSendSyncReturningCollection()
    {
        $list1 = $this->object->sendSync(new Request('/ip/arp/print'));
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list1,
            'The list is not a collection'
        );
        $this->assertEquals(
            $list1->getAllTagged(null)->toArray(),
            $list1->toArray(),
            "The collection should contain only responses without a tag."
        );
        $this->assertInternalType(
            'string',
            $list1[0]->getProperty('address'),
            'The address is not a string'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\Response',
            $list1->end(),
            'The list is empty'
        );
        $this->assertEquals(Response::TYPE_FINAL, $list1->current()->getType());
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\Response',
            $list1->prev(),
            'The list is empty'
        );
        $this->assertEquals(Response::TYPE_DATA, $list1->current()->getType());
        
        $list2 = $this->object->sendSync(
            new Request('/ip/arp/print', null, 't')
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list2,
            'The list is not a collection'
        );
        $this->assertEquals(
            $list2->getAllTagged('t')->toArray(),
            $list2->toArray(),
            "The collection should contain only responses with tag 't'"
        );
        $this->assertInternalType(
            'string',
            $list2[0]->getProperty('address'),
            'The address is not a string'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\Response',
            $list2->end(),
            'The list is empty'
        );
        $this->assertEquals(Response::TYPE_FINAL, $list2->current()->getType());
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\Response',
            $list2->prev(),
            'The list is empty'
        );
        $this->assertEquals(Response::TYPE_DATA, $list2->current()->getType());
        
        $this->assertEquals(
            count($list1),
            count($list2)
        );
    }

    public function testSendSyncReturningCollectionWithStreams()
    {
        $this->assertFalse($this->object->isStreamingResponses());
        $this->assertFalse($this->object->setStreamingResponses(true));
        $this->assertTrue($this->object->isStreamingResponses());
        $list = $this->object->sendSync(new Request('/ip/arp/print'));
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertInternalType(
            'resource',
            $list[0]->getProperty('address'),
            'The address is not a stream'
        );
    }

    public function testSendAsyncTagRequirement()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5');
        try {
            $this->object->sendAsync($ping);

            $this->fail('The call had to fail.');
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_TAG_REQUIRED,
                $e->getCode(),
                'Improper exception code.'
            );
        }
        try {
            $ping->setTag('');
            $this->object->sendAsync($ping);

            $this->fail('The call had to fail.');
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_TAG_REQUIRED,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testSendAsyncUniqueTagRequirement()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5')
            ->setTag('ping');
        $ping2 = new Request('/ping');
        $ping2->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5')
            ->setTag('ping');
        $this->object->sendAsync($ping);
        try {
            $this->object->sendAsync($ping2);

            $this->fail('The call had to fail.');
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_TAG_UNIQUE,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testSendAsyncValidCallbackRequirement()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5')
            ->setTag('ping');
        try {
            $this->object->sendAsync($ping, 3);

            $this->fail('The call had to fail.');
        } catch (UnexpectedValueException $e) {
            $this->assertEquals(
                UnexpectedValueException::CODE_CALLBACK_INVALID,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testSendAsyncWithCallbackAndTempLoop()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME)
            ->setArgument('interval', '0.5')
            ->setTag('ping');
        $repliesCount = 0;
        $this->object->sendAsync(
            $ping,
            function ($response, $client) use (&$repliesCount) {
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Response',
                    $response,
                    'A callback must receive a single response per call'
                );
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Client',
                    $client,
                    'A callback must receive a copy of the client object'
                );

                PHPUnit_Framework_TestCase::assertEquals(
                    'ping',
                    $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
            }
        );

        $this->object->loop(2);
        $this->assertGreaterThan(
            0,
            $repliesCount,
            "No responses for '" . HOSTNAME . "' in 2 seconds."
        );
    }

    public function testSendAsyncAndFullCancel()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5')
            ->setTag('ping1');
        $ping2 = new Request('/ping');
        $ping2->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5')
            ->setTag('ping2');
        $this->object->sendAsync($ping);
        $this->object->sendAsync($ping2);
        $this->object->loop(2);
        $this->object->cancelRequest();
        
        $ping1responses = $this->object->extractNewResponses('ping1');
        
        $ping1responses->end();
        $ping1responses->prev();
        $this->assertEquals(Response::TYPE_ERROR, $ping1responses->getType());
        
        $ping2responses = $this->object->extractNewResponses('ping2');
        $ping2responses->end();
        $ping2responses->prev();
        $this->assertEquals(Response::TYPE_ERROR, $ping2responses->getType());
    }

    public function testInvalidCancel()
    {
        $this->assertEquals(
            0,
            $this->object->getPendingRequestsCount(),
            'There should be no active requests.'
        );
        try {
            $this->object->cancelRequest('ping1');
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_CANCEL_FAIL,
                $e->getCode(),
                'Improper exception code.'
            );
        }
        $this->assertEquals(
            0,
            $this->object->getPendingRequestsCount(),
            'There should be no active requests.'
        );
    }

    public function testSendAsyncAndInvalidCancel()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5')
            ->setTag('ping1');
        $ping2 = new Request('/ping');
        $ping2->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5')
            ->setTag('ping2');
        $this->object->sendAsync($ping);
        $this->object->sendAsync($ping2);
        $this->assertEquals(
            2,
            $this->object->getPendingRequestsCount(),
            'Improper active request count before cancel test.'
        );
        $this->object->loop(2);
        try {
            $this->object->cancelRequest('ping3');
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_CANCEL_FAIL,
                $e->getCode(),
                'Improper exception code.'
            );
        }
        $this->assertEquals(
            2,
            $this->object->getPendingRequestsCount(),
            'Improper active request count after cancel test.'
        );
    }

    public function testSendAsyncAndFullExtract()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5')
            ->setTag('ping1');
        $ping2 = new Request('/ping');
        $ping2->setArgument('address', HOSTNAME_INVALID)
            ->setArgument('interval', '0.5')
            ->setTag('ping2');
        $this->object->sendAsync($ping);
        $this->object->sendAsync($ping2);
        $this->assertEquals(
            2,
            $this->object->getPendingRequestsCount(),
            'Improper pending request count before extraction test.'
        );
        $this->object->loop(2);
        $responses = $this->object->extractNewResponses();

        $this->assertEquals(
            2,
            $this->object->getPendingRequestsCount(),
            'Improper pending request count after extraction test.'
        );
        
        $this->assertGreaterThan(
            0,
            count($responses->getAllTagged('ping1')),
            "No responses for 'ping1' in 2 seconds."
        );
        $this->assertGreaterThan(
            0,
            count($responses->getAllTagged('ping2')),
            "No responses for 'ping2' in 2 seconds."
        );
    }

    public function testSendAsyncWithCallbackAndCancel()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME)
            ->setArgument('interval', '0.5')
            ->setTag('ping');
        $finalRepliesCount = -1;
        $responseCount = 0;
        $this->object->sendAsync(
            $ping,
            function ($response, $client) use (&$responseCount) {
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Response',
                    $response,
                    'A callback must receive a single response per call'
                );
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Client',
                    $client,
                    'A callback must receive a copy of the client object'
                );

                PHPUnit_Framework_TestCase::assertEquals(
                    'ping',
                    $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $responseCount++;
            }
        );

        $this->object->loop(2);
        $bufferedReplies = count($this->object->extractNewResponses('ping'));
        $this->assertEquals(
            0,
            $bufferedReplies,
            'Responses for requests with callbacks must not be buffered.'
        );
        $finalRepliesCount = $responseCount;
        $this->object->cancelRequest('ping');
        $this->object->loop(2);
        $this->assertGreaterThan(
            0,
            $responseCount,
            "No responses for '" . HOSTNAME . "' in 2 seconds."
        );
        $this->assertGreaterThanOrEqual(
            $finalRepliesCount + 2/* The !trap and !done */,
            $responseCount,
            "Insufficient callbacks during second loop."
        );
    }

    public function testSendAsyncWithCallbackAndCancelWithin()
    {
        $limit = 5;
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME)
            ->setArgument('interval', '0.5')
            ->setTag('ping');
        $repliesCount = 0;
        $this->object->sendAsync(
            $ping,
            function ($response, $client) use (&$repliesCount, $limit) {
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Response',
                    $response,
                    'A callback must receive a single response per call'
                );
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Client',
                    $client,
                    'A callback must receive a copy of the client object'
                );

                PHPUnit_Framework_TestCase::assertEquals(
                    'ping',
                    $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
                return $repliesCount === $limit;
            }
        );

        $this->object->loop();
        $this->assertEquals(
            $limit + 2/* The !trap and !done*/,
            $repliesCount,
            "Extra callbacks were executed during second loop."
        );
    }

    public function testSendAsyncWithCallbackAndFullLoop()
    {
        $arpPrint = new Request('/ip/arp/print');
        $arpPrint->setTag('arp');
        $repliesCount = 0;
        $arpCallback = function ($response, $client) use (&$repliesCount) {
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Response',
                    $response,
                    'A callback must receive a single response per call'
                );
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Client',
                    $client,
                    'A callback must receive a copy of the client object'
                );

                PHPUnit_Framework_TestCase::assertEquals(
                    'arp',
                    $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
        };
        $this->object->sendAsync($arpPrint, $arpCallback);

        $this->object->loop();

        $this->assertGreaterThan(0, $repliesCount, "No callbacks.");
        $repliesCount = 0;

        $this->object->sendAsync($arpPrint, $arpCallback);

        $this->object->loop();

        $this->assertGreaterThan(0, $repliesCount, "No callbacks.");
    }

    public function testSendAsyncAndCompleteRequest()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME)
            ->setArgument('interval', '0.5')
            ->setTag('ping');
        $repliesCount = 0;
        $this->object->sendAsync(
            $ping,
            function ($response, $client) use (&$repliesCount) {
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Response',
                    $response,
                    'A callback must receive a single response per call'
                );
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Client',
                    $client,
                    'A callback must receive a copy of the client object'
                );

                PHPUnit_Framework_TestCase::assertEquals(
                    'ping',
                    $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
            }
        );
        sleep(1);


        $arpPrint = new Request('/ip/arp/print');
        $arpPrint->setTag('arp');
        $this->object->sendAsync($arpPrint);
        $list = $this->object->completeRequest('arp');

        
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );

        $this->assertGreaterThan(
            0,
            $repliesCount,
            "No responses for '" . HOSTNAME . "' before of 'arp' is done."
        );
    }

    public function testSendAsyncAndCompleteRequestWithStream()
    {
        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME)
            ->setArgument('interval', '0.5')
            ->setTag('ping');
        $repliesCount = 0;
        $this->object->sendAsync(
            $ping,
            function ($response, $client) use (&$repliesCount) {
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Response',
                    $response,
                    'A callback must receive a single response per call'
                );
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Client',
                    $client,
                    'A callback must receive a copy of the client object'
                );

                PHPUnit_Framework_TestCase::assertEquals(
                    'ping',
                    $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $repliesCount++;
            }
        );
        sleep(1);


        $arpPrint = new Request('/ip/arp/print');
        $arpPrint->setTag('arp');
        $this->object->sendAsync($arpPrint);
        $this->assertFalse($this->object->isStreamingResponses());
        $this->assertFalse($this->object->setStreamingResponses(true));
        $this->assertTrue($this->object->isStreamingResponses());

        $list = $this->object->completeRequest('arp');

        
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );

        $this->assertGreaterThan(
            0,
            $repliesCount,
            "No responses for '" . HOSTNAME . "' before of 'arp' is done."
        );
        $this->assertInternalType(
            'resource',
            $list[0]->getProperty('address'),
            'The address is not a stream'
        );
    }

    public function testSendAsyncAndCompleteRequestWithCallback()
    {


        $arpPrint = new Request('/ip/arp/print');
        $arpPrint->setTag('arp');
        $list1 = $list2 = array();
        foreach ($this->object->sendSync($arpPrint) as $response) {
            $list1[(string) $response->getProperty('.id')] = $response;
        }
        ksort($list1);
        
        $this->object->sendAsync(
            $arpPrint,
            function ($response, $client) use (&$list2) {
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Response',
                    $response,
                    'A callback must receive a single response per call'
                );
                PHPUnit_Framework_TestCase::assertInstanceOf(
                    ROS_NAMESPACE . '\Client',
                    $client,
                    'A callback must receive a copy of the client object'
                );

                PHPUnit_Framework_TestCase::assertEquals(
                    'arp',
                    $response->getTag(),
                    'The callback must only receive responses meant for it.'
                );
                $list2[(string) $response->getProperty('.id')] = $response;
            }
        );

        $this->assertEmpty($this->object->completeRequest('arp')->toArray());
        ksort($list2);
        $this->assertEquals($list1, $list2);
    }

    public function testCompleteRequestEmptyQueue()
    {
        try {
            $this->object->completeRequest('invalid');

            $this->fail('No exception was thrown.');
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_UNKNOWN_REQUEST,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testCompleteRequestInvalid()
    {
        try {
            $arpPrint = new Request('/ip/arp/print');
            $arpPrint->setTag('arp');
            $this->object->sendAsync($arpPrint);
            $this->object->completeRequest('invalid');

            $this->fail('No exception was thrown.');
        } catch (DataFlowException $e) {
            $this->assertEquals(
                DataFlowException::CODE_UNKNOWN_REQUEST,
                $e->getCode(),
                'Improper exception code.'
            );
        }
    }

    public function testSendAsyncWithoutCallbackAndLoop()
    {
        $arpPrint = new Request('/ip/arp/print');
        $arpPrint->setTag('arp');
        $this->object->sendAsync($arpPrint);

        $this->object->loop();
        $list = $this->object->extractNewResponses('arp');
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertGreaterThan(0, count($list), 'No responses.');

        $ping = new Request('/ping');
        $ping->setArgument('address', HOSTNAME)
            ->setArgument('interval', '0.5')
            ->setTag('ping');
        $this->object->sendAsync($ping);

        $this->object->loop(2);
        $list = $this->object->extractNewResponses('ping');
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertGreaterThan(0, count($list), 'No responses.');
        $this->assertEquals(
            0,
            count($list->getAllOfType(Response::TYPE_FINAL)),
            'The command should not be finished yet.'
        );
        $this->assertEquals(
            count($list),
            count($list->getAllOfType(Response::TYPE_DATA)),
            'There should be only data responses.'
        );
        $this->object->cancelRequest('ping');
    }
    
    public function testListenOverTimeout()
    {
        $this->object->sendAsync(
            new Request('/queue simple listen', null, 'l'),
            function ($response) {
                PHPUnit_Framework_Assert::assertFalse($response);
            }
        );
        $this->assertSame(1, $this->object->getPendingRequestsCount());
        $this->assertTrue(
            $this->object->loop(ini_get('default_socket_timeout') + 3)
        );
        $this->assertSame(1, $this->object->getPendingRequestsCount());
        $this->assertSame(
            array(),
            $this->object->extractNewResponses('l')->toArray()
        );
    }
    
    public function testClientInvokability()
    {
        $obj = $this->object;
        $this->assertEquals(0, $obj->getPendingRequestsCount());
        $obj(new Request('/ping address=127.0.0.1 interval=1', null, 'ping1'));
        $this->assertEquals(1, $obj->getPendingRequestsCount());
        $obj(new Request('/ping address=::1 interval=1', null, 'ping2'));
        $this->assertEquals(2, $obj->getPendingRequestsCount());
        $obj(4);
        $ping1Responses = $obj->extractNewResponses('ping1');
        $this->assertGreaterThan(0, count($ping1Responses));
        $ping2Responses = $obj->extractNewResponses('ping2');
        $this->assertGreaterThan(0, count($ping2Responses));
        $obj->cancelRequest();

        $obj(new Request('/ip/arp/print', null, 'arp'));
        $arpResponses1 = $obj('arp');
        $this->assertEquals(0, $obj->getPendingRequestsCount());
        $this->assertGreaterThan(0, count($arpResponses1));
        $obj(new Request('/ip/arp/print', null, 'arp'));
        $this->assertEquals(1, $obj->getPendingRequestsCount());
        $obj();
        $this->assertEquals(0, $obj->getPendingRequestsCount());
        $arpResponses2 = $obj->extractNewResponses('arp');
        $this->assertEquals(0, $obj->getPendingRequestsCount());
        $this->assertGreaterThan(0, count($arpResponses2));
        
        $arpResponses3 = $obj(new Request('/ip/arp/print'));

        $this->assertEquals(count($arpResponses1), count($arpResponses2));
        $this->assertEquals(count($arpResponses2), count($arpResponses3));
        $this->assertInstanceOf(ROS_NAMESPACE . '\Response', $arpResponses1(0));
    }

    public function testStreamEquality()
    {
        $request = new Request('/queue/simple/print');

        $request->setQuery(
            Query::where('target', HOSTNAME_INVALID . '/32')
        );

        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );

        $this->object->setStreamingResponses(true);
        $streamList = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $streamList,
            'The list is not a collection'
        );

        foreach ($list as $index => $response) {
            $streamListArgs = $streamList[$index]->getIterator();
            foreach ($response as $argName => $value) {
                $this->assertArrayHasKey(
                    $argName,
                    $streamListArgs,
                    'Missing argument.'
                );
                $this->assertEquals(
                    $value,
                    stream_get_contents($streamListArgs[$argName]),
                    'Argument values are not equivalent.'
                );
                unset($streamListArgs[$argName]);
            }
            $this->assertEmpty($streamListArgs, 'Extra arguments.');
        }
    }

    public function testSendSyncWithQueryEquals()
    {
        $request = new Request('/queue/simple/print');

        $request->setQuery(
            Query::where('target', HOSTNAME_INVALID . '/32')
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            2,
            count($list),
            'The list should have only one item and a "done" reply.'
        );

        $request->setQuery(
            Query::where(
                'target',
                HOSTNAME_INVALID . '/32',
                Query::OP_EQ
            )
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            2,
            count($list),
            'The list should have only one item and a "done" reply.'
        );

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $request->setQuery(
            Query::where('target', $invalidAddressStream)
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            2,
            count($list),
            'The list should have only one item and a "done" reply.'
        );

        $request->setQuery(
            Query::where(
                'target',
                $invalidAddressStream,
                Query::OP_EQ
            )
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            2,
            count($list),
            'The list should have only one item and a "done" reply.'
        );
    }

    public function testSendSyncWithQueryEqualsNot()
    {
        $request = new Request('/queue/simple/print');
        $fullList = $this->object->sendSync($request);

        $request->setQuery(
            Query::where('target', HOSTNAME_INVALID . '/32')->not()
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            count($fullList) - 1,
            count($list),
            'The list was never filtered.'
        );

        $request->setQuery(
            Query::where(
                'target',
                HOSTNAME_INVALID . '/32',
                Query::OP_EQ
            )->not()
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            count($fullList) - 1,
            count($list),
            'The list was never filtered.'
        );

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $request->setQuery(
            Query::where('target', $invalidAddressStream)->not()
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            count($fullList) - 1,
            count($list),
            'The list was never filtered.'
        );

        $request->setQuery(
            Query::where(
                'target',
                $invalidAddressStream,
                Query::OP_EQ
            )->not()
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            count($fullList) - 1,
            count($list),
            'The list was never filtered.'
        );
    }

    public function testSendSyncWithQueryEnum()
    {
        $request = new Request('/queue/simple/print');
        $fullList = $this->object->sendSync($request);

        $request->setQuery(
            Query::where('target', HOSTNAME_SILENT . '/32')
            ->orWhere('target', HOSTNAME_INVALID . '/32')
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(3, count($list), 'The list was never filtered.');

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $silentAddressStream = fopen('php://temp', 'r+b');
        fwrite($silentAddressStream, HOSTNAME_SILENT . '/32');
        rewind($silentAddressStream);

        $request->setQuery(
            Query::where('target', $silentAddressStream)
            ->orWhere('target', $invalidAddressStream)
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(3, count($list), 'The list was never filtered.');
    }

    public function testSendSyncWithQueryEnumNot()
    {
        $request = new Request('/queue/simple/print');
        $fullList = $this->object->sendSync($request);

        $request->setQuery(
            Query::where('target', HOSTNAME_SILENT . '/32')
            ->orWhere('target', HOSTNAME_INVALID . '/32')
            ->not()
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            count($fullList) - 2,
            count($list),
            'The list was never filtered.'
        );

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $silentAddressStream = fopen('php://temp', 'r+b');
        fwrite($silentAddressStream, HOSTNAME_SILENT . '/32');
        rewind($silentAddressStream);

        $request->setQuery(
            Query::where('target', $silentAddressStream)
            ->orWhere('target', $invalidAddressStream)
            ->not()
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertEquals(
            count($fullList) - 2,
            count($list),
            'The list was never filtered.'
        );
    }

    public function testSendSyncWithQueryBetween()
    {
        $request = new Request('/ip/arp/print');
        $fullList = $this->object->sendSync($request);

        $request->setQuery(
            Query::where('address', HOSTNAME, Query::OP_GT)
            ->andWhere('address', HOSTNAME_INVALID, Query::OP_LT)
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertLessThan(
            count($fullList),
            count($list),
            'The list was never filtered.'
        );

        $invalidAddressStream = fopen('php://temp', 'r+b');
        fwrite($invalidAddressStream, HOSTNAME_INVALID . '/32');
        rewind($invalidAddressStream);

        $addressStream = fopen('php://temp', 'r+b');
        fwrite($addressStream, HOSTNAME . '/32');
        rewind($addressStream);

        $request->setQuery(
            Query::where('address', $addressStream, Query::OP_GT)
            ->andWhere(
                'address',
                $invalidAddressStream,
                Query::OP_LT
            )
        );
        $list = $this->object->sendSync($request);
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $fullList,
            'The list is not a collection'
        );
        $this->assertInstanceOf(
            ROS_NAMESPACE . '\ResponseCollection',
            $list,
            'The list is not a collection'
        );
        $this->assertLessThan(
            count($fullList),
            count($list),
            'The list was never filtered.'
        );
    }

    /**
     * @requires PHP 5.6
     * 
     * @return void
     */
    public function testResponseCollectionWordCount()
    {
        $this->assertEquals(
            3/*!re + .id*/+ 2/*!done*/,
            count(
                $this->object->sendSync(
                    new Request(
                        '/queue/simple/print .proplist=.id',
                        Query::where('target', HOSTNAME_SILENT . '/32')
                    )
                ),
                COUNT_RECURSIVE
            )
        );
    }

    public function testResponseCollectionWordCountCall()
    {
        $this->assertEquals(
            3/*!re + .id*/+ 2/*!done*/,
            $this->object->sendSync(
                new Request(
                    '/queue/simple/print .proplist=.id',
                    Query::where('target', HOSTNAME_SILENT . '/32')
                )
            )->count(COUNT_RECURSIVE)
        );
    }

    public function testResponseCollectionOrderBy()
    {
        $request = new Request('/queue/simple/print');
        $fullList = $this->object->sendSync($request)
            ->getAllOfType(Response::TYPE_DATA);

        $defaultFirst = $fullList[0]->getProperty('name');
        $defaultLast = $fullList[-1]->getProperty('name');

        $sortedByNameASC = $fullList->orderBy(array('name'));
        $sortedByNameASCFirst = $sortedByNameASC[0]->getProperty('name');
        $sortedByNameASCLast = $sortedByNameASC[-1]->getProperty('name');

        $this->assertNotSame($defaultFirst, $sortedByNameASCFirst);
        $this->assertNotSame($defaultLast, $sortedByNameASCLast);

        $sortedByNameASC = $fullList->orderBy(array('name' => null));
        $sortedByNameASCFirst = $sortedByNameASC[0]->getProperty('name');
        $sortedByNameASCLast = $sortedByNameASC[-1]->getProperty('name');

        $this->assertNotSame($defaultFirst, $sortedByNameASCFirst);
        $this->assertNotSame($defaultLast, $sortedByNameASCLast);

        $sortedByNameDESC = $fullList->orderBy(array('name' => SORT_DESC));
        $sortedByNameDESCFirst = $sortedByNameDESC[0]->getProperty('name');
        $sortedByNameDESCLast = $sortedByNameDESC[-1]->getProperty('name');

        $this->assertSame($sortedByNameDESCFirst, $sortedByNameASCLast);
        $this->assertSame($sortedByNameDESCLast, $sortedByNameASCFirst);

        $sortedByNameDESC = $fullList->orderBy(
            array('name' => array(SORT_DESC, SORT_REGULAR))
        );
        $sortedByNameDESCFirst = $sortedByNameDESC[0]->getProperty('name');
        $sortedByNameDESCLast = $sortedByNameDESC[-1]->getProperty('name');

        $this->assertSame($sortedByNameDESCFirst, $sortedByNameASCLast);
        $this->assertSame($sortedByNameDESCLast, $sortedByNameASCFirst);

        $sortedByMaxLimitAndName = $fullList->orderBy(
            array('max-limit', 'name')
        );
        $sortedByMaxLimitAndNameFirst = $sortedByMaxLimitAndName[0]
            ->getProperty('name');
        $sortedByMaxLimitAndNameLast = $sortedByMaxLimitAndName[-1]
            ->getProperty('name');

        $this->assertNotSame($defaultFirst, $sortedByMaxLimitAndNameFirst);
        $this->assertNotSame(
            $sortedByNameASCFirst,
            $sortedByMaxLimitAndNameFirst
        );

        $sortedByMaxLimit = $fullList->orderBy(
            array('max-limit')
        );
        $sortedByMaxLimitFirst = $sortedByMaxLimit[0]
            ->getProperty('name');
        $sortedByMaxLimitLast = $sortedByMaxLimit[-1]
            ->getProperty('name');

        $this->assertNotSame($defaultFirst, $sortedByMaxLimitFirst);
        $this->assertNotSame(
            $sortedByNameASCFirst,
            $sortedByMaxLimitFirst
        );

        $sortedByMaxLimitDownload = $fullList->orderBy(
            array('max-limit' => function ($a, $b) {
                list($uploadA, $downloadA) = explode('/', $a);
                list($uploadB, $downloadB) = explode('/', $b);
                return strcmp($downloadA, $downloadB);
            })
        );
        $sortedByMaxLimitDownloadFirst = $sortedByMaxLimitDownload[0]
            ->getProperty('name');
        $sortedByMaxLimitDownloadLast = $sortedByMaxLimitDownload[-1]
            ->getProperty('name');

        $this->assertNotSame($defaultFirst, $sortedByMaxLimitDownloadFirst);
        $this->assertNotSame(
            $sortedByNameASCFirst,
            $sortedByMaxLimitDownloadFirst
        );
        $this->assertNotSame(
            $sortedByMaxLimitFirst,
            $sortedByMaxLimitDownloadFirst
        );
    }

    public function testDefaultCharsets()
    {
        $this->assertNull(
            $this->object->getCharset(Communicator::CHARSET_REMOTE)
        );
        $this->assertNull(
            $this->object->getCharset(Communicator::CHARSET_LOCAL)
        );
        $this->assertEquals(
            array(
                Communicator::CHARSET_REMOTE => null,
                Communicator::CHARSET_LOCAL  => null
            ),
            $this->object->getCharset(Communicator::CHARSET_ALL)
        );
        $this->assertEquals(
            array(
                Communicator::CHARSET_REMOTE => null,
                Communicator::CHARSET_LOCAL  => null
            ),
            Communicator::getDefaultCharset(Communicator::CHARSET_ALL)
        );
        $this->assertNull(
            Communicator::getDefaultCharset(Communicator::CHARSET_REMOTE)
        );
        $this->assertNull(
            Communicator::getDefaultCharset(Communicator::CHARSET_LOCAL)
        );
    }
    
    public function testSendSyncReturningResponseLargeDataException()
    {
        //Required for this test
        $memoryLimit = ini_set('memory_limit', -1);
        try {

            $comment = fopen('php://temp', 'r+b');
            $fillerString = str_repeat('t', 0xFFFFFF);
            //fwrite($comment, $fillerString);
            for ($i = 0; $i < 256; $i++) {
                fwrite($comment, $fillerString);
            }
            unset($fillerString);
            fwrite(
                $comment,
                str_repeat('t', 0xFE/* - strlen('=comment=') */)
            );
            $argL = (double) sprintf('%u', ftell($comment));
            rewind($comment);

            $maxArgL = 0xFFFFFFFF - strlen('?comment=');
            $this->assertGreaterThan(
                $maxArgL,
                $argL,
                '$comment is not long enough.'
            );
            rewind($comment);
            $printRequest = new Request('/ip/arp/print');
            $printRequest->setQuery(Query::where('comment', $comment));
            $this->object->sendSync($printRequest);
            fclose($comment);
            //Clearing out for other tests.
            ini_set('memory_limit', $memoryLimit);
            $this->fail('Lengths above 0xFFFFFFFF should not be supported.');
        } catch (LengthException $e) {
            fclose($comment);
            //Clearing out for other tests.
            ini_set('memory_limit', $memoryLimit);
            $this->assertEquals(
                LengthException::CODE_UNSUPPORTED,
                $e->getCode(),
                'Improper exception thrown.'
            );
        }
    }
}
