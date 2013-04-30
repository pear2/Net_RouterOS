<?php
namespace PEAR2\Net\RouterOS;

class UtilStateAlteringFeaturesTest extends \PHPUnit_Framework_TestCase
{
    const REGEX_ID = '\*[A-F0-9]+';
    const REGEX_IDLIST = '/^(\*[A-F0-9]+\,)*(\*[A-F0-9]+)?$/';

    /**
     * @var Util
     */
    protected $objUtil;
    /**
     * @var Client
     */
    protected $objClient;

    protected function setUp()
    {
        $this->objUtil = new Util(
            $this->objClient = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT)
        );
    }

    protected function tearDown()
    {
        unset($this->objUtil);
        unset($this->objClient);
    }

    public function testAdd()
    {
        $printRequest = new Request('/queue/simple/print');
        $beforeCount = count($this->objClient->sendSync($printRequest));
        $this->objUtil->changeMenu('/queue/simple');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $id = $this->objUtil->add(array('name' => TEST_QUEUE_NAME))
        );
        $afterCount = count($this->objClient->sendSync($printRequest));
        $this->assertSame(1 + $beforeCount, $afterCount);
        
        $removeRequest = new Request('/queue/simple/remove');
        $removeRequest->setArgument('numbers', $id);
        $this->objClient->sendSync($removeRequest);

        $postCount = count($this->objClient->sendSync($printRequest));
        $this->assertSame($beforeCount, $postCount);
    }

    /**
     * @depends testAdd
     * 
     * @return void
     */
    public function testDisableAndEnable()
    {
        $this->objUtil->changeMenu('/queue/simple');
        $id = $this->objUtil->add(
            array('name' => TEST_QUEUE_NAME, 'disabled' => 'no')
        );
        $printRequest = new Request(
            '/queue/simple/print',
            Query::where('.id', $id)
        );

        $this->assertSame(
            'false',
            $this->objClient->sendSync($printRequest)->getArgument('disabled')
        );

        $this->objUtil->disable($id);

        $this->assertSame(
            'true',
            $this->objClient->sendSync($printRequest)->getArgument('disabled')
        );

        $this->objUtil->enable($id);

        $this->assertSame(
            'false',
            $this->objClient->sendSync($printRequest)->getArgument('disabled')
        );

        $removeRequest = new Request('/queue/simple/remove');
        $removeRequest->setArgument('numbers', $id);
        $this->objClient->sendSync($removeRequest);
    }

    /**
     * @depends testDisableAndEnable
     * 
     * @return void
     */
    public function testRemove()
    {
        $printRequest = new Request('/queue/simple/print');
        $beforeCount = count($this->objClient->sendSync($printRequest));
        $this->objUtil->changeMenu('/queue/simple');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $id = $this->objUtil->add(array('name' => TEST_QUEUE_NAME))
        );
        $afterCount = count($this->objClient->sendSync($printRequest));
        $this->assertSame(1 + $beforeCount, $afterCount);
        
        $this->objUtil->remove($id);

        $postCount = count($this->objClient->sendSync($printRequest));
        $this->assertSame($beforeCount, $postCount);
    }

    /**
     * @depends testRemove
     * 
     * @return void
     */
    public function testSetAndEdit()
    {
        $this->objUtil->changeMenu('/queue/simple');
        $id = $this->objUtil->add(
            array(
                'name' => TEST_QUEUE_NAME,
                'target-addresses' => HOSTNAME_SILENT . '/32'
            )
        );

        $printRequest = new Request(
            '/queue/simple/print',
            Query::where('.id', $id)
        );

        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $this->objClient->sendSync(
                $printRequest
            )->getArgument('target-addresses')
        );

        $this->objUtil->set(
            $id,
            array(
                'target-addresses' => HOSTNAME_INVALID . '/32'
            )
        );

        $this->assertSame(
            HOSTNAME_INVALID . '/32',
            $this->objClient->sendSync(
                $printRequest
            )->getArgument('target-addresses')
        );

        $this->objUtil->edit(
            $id,
            array(
                'target-addresses' => HOSTNAME_SILENT . '/32'
            )
        );

        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $this->objClient->sendSync(
                $printRequest
            )->getArgument('target-addresses')
        );

        $this->objUtil->remove($id);
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
        $this->objUtil->changeMenu('/queue/simple');
        $itemCount = count(explode(',', $this->objUtil->find()));
        $id = $this->objUtil->add(
            array(
                'name' => TEST_QUEUE_NAME,
                'target-addresses' => HOSTNAME_SILENT . '/32'
            )
        );
        $this->assertSame(
            1 + $itemCount,
            count(explode(',', $this->objUtil->find()))
        );
        $this->assertSame($id, $this->objUtil->find($itemCount));
        $this->assertSame($id, $this->objUtil->find((string)$itemCount));
        
        $this->objUtil->remove($id);
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
  
        $this->objUtil->changeMenu('/queue/simple');
        $this->assertCount(
            0,
            $this->objClient->sendSync(
                $printRequest
            )->getAllOfType(Response::TYPE_DATA)
        );

        $this->objUtil->exec(
            'add name=$name',
            array('name' => TEST_QUEUE_NAME)
        );

        $this->assertCount(
            1,
            $this->objClient->sendSync(
                $printRequest
            )->getAllOfType(Response::TYPE_DATA)
        );
        
        $this->objUtil->remove(TEST_QUEUE_NAME);
 
        $this->assertCount(
            0,
            $this->objClient->sendSync(
                $printRequest
            )->getAllOfType(Response::TYPE_DATA)
        );

        $this->objUtil->exec(
            'add name=$name comment=$"_"',
            array('name' => TEST_QUEUE_NAME),
            null,
            TEST_SCRIPT_NAME
        );

        $results = $this->objClient->sendSync(
            $printRequest
        )->getAllOfType(Response::TYPE_DATA);

        $this->assertCount(1, $results);

        $this->assertEquals(TEST_SCRIPT_NAME, $results->getArgument('comment'));

        $this->objUtil->remove(TEST_QUEUE_NAME);
    }
}