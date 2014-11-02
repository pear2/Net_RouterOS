<?php

namespace PEAR2\Net\RouterOS\Util\Test;

use DateInterval;
use DateTime;
use DateTimezone;
use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\Util;
use PHPUnit_Framework_TestCase;

abstract class Unsafe extends PHPUnit_Framework_TestCase
{
    const REGEX_ID = '\*[A-F0-9]+';
    const REGEX_IDLIST = '/^((\*[A-F0-9]+)?\,)*(\*[A-F0-9]+)$/';

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
            '/queue/simple/print',
            Query::where('.id', $id)
        );

        $this->assertSame(
            'false',
            $this->client->sendSync($printRequest)->getProperty('disabled')
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
     * @depends testDisableAndEnable
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
            '/queue/simple/print',
            Query::where('.id', $id)
        );

        $responses = $this->client->sendSync($printRequest);
        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $responses->getProperty('target')
        );
        $this->assertNotSame(
            'true',
            $responses->getProperty('disabled')
        );

        $this->util->set(
            $id,
            array(
                'target' => HOSTNAME_INVALID . '/32',
                'disabled'
            )
        );

        $responses = $this->client->sendSync($printRequest);
        $this->assertSame(
            HOSTNAME_INVALID . '/32',
            $responses->getProperty('target')
        );
        $this->assertSame(
            'true',
            $responses->getProperty('disabled')
        );

        $this->util->edit(
            $id,
            array(
                'target' => HOSTNAME_SILENT . '/32'
            )
        );

        $responses = $this->client->sendSync($printRequest);
        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $responses->getProperty('target')
        );
        $this->assertSame(
            'true',
            $responses->getProperty('disabled')
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
        $numberNameNot = $this->util->get(1 + $itemCount, 'name');
        $idName = $this->util->get($id, 'name');
        $nameTarget = $this->util->get(TEST_QUEUE_NAME, 'target');
        $nameNot = $this->util->get(TEST_QUEUE_NAME, 'total-max-limit');
        $nameInvalid = $this->util->get(TEST_QUEUE_NAME, 'p2p');
        $this->util->remove($id);
        $nameTargetNot = $this->util->get(
            TEST_QUEUE_NAME,
            'target'
        );

        $this->assertSame(
            TEST_QUEUE_NAME,
            $numberName
        );
        $this->assertFalse($numberNameNot);
        $this->assertSame(
            TEST_QUEUE_NAME,
            $idName
        );
        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $nameTarget
        );
        $this->assertNull($nameNot);
        $this->assertFalse($nameInvalid);
        $this->assertFalse($nameTargetNot);
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
        $this->assertSame('time', $results->getProperty('comment'));

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
        $this->assertSame('00:00:00.000001', $results->getProperty('comment'));

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
        $this->assertSame('1d00:00:01', $results->getProperty('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateTime(
                    '1970-01-10 01:02:03',
                    new DateTimezone('UTC')
                )
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('1w2d01:02:03', $results->getProperty('comment'));

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
        $getResult4 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult5 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $this->assertTrue($putResult4);
        $this->assertFalse($getResult4);
        $this->assertFalse($putResult5);
        
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
        $getResult4 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult5 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $this->assertTrue($putResult4);
        $this->assertFalse($getResult4);
        $this->assertFalse($putResult5);
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
        $getResult4 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult5 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $this->assertTrue($putResult4);
        $this->assertFalse($getResult4);
        $this->assertFalse($putResult5);
        
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
        $getResult4 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult5 = $this->util->filePutContents(TEST_FILE_NAME, null);
        $this->assertTrue($putResult4);
        $this->assertFalse($getResult4);
        $this->assertFalse($putResult5);

        $this->client->setStreamingResponses(false);
    }

    public function testFilePutContentsNoPermissions()
    {
        $this->setUp(USERNAME2, PASSWORD2);
        $this->assertFalse($this->util->filePutContents(TEST_FILE_NAME, 'ok'));
    }
}
