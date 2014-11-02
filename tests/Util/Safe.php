<?php

namespace PEAR2\Net\RouterOS\Util\Test;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\Util;
use PHPUnit_Framework_TestCase;

abstract class Safe extends PHPUnit_Framework_TestCase
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

    public function testSetGetMenu()
    {
        $this->assertSame('/', $this->util->getMenu());
        $this->assertSame(
            '/queue',
            $this->util->setMenu('queue')->getMenu()
        );
        $this->assertSame(
            '/queue/simple',
            $this->util->setMenu('simple')->getMenu()
        );
        $this->assertSame(
            '/queue/tree',
            $this->util->setMenu('.. tree')->getMenu()
        );
        $this->assertSame(
            '/queue/type',
            $this->util->setMenu('../type')->getMenu()
        );
        $this->assertSame(
            '/interface',
            $this->util->setMenu('/interface')->getMenu()
        );
        $this->assertSame(
            '/ip/arp',
            $this->util->setMenu('/ip/arp')->getMenu()
        );
        $this->assertSame(
            '/ip/hotspot',
            $this->util->setMenu('/ip hotspot')->getMenu()
        );
    }

    public function testFindByQuery()
    {
        $this->util->setMenu('/queue/simple');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $this->util->find(
                Query::where('target', HOSTNAME_INVALID . '/32')
            )
        );
    }

    public function testFindNoCriteria()
    {
        $this->util->setMenu('/queue/simple');
        $findResults = $this->util->find();
        $this->assertRegExp(
            self::REGEX_IDLIST,
            $findResults
        );
        $this->assertSame(
            count(explode(',', $findResults)),
            count(
                $this->client->sendSync(
                    new Request('/queue/simple/print')
                )->getAllOfType(Response::TYPE_DATA)
            )
        );
    }

    public function testFindCallback()
    {
        $this->util->setMenu('/queue/simple');
        $findResults = $this->util->find(
            function ($entry) {
                return $entry->getProperty(
                    'target'
                ) === HOSTNAME_INVALID . '/32';
            }
        );
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $findResults
        );
        $this->assertSame(
            $findResults,
            $this->client->sendSync(
                new Request(
                    '/queue/simple/print',
                    Query::where('target', HOSTNAME_INVALID . '/32')
                )
            )->getProperty('.id')
        );
    }
    
    public function testByCallbackName()
    {
        include_once __DIR__ . '/../Extra/isHostnameInvalid.php';

        $this->util->setMenu('/queue/simple');
        $findResults = $this->util->find('isHostnameInvalid');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $findResults
        );
        $this->assertSame(
            $findResults,
            $this->client->sendSync(
                new Request(
                    '/queue/simple/print',
                    Query::where('target', HOSTNAME_INVALID . '/32')
                )
            )->getProperty('.id')
        );
    }

    public function testFindById()
    {
        $originalResult = $this->client->sendSync(
            new Request(
                '/queue/simple/print',
                Query::where('target', HOSTNAME_INVALID . '/32')
            )
        );
        $this->assertSame(
            $originalResult->getProperty('.id'),
            $this->util->find($originalResult->getProperty('.id'))
        );
    }
    
    public function testFindByCommaSeparatedValue()
    {
        $this->util->setMenu('/queue/simple');
        $findResults = $this->util->find('0,1');
        $this->assertRegExp(
            self::REGEX_IDLIST,
            $findResults
        );
        $this->assertCount(2, explode(',', $findResults));

        $findResults = $this->util->find('0,,1');
        $this->assertRegExp(
            self::REGEX_IDLIST,
            $findResults
        );
        $this->assertCount(2, explode(',', $findResults));
    }

    public function testGetallAndCount()
    {
        $this->util->setMenu('/queue/simple');
        $queues = $this->util->getAll();
        $this->assertInstanceOf(ROS_NAMESPACE . '\ResponseCollection', $queues);
        $this->assertSameSize($queues, $this->util);
    }

    public function testInvalidGetallAndCount()
    {
        $this->util->setMenu('/queue');
        $this->assertFalse($this->util->getAll());
        $this->assertCount(-1, $this->util);
    }
}
