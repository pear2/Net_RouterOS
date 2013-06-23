<?php
namespace PEAR2\Net\RouterOS;

use DateTime;
use DateInterval;

class UtilStateAlteringFeaturesTest extends \PHPUnit_Framework_TestCase
{
    const REGEX_ID = '\*[A-F0-9]+';
    const REGEX_IDLIST = '/^(\*[A-F0-9]+\,)*(\*[A-F0-9]+)$/';

    /**
     * @var Util
     */
    protected $util;
    /**
     * @var Client
     */
    protected $client;
    
    /**
     * @var bool Whether connections should be persistent ones.
     */
    protected $isPersistent = false;

    protected function setUp()
    {
        $this->util = new Util(
            $this->client = new Client(
                \HOSTNAME,
                USERNAME,
                PASSWORD,
                PORT,
                $this->isPersistent
            )
        );
    }

    protected function tearDown()
    {
        unset($this->util);
        $this->client->close();
        unset($this->client);
    }

    public function testAdd()
    {
        $printRequest = new Request('/queue/simple/print');
        $beforeCount = count($this->client->sendSync($printRequest));
        $this->util->changeMenu('/queue/simple');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $id = $this->util->add(array('name' => TEST_QUEUE_NAME))
        );
        $afterCount = count($this->client->sendSync($printRequest));
        $this->assertSame(1 + $beforeCount, $afterCount);
        
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
        $this->util->changeMenu('/queue/simple');
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
        $this->util->changeMenu('/queue/simple');
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
            $this->client->sendSync($printRequest)->getArgument('disabled')
        );

        $this->util->disable($id);

        $this->assertSame(
            'true',
            $this->client->sendSync($printRequest)->getArgument('disabled')
        );

        $this->util->enable($id);

        $this->assertSame(
            'false',
            $this->client->sendSync($printRequest)->getArgument('disabled')
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
        $this->util->changeMenu('/queue/simple');
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
        $this->util->changeMenu('/queue/simple');

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
        $this->util->changeMenu('/queue/simple');
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

        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $this->client->sendSync(
                $printRequest
            )->getArgument('target')
        );

        $this->util->set(
            $id,
            array(
                'target' => HOSTNAME_INVALID . '/32'
            )
        );

        $this->assertSame(
            HOSTNAME_INVALID . '/32',
            $this->client->sendSync(
                $printRequest
            )->getArgument('target')
        );

        $this->util->edit(
            $id,
            array(
                'target' => HOSTNAME_SILENT . '/32'
            )
        );

        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $this->client->sendSync(
                $printRequest
            )->getArgument('target')
        );

        $this->util->remove($id);
    }

    /**
     * @depends testAdd
     * @depends testRemove
     * @depends PEAR2\Net\RouterOS\UtilFeaturesTest::testFindNoCriteria
     * 
     * @return void
     */
    public function testFindByNumber()
    {
        $this->util->changeMenu('/queue/simple');
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
        $this->util->changeMenu('/queue/simple');
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
        $nameNot = $this->util->get(TEST_QUEUE_NAME, 'p2p');
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
        $this->util->changeMenu('/ip/firewall/filter');
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
  
        $this->util->changeMenu('/queue/simple');
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

        $this->assertSame(TEST_SCRIPT_NAME, $results->getArgument('comment'));

        $this->util->remove(TEST_QUEUE_NAME);
    }

    public function testExecArgTypes()
    {
        $printRequest = new Request(
            '/queue/simple/print',
            Query::where('name', TEST_QUEUE_NAME)
        );
        $this->util->changeMenu('/queue/simple');

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
        $this->assertSame('num', $results->getArgument('comment'));

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
        $this->assertSame('str', $results->getArgument('comment'));

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
            $results->getArgument('comment'),
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
        $this->assertSame('bool', $results->getArgument('comment'));

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
        $this->assertSame('time', $results->getArgument('comment'));

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
        $this->assertSame('time', $results->getArgument('comment'));

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
        $this->assertSame('array', $results->getArgument('comment'));
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
        $this->util->changeMenu('/queue/simple');

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
        $this->assertSame('2', $results->getArgument('comment'));

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
        $this->assertSame('test', $results->getArgument('comment'));

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
        $this->assertSame('true', $results->getArgument('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
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
        $this->assertSame('hello,world', $results->getArgument('comment'));

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
        $this->assertSame(null, $results->getArgument('comment'));

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
        $this->assertSame(null, $results->getArgument('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateTime('1970-01-01 00:00:00.000001')
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('00:00:00.000001', $results->getArgument('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateTime('1970-01-02 00:00:01')
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('1d00:00:01', $results->getArgument('comment'));

        $this->util->exec(
            'add name=$name target=0.0.0.0/0 comment=$comment',
            array(
                'name' => TEST_QUEUE_NAME,
                'comment' => new DateTime('1970-01-10 01:02:03')
            )
        );
        $results = $this->client->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);
        $this->util->remove(TEST_QUEUE_NAME);
        $this->assertCount(1, $results);
        $this->assertSame('1w2d01:02:03', $results->getArgument('comment'));

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
        $this->assertSame('1w1d00:00:00', $results->getArgument('comment'));
    }

    public function testMove()
    {
        $this->util->changeMenu('/queue/simple');
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
        $putResult1 = $this->util->filePutContents(TEST_FILE_NAME, $data1);
        $getResult1 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult2 = $this->util->filePutContents(
            TEST_FILE_NAME,
            $data2,
            true
        );
        $getResult2 = $this->util->fileGetContents(TEST_FILE_NAME);
        $putResult3 = $this->util->filePutContents(
            TEST_FILE_NAME,
            $data1
        );
        $getResult3 = $this->util->fileGetContents(TEST_FILE_NAME);
        $this->util->changeMenu('/file');
        $this->util->remove(TEST_FILE_NAME);
        $getResult4 = $this->util->fileGetContents(TEST_FILE_NAME);

        $this->assertTrue($putResult1);
        $this->assertSame($data1, $getResult1);
        $this->assertTrue($putResult2);
        $this->assertSame($data2, $getResult2);
        $this->assertFalse($putResult3);
        $this->assertSame($data2, $getResult3);
        $this->assertFalse($getResult4);
    }
    
    public function testFilePutContentsNoPermissions()
    {
        $this->util = new Util(
            $this->client = new Client(
                \HOSTNAME,
                USERNAME2,
                PASSWORD2,
                PORT,
                $this->isPersistent
            )
        );
        $this->assertFalse($this->util->filePutContents(TEST_FILE_NAME, 'ok'));
    }
}
