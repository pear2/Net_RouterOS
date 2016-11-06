<?php

namespace PEAR2\Net\RouterOS\Test\Util;

use DateInterval;
use DateTime;
use DateTimezone;
use PEAR2\Net\RouterOS\Communicator;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\ResponseCollection;
use PEAR2\Net\RouterOS\RouterErrorException;
use PEAR2\Net\RouterOS\Script;
use PEAR2\Net\RouterOS\Util;
use PHPUnit_Framework_TestCase;

abstract class Unsafe extends PHPUnit_Framework_TestCase
{
    const REGEX_ID = '\*[a-f0-9]+';
    const REGEX_IDLIST = '/^((\*[a-f0-9]+)\,)*(\*[a-f0-9]+)$/';

    /**
     * @var Util
     */
    protected $util;
    /**
     * @var Client
     */
    protected $client;

    public function testAdd()
    {
        $printRequest = new Request('/queue/simple/print');
        $beforeCount = count($this->client->sendSync($printRequest));
        $this->util->setMenu('/queue/simple');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $id = $this->util->add(array('name' => TEST_QUEUE_NAME, 'disabled'))
        );

        $afterCount = count($this->client->sendSync($printRequest));
        $this->assertSame(1 + $beforeCount, $afterCount);
        $this->assertSame(
            'true',
            $this->client->sendSync(
                $printRequest->setQuery(Query::where('name', TEST_QUEUE_NAME))
            )->getProperty('disabled')
        );
        $printRequest->setQuery(null);

        try {
            $this->util->add(array('name' => TEST_QUEUE_NAME, 'disabled'));
            $this->fail(
                'Creating a queue with a duplicated name should throw an exception.'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_ADD_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }

        $removeRequest = new Request('/queue/simple/remove');
        $removeRequest->setArgument('numbers', $id);
        $this->client->sendSync($removeRequest);

        $postCount = count($this->client->sendSync($printRequest));
        $this->assertSame($beforeCount, $postCount);
    }

    /**
     * @depends testAdd
     *
     * @return void
     */
    public function testAddUpdatingCache()
    {
        $this->util->setMenu('/queue/simple');
        $beforeCount = substr_count($this->util->find(), ',');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $id = $this->util->add(array('name' => TEST_QUEUE_NAME))
        );
        $afterCount = substr_count($this->util->find(), ',');
        $this->assertSame(1 + $beforeCount, $afterCount);

        $removeRequest = new Request('/queue/simple/remove');
        $removeRequest->setArgument('numbers', $id);
        $this->client->sendSync($removeRequest);

