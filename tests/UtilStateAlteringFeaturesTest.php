<?php
namespace PEAR2\Net\RouterOS;

class UtilStateAlteringFeaturesTest extends \PHPUnit_Framework_TestCase
{
    const REGEX_ID = '\*[A-F0-9]+';
    const REGEX_IDLIST = '/^(\*[A-F0-9]+\,)*(\*[A-F0-9]+)?$/';

    /**
     * @var Util
     */
    protected $util;
    /**
     * @var Client
     */
    protected $client;

    protected function setUp()
    {
        $this->util = new Util(
            $this->client = new Client(\HOSTNAME, USERNAME, PASSWORD, PORT)
        );
    }

    protected function tearDown()
    {
        unset($this->util);
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
     */
    public function testDisableAndEnable()
    {
        $this->util->changeMenu('/queue/simple');
        $id = $this->util->add(
            array('name' => TEST_QUEUE_NAME, 'disabled' => 'no')
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
     */
    public function testRemove()
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
        
        $this->util->remove($id);

        $postCount = count($this->client->sendSync($printRequest));
        $this->assertSame($beforeCount, $postCount);
    }

    /**
     * @depends testRemove
     */
    public function testSetAndEdit()
    {
        $this->util->changeMenu('/queue/simple');
        $id = $this->util->add(
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
            $this->client->sendSync(
                $printRequest
            )->getArgument('target-addresses')
        );

        $this->util->set(
            $id,
            array(
                'target-addresses' => HOSTNAME_INVALID . '/32'
            )
        );

        $this->assertSame(
            HOSTNAME_INVALID . '/32',
            $this->client->sendSync(
                $printRequest
            )->getArgument('target-addresses')
        );

        $this->util->edit(
            $id,
            array(
                'target-addresses' => HOSTNAME_SILENT . '/32'
            )
        );

        $this->assertSame(
            HOSTNAME_SILENT . '/32',
            $this->client->sendSync(
                $printRequest
            )->getArgument('target-addresses')
        );

        $this->util->remove($id);
    }

    /**
     * @depends testAdd
     * @depends testRemove
     */
    public function testFindByNumber()
    {
        $this->util->changeMenu('/queue/simple');
        $itemCount = count(explode(',', $this->util->find()));
        $id = $this->util->add(
            array(
                'name' => TEST_QUEUE_NAME,
                'target-addresses' => HOSTNAME_SILENT . '/32'
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
}