        $postCount = substr_count($this->util->clearIdCache()->find(), ',');
        $this->assertSame($beforeCount, $postCount);
    }

    /**
     * @depends testAdd
     *
     * @return void
     */
    public function testDisableAndEnable()
    {
        $this->util->setMenu('/queue/simple');
        $id = $this->util->add(
            array(
                'name' => TEST_QUEUE_NAME,
                'disabled' => 'no',
                'target' => '0.0.0.0/0'
            )
        );
        $printRequest = new Request(
            '/queue/simple/print'
        );
        $printRequest->setArgument('from', $id);

        $result = $this->client->sendSync($printRequest);
        $this->assertSame(
            'false',
            $result->getProperty('disabled'),
            print_r($result->toArray(), true) . ';;' . print_r($printRequest, true)
        );

        $this->util->disable($id);

        $this->assertSame(
            'true',
            $this->client->sendSync($printRequest)->getProperty('disabled')
        );

        $this->util->enable($id);

        $this->assertSame(
            'false',
            $this->client->sendSync($printRequest)->getProperty('disabled')
        );

        $removeRequest = new Request('/queue/simple/remove');
        $removeRequest->setArgument('numbers', $id);
        $this->client->sendSync($removeRequest);
    }

    /**
     * @depends testAdd
     *
     * @return void
     */
    public function testRemove()
    {
        $printRequest = new Request('/queue/simple/print');
        $beforeCount = count($this->client->sendSync($printRequest));
        $this->util->setMenu('/queue/simple');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $id = $this->util->add(
                array(
                    'name' => TEST_QUEUE_NAME,
                    'target' => '0.0.0.0/0'
                )
            )
        );
        $afterCount = count($this->client->sendSync($printRequest));
        $this->assertSame(1 + $beforeCount, $afterCount);

        $this->util->remove($id);

        $postCount = count($this->client->sendSync($printRequest));
        $this->assertSame($beforeCount, $postCount);
    }

    /**
     * @depends testRemove
     *
     * @return void
     */
    public function testAddMultiple()
    {
        $printRequest = new Request('/queue/simple/print');
        $this->util->setMenu('/queue/simple');

        $beforeCount = count($this->client->sendSync($printRequest));
        $this->assertRegExp(
            self::REGEX_IDLIST,
            $idList = $this->util->add(
                array('name' => TEST_QUEUE_NAME, 'target' => '0.0.0.0/0'),
                array('name' => TEST_QUEUE_NAME1, 'target' => '0.0.0.0/0')
            )
        );
        $afterCount = count($this->client->sendSync($printRequest));
        $this->assertSame(2 + $beforeCount, $afterCount);

        $this->util->remove($idList);

        $postCount = count($this->client->sendSync($printRequest));
        $this->assertSame($beforeCount, $postCount);

        $beforeCount = count($this->client->sendSync($printRequest));
        $this->assertRegExp(
            self::REGEX_IDLIST,
            $idList = $this->util->add(
                array('name' => TEST_QUEUE_NAME, 'target' => '0.0.0.0/0'),
                null,
                array('name' => TEST_QUEUE_NAME1, 'target' => '0.0.0.0/0')
            )
        );
        $afterCount = count($this->client->sendSync($printRequest));
        $this->assertSame(2 + $beforeCount, $afterCount);

        $this->util->remove($idList);

        $postCount = count($this->client->sendSync($printRequest));
        $this->assertSame($beforeCount, $postCount);
    }

    /**
     * @depends testRemove
     *
     * @return void
     */
    public function testComment()
    {
        $this->util->setMenu('/queue/simple');
        $id = $this->util->add(
            array(
                'name' => TEST_QUEUE_NAME,
                'target' => HOSTNAME_SILENT . '/32'
            )
        );

        $printRequest = new Request(
            '/queue/simple/print'
        );
        $printRequest->setArgument('from', $id);

        $responses = $this->client->sendSync($printRequest);
        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $responses->getProperty('target')
        );
        $this->assertNull(
            $responses->getProperty('comment')
        );
        $this->util->comment(TEST_QUEUE_NAME, 'test comment');

        $responses = $this->client->sendSync($printRequest);
        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $responses->getProperty('target')
        );
        $this->assertSame(
            'test comment',
            $responses->getProperty('comment')
        );

        $this->util->remove($id);
    }

    /**
     * @depends testAdd
     * @depends testRemove
     *
     * @return void
     */
    public function testFindByNumber()
    {
        $this->util->setMenu('/queue/simple');
        $itemCount = count(explode(',', $this->util->find()));
        $id = $this->util->add(
            array(
                'name' => TEST_QUEUE_NAME,
                'target' => HOSTNAME_SILENT . '/32'
            )
        );
        $this->assertSame(
            1 + $itemCount,
            count(explode(',', $this->util->find()))
        );
        $this->assertSame($id, $this->util->find($itemCount));
        $this->assertSame($id, $this->util->find((string)$itemCount));

        $this->util->remove($id);
    }

    /**
     * @depends testFindByNumber
     *
     * @return void
     */
    public function testGet()
    {
        $this->util->setMenu('/queue/simple');
        $itemCount = count(explode(',', $this->util->find()));
        $id = $this->util->add(
            array(
                'name' => TEST_QUEUE_NAME,
                'target' => HOSTNAME_SILENT . '/32'
            )
        );

        $numberName = $this->util->get($itemCount, 'name');
        try {
            $this->util->get(1 + $itemCount, 'name');
        } catch (RouterErrorException $e) {
            $this->assertSame(
                RouterErrorException::CODE_CACHE_ERROR,
                $e->getCode()
            );
        }
        $idName = $this->util->get($id, 'name');
        $nameTarget = $this->util->get(TEST_QUEUE_NAME, 'target');
        $queryTarget = $this->util->get(Query::where('name', TEST_QUEUE_NAME), 'target');
        $nameNot = $this->util->get(TEST_QUEUE_NAME, 'total-max-limit');
        try {
            $this->util->get(TEST_QUEUE_NAME, 'p2p');
        } catch (RouterErrorException $e) {
            $this->assertSame(
                RouterErrorException::CODE_GET_ERROR,
                $e->getCode()
            );
        }
        $idAll = $this->util->get($id);
        $nameAll = $this->util->get(TEST_QUEUE_NAME);
        $queryAll = $this->util->get(Query::where('name', TEST_QUEUE_NAME));
        
        $this->util->remove($id);
        try {
            $this->util->get(
                TEST_QUEUE_NAME,
                'target'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(
                RouterErrorException::CODE_GET_ERROR,
                $e->getCode()
            );
        }

        $this->assertSame(
            TEST_QUEUE_NAME,
            $numberName
        );
        $this->assertSame(
            TEST_QUEUE_NAME,
            $idName
        );
        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $nameTarget
        );
        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $queryTarget
        );
        $this->assertNull($nameNot);

        $this->assertInternalType('array', $idAll);
        $this->assertArraySubset(
            array(
                'name' => TEST_QUEUE_NAME, 
                'target' => HOSTNAME_SILENT . '/32'
            ),
            $idAll
        );
        $this->assertInternalType('array', $nameAll);
        $this->assertArraySubset(
            array(
                'name' => TEST_QUEUE_NAME, 
                'target' => HOSTNAME_SILENT . '/32'
            ),
            $nameAll
        );
        $this->assertInternalType('array', $queryAll);
        $this->assertArraySubset(
            array(
                'name' => TEST_QUEUE_NAME, 
                'target' => HOSTNAME_SILENT . '/32'
            ),
            $queryAll
        );
    }

    /**
     * @depends testGet
     *
     * @return void
     */
    public function testUnsetValue()
    {
        $value = 'all-p2p';
        $this->util->setMenu('/ip/firewall/filter');
        $id = $this->util->add(
            array(
                'comment' => 'API TESTING',
                'p2p'     => $value,
                'action'  => 'passthrough',
                'chain'   => 'forward'
            )
        );
        $targetBefore = $this->util->get($id, 'p2p');
        $this->util->unsetValue($id, 'p2p');
        $targetAfter = $this->util->get($id, 'p2p');
        $this->util->remove($id);

        $this->assertSame($value, $targetBefore);
        $this->assertNull($targetAfter);
    }

    /**
     * @depends testRemove
     * @depends testUnsetValue
     *
     * @return void
     */
    public function testSetAndEdit()
    {
        $this->util->setMenu('/queue/simple');
        $id = $this->util->add(
            array(
                'name' => TEST_QUEUE_NAME,
                'target' => HOSTNAME_SILENT . '/32'
            )
        );

        $printRequest = new Request(
            '/queue/simple/print'
        );
        $printRequest->setArgument('from', $id);

        $responses = $this->client->sendSync($printRequest);
        $this->assertNotSame('true', $responses->getProperty('disabled'));
        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $responses->getProperty('target'),
            print_r($responses->toArray(), true) . ';;' . print_r($printRequest, true)
        );

        $this->util->set(
            $id,
            array(
                'target' => HOSTNAME_INVALID . '/32',
                'disabled'
            )
        );

        $responses = $this->client->sendSync($printRequest);
        $this->assertSame('true', $responses->getProperty('disabled'));
        $this->assertSame(
            HOSTNAME_INVALID . '/32',
            $responses->getProperty('target')
        );

        $this->util->edit($id, 'target', HOSTNAME_SILENT . '/32');

        $responses = $this->client->sendSync($printRequest);
        $this->assertSame('true', $responses->getProperty('disabled'));
        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $responses->getProperty('target')
        );

        $this->util->edit($id, 'target', null);

        $responses = $this->client->sendSync($printRequest);
        $this->assertSame('true', $responses->getProperty('disabled'));
        $this->assertSame(null, $responses->getProperty('target'));

        $this->util->remove($id);
    }
    
    /**
     * @depends testSetAndEdit
     */
    public function testGetCurrentTime()
    {
        $originalTimezone = $this->util->setMenu('/system clock')
            ->get(null, 'time-zone-name');
        $originalTimezoneAutodetect = $this->util
            ->get(null, 'time-zone-autodetect');
        
        $this->util->set(
            null,
            array(
                'time-zone-autodetect' => 'false',
                'time-zone-name' => TEST_TIMEZONE
            )
        );
        $curTime = $this->util->setMenu('/')->getCurrentTime();
        $this->assertInstanceOf(
            'DateTime',
             $curTime
        );
        $this->assertSame(TEST_TIMEZONE, $curTime->getTimezone()->getName());
        
        $this->util->setMenu('/system clock')
            ->set(null, array('time-zone-name' => 'manual'));
        $curTimeInManual = $this->util->setMenu('/')->getCurrentTime();
        $this->assertNotSame(
            TEST_TIMEZONE,
            $curTimeInManual->getTimezone()->getName()
        );
        
        $this->util->setMenu('/system clock')->set(
            null,
            array(
                'time-zone-name' => $originalTimezone,
                'time-zone-autodetect' => $originalTimezoneAutodetect
            )
        );
    }
    
    public function testGetCurrentTimeFromStreamClient()
    {
        $this->client->setStreamingResponses(true);
        $this->testGetCurrentTime();
    }

    /**
     * @depends testRemove
     *
     * @return void
     */
    public function testExec()
    {
        $printRequest = new Request(
            '/queue/simple/print',
            Query::where('name', TEST_QUEUE_NAME)
        );

        $this->util->setMenu('/queue/simple');
        $this->assertCount(
            0,
            $this->client->sendSync(
                $printRequest
            )->getAllOfType(Response::TYPE_DATA)
        );

        $this->util->exec(
            'add name=$name target=0.0.0.0/0',
            array('name' => TEST_QUEUE_NAME)
        );

        $this->assertCount(
            1,
            $this->client->sendSync(
                $printRequest
            )->getAllOfType(Response::TYPE_DATA)
        );

        $this->util->remove(TEST_QUEUE_NAME);

        $this->assertCount(
            0,
            $this->client->sendSync(
                $printRequest
            )->getAllOfType(Response::TYPE_DATA)
        );

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$"_"',
            array('name' => TEST_QUEUE_NAME),
            null,
            TEST_SCRIPT_NAME
        );

        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);

        $this->assertCount(1, $results);

        $this->assertSame(TEST_SCRIPT_NAME, $results->getProperty('comment'));

        $this->util->remove(TEST_QUEUE_NAME);
    }

    public function testExecArgTypes()
    {
        $printRequest = new Request(
            '/queue/simple/print',
            Query::where('name', TEST_QUEUE_NAME)
        );
        $this->util->setMenu('/queue/simple');

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=[:typeof $comment]',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => 2
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('num', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=[:typeof $comment]',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => 'test'
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('str', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=[:typeof $comment]',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => null
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertContains(
            $results->getProperty('comment'),
            array('nil', 'nothing')
        );

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=[:typeof $comment]',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => true
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('bool', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=[:typeof $comment]',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateTime()
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('str', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=[:typeof $comment]',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateInterval('P8D')
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('time', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=[:typeof $comment]',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => array('hello', 'world')
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('array', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=[:typeof [($comment->"key")]]',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => array('key' => 2)
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('num', $results->getProperty('comment'));
    }

    /**
     * @depends testExecArgTypes
     *
     * @return void
     */
    public function testExecArgValues()
    {
        $printRequest = new Request(
            '/queue/simple/print',
            Query::where('name', TEST_QUEUE_NAME)
        );
        $this->util->setMenu('/queue/simple');

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => 2
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('2', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => 'test'
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('test', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => true
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('true', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=[:pick $comment 0]',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => array('hello', 'world')
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('hello', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => array()
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame(null, $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => null
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame(null, $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateTime(
                    '1970-01-01 00:00:00.000001',
                    new DateTimezone('UTC')
                )
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('Jan/01/1970 00:00:00.000001', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateTime(
                    '1970-01-02 00:00:01',
                    new DateTimezone('UTC')
                )
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('Jan/02/1970 00:00:01', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateTime(
                    '1970-01-10 00:00:00',
                    new DateTimezone(TEST_TIMEZONE)
                )
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('Jan/10/1970 00:00:00', $results->getProperty('comment'));

        $datePrime = new DateTime(
            '1970-01-10 12:34:56',
            new DateTimezone('UTC')
        );
        $unixEpoch = new DateTime('@0', new DateTimezone('UTC'));
        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => $unixEpoch->diff($datePrime)
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('1w2d12:34:56', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateTime(
                    '1970-01-02 00:00:00',
                    new DateTimezone('UTC')
                )
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('Jan/02/1970', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateInterval('P8D')
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('1w1d00:00:00', $results->getProperty('comment'));
    }

    /**
     * @depends testExec
     * @depends testAdd
     *
     * @return void
     */
    public function testExecExceptions()
    {
        $this->util->setMenu('/system script')->add(
            array(
                'name' => TEST_SCRIPT_NAME,
                'source' => '#TEST'
            )
        );
        try {
            $this->util->exec(
                '#TESTING',
                array(),
                null,
                TEST_SCRIPT_NAME
            );
            $this->fail(
                'Adding a script with duplicated name should throw an exception'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SCRIPT_ADD_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
        $this->util->remove(TEST_SCRIPT_NAME);

        try {
            $this->util->exec(
                ':error "My error message";',
                array(),
                null,
                TEST_SCRIPT_NAME
            );
            $this->fail(
                'Uncaught errors from the result should throw an exception'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SCRIPT_RUN_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->getType());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->seek(1)->getType());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-2)->getType());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-1)->getType());
        }

        try {
            $this->util->exec(
                '/system script remove $"_"',
                array(),
                null,
                TEST_SCRIPT_NAME
            );
            $this->fail(
                'Removing the script from inside should throw an exception'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SCRIPT_REMOVE_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->getType());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(1)->getType());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->seek(2)->getType());
        }
    }

    public function testExecWithCharset()
    {
        $this->client->setCharset(
            array(
                Communicator::CHARSET_REMOTE => 'windows-1251',
                Communicator::CHARSET_LOCAL => 'UTF-8'
            )
        );

        $this->util->setMenu('/queue simple');
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, iconv('UTF-8', 'windows-1251', 'уф'));
        rewind($stream);
        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=("йес! " . $comment . " " . $stream)',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => 'ягода',
                'stream' => $stream
            ),
            null,
            TEST_SCRIPT_NAME
        );
        $comment = 'йес! ягода уф';
        $this->assertSame(
            $comment,
            $this->util->get(TEST_QUEUE_NAME, 'comment')
        );
        $this->client->setCharset(
            array(
                Communicator::CHARSET_REMOTE => null,
                Communicator::CHARSET_LOCAL => null
            )
        );
        $this->assertSame(
            iconv('UTF-8', 'windows-1251', $comment),
            $this->util->get(TEST_QUEUE_NAME, 'comment')
        );
        $this->util->remove(TEST_QUEUE_NAME);
        
    }

    public function testMove()
    {
        $this->util->setMenu('/queue/simple');
        $id = $this->util->add(array('name' => TEST_QUEUE_NAME));
        $result = $this->util->move($id, 0);
        $this->util->remove($id);
        $this->assertCount(1, $result);

        $idList = $this->util->add(
            array('name' => TEST_QUEUE_NAME),
            array('name' => TEST_QUEUE_NAME1)
        );
        $result = $this->util->move($idList, 0);
        $this->util->remove($idList);
        $this->assertCount(1, $result);

        $id = $this->util->add(array('name' => TEST_QUEUE_NAME));
        $result = $this->util->move($id, '0,1');
        $this->util->remove($id);
        $this->assertCount(1, $result);

        $idList = $this->util->add(
            array('name' => TEST_QUEUE_NAME),
            array('name' => TEST_QUEUE_NAME1)
        );
        $result = $this->util->move($idList, '0,1');
        $this->util->remove($idList);
        $this->assertCount(1, $result);
    }

    /**
     * @depends testDisableAndEnable
     * @depends testRemove
     * @depends testComment
     * @depends testUnsetValue
     * @depends testMove
     * @depends testSetAndEdit
     * @depends testGet
     *
     * @return void
     */
    public function testAbsenseExceptions()
    {
        try {
            $this->util->disable('');
            $this->fail(
                'There should not be "disable" at the root menu'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_DISABLE_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
        try {
            $this->util->enable('');
            $this->fail(
                'There should not be "enable" at the root menu'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_ENABLE_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
        try {
            $this->util->remove('');
            $this->fail(
                'There should not be "remove" at the root menu'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_REMOVE_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
        try {
            $this->util->comment('', 'TEST');
            $this->fail(
                'There should not be "comment" at the root menu'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_COMMENT_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
        try {
            $this->util->unsetValue('', 'TEST');
            $this->fail(
                'There should not be "unset" at the root menu'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_UNSET_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
        try {
            $this->util->move('', '');
            $this->fail(
                'There should not be "move" at the root menu'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_MOVE_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }

        try {
            $this->util->get('', 'TEST');
            $this->fail(
                'The "get" at the root menu should not be for items'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_GET_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
        try {
            $this->util->set('', array('TEST'));
            $this->fail(
                'The "set" at the root menu should not be for items'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SET_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
    }

    /**
     * @depends testAdd
     * @depends testRemove
     */
    public function testFilePutAndGetContents()
    {
        $data1 = 'test';
        $data2 = 'ok';
        $data1s = fopen('php://temp', 'r+b');
        fwrite($data1s, $data1);
        rewind($data1s);
        $data2s = fopen('php://temp', 'r+b');
        fwrite($data2s, $data2);
        rewind($data2s);

        //New and overwite string
        $putResult1 = $this->util->filePutContents(TEST_FILE_NAME, $data1);
        $getResult1 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult2 = $this->util->filePutContents(
            TEST_FILE_NAME,
            $data2,
            true
        );
        $getResult2 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult3 = $this->util->filePutContents(TEST_FILE_NAME, $data1);
        $getResult3 = $this->util->fileGetContents(TEST_FILE_NAME);

        $this->assertTrue($putResult1);
        $this->assertSame($data1, $getResult1);
        $this->assertTrue($putResult2);
        $this->assertSame($data2, $getResult2);
        $this->assertFalse($putResult3);
        $this->assertSame($data2, $getResult3);

        //Removal
        $putResult4 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $putResult5 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $this->assertTrue($putResult4);
        $this->assertFalse($putResult5);
        try {
            $this->util->fileGetContents(TEST_FILE_NAME);
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SCRIPT_RUN_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->getType());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->seek(1)->getType());
            $this->assertInternalType('string', $e->getResponses()->seek(1)->getProperty('message'));
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-2)->getType());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-1)->getType());
        }

        //New and overwite stream
        $putResult1 = $this->util->filePutContents(TEST_FILE_NAME, $data1s);
        $getResult1 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult2 = $this->util->filePutContents(
            TEST_FILE_NAME,
            $data2s,
            true
        );
        $getResult2 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult3 = $this->util->filePutContents(TEST_FILE_NAME, $data1s);
        $getResult3 = $this->util->fileGetContents(TEST_FILE_NAME);

        $this->assertTrue($putResult1);
        $this->assertSame($data1, $getResult1);
        $this->assertTrue($putResult2);
        $this->assertSame($data2, $getResult2);
        $this->assertFalse($putResult3);
        $this->assertSame($data2, $getResult3);

        //Removal
        $putResult4 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $putResult5 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $this->assertTrue($putResult4);
        $this->assertFalse($putResult5);
        try {
            $this->util->fileGetContents(TEST_FILE_NAME);
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SCRIPT_RUN_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->getType());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->seek(1)->getType());
            $this->assertInternalType('string', $e->getResponses()->seek(1)->getProperty('message'));
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-2)->getType());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-1)->getType());
        }
        
        //Add failing attempts
        $this->util->setMenu('/system script')->add(
            array(
                'name' => TEST_SCRIPT_NAME,
                'source' => '#TEST'
            )
        );
        try {
            $this->util->fileGetContents(TEST_FILE_NAME, TEST_SCRIPT_NAME);
            $this->fail(
                'Getting file through existing script should throw an exception'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SCRIPT_ADD_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
        $this->util->remove(TEST_SCRIPT_NAME);
    }

    public function testFilePutAndGetContentsStreamed()
    {
        $data1 = 'test';
        $data2 = 'ok';
        $data1s = fopen('php://temp', 'r+b');
        fwrite($data1s, $data1);
        rewind($data1s);
        $data2s = fopen('php://temp', 'r+b');
        fwrite($data2s, $data2);
        rewind($data2s);

        $this->client->setStreamingResponses(true);
        //New and overwite string
        $putResult1 = $this->util->filePutContents(TEST_FILE_NAME, $data1);
        $getResult1 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult2 = $this->util->filePutContents(
            TEST_FILE_NAME,
            $data2,
            true
        );
        $getResult2 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult3 = $this->util->filePutContents(TEST_FILE_NAME, $data1);
        $getResult3 = $this->util->fileGetContents(TEST_FILE_NAME);

        $this->assertTrue($putResult1);
        $this->assertSame($data1, stream_get_contents($getResult1));
        $this->assertTrue($putResult2);
        $this->assertSame($data2, stream_get_contents($getResult2));
        $this->assertFalse($putResult3);
        $this->assertSame($data2, stream_get_contents($getResult3));

        //Removal
        $putResult4 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $putResult5 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $this->assertTrue($putResult4);
        $this->assertFalse($putResult5);
        try {
            $this->util->fileGetContents(TEST_FILE_NAME);
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SCRIPT_RUN_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->getType());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->seek(1)->getType());
            $this->assertInternalType('resource', $e->getResponses()->seek(1)->getProperty('message'));
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-2)->getType());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-1)->getType());
        }

        //New and overwite stream
        $putResult1 = $this->util->filePutContents(TEST_FILE_NAME, $data1s);
        $getResult1 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult2 = $this->util->filePutContents(
            TEST_FILE_NAME,
            $data2s,
            true
        );
        $getResult2 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult3 = $this->util->filePutContents(TEST_FILE_NAME, $data1s);
        $getResult3 = $this->util->fileGetContents(TEST_FILE_NAME);

        $this->assertTrue($putResult1);
        $this->assertSame($data1, stream_get_contents($getResult1));
        $this->assertTrue($putResult2);
        $this->assertSame($data2, stream_get_contents($getResult2));
        $this->assertFalse($putResult3);
        $this->assertSame($data2, stream_get_contents($getResult3));

        //Removal
        $putResult4 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $putResult5 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $this->assertTrue($putResult4);
        $this->assertFalse($putResult5);
        try {
            $this->util->fileGetContents(TEST_FILE_NAME);
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SCRIPT_RUN_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->getType());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->seek(1)->getType());
            $this->assertInternalType('resource', $e->getResponses()->seek(1)->getProperty('message'));
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-2)->getType());
            $this->assertSame(Response::TYPE_FINAL, $e->getResponses()->seek(-1)->getType());
        }

        $this->client->setStreamingResponses(false);
    }

    public function testFilePutContentsNoPermissions()
    {
        $this->setUp(USERNAME2, PASSWORD2);
        $this->assertFalse($this->util->filePutContents(TEST_FILE_NAME, 'ok'));
    }

    public function testFileGetContentsNoExec()
    {
        $mockResult = new ResponseCollection(array());
        $utilMock = $this->getMockBuilder(ROS_NAMESPACE . '\Util')
            ->setConstructorArgs(array($this->client))
            ->setMethods(array('exec'))
            ->getMock();
        $utilMock->method('exec')->willReturn($mockResult);
        
        
        try {
            $utilMock->fileGetContents(TEST_FILE_NAME);
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_SCRIPT_FILE_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame($mockResult, $e->getResponses());
        }
    }
}
