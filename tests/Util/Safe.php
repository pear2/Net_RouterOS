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

    public function testChangeMenu()
    {
        $this->assertSame('/', $this->util->changeMenu());
        $this->assertSame(
            '/queue',
            $this->util->changeMenu('queue')->changeMenu()
        );
        $this->assertSame(
            '/queue/simple',
            $this->util->changeMenu('simple')->changeMenu()
        );
        $this->assertSame(
            '/queue/tree',
            $this->util->changeMenu('.. tree')->changeMenu()
        );
        $this->assertSame(
            '/queue/type',
            $this->util->changeMenu('../type')->changeMenu()
        );
        $this->assertSame(
            '/interface',
            $this->util->changeMenu('/interface')->changeMenu()
        );
        $this->assertSame(
            '/ip/arp',
            $this->util->changeMenu('/ip/arp')->changeMenu()
        );
        $this->assertSame(
            '/ip/hotspot',
            $this->util->changeMenu('/ip hotspot')->changeMenu()
        );
    }

    public function testFindByQuery()
    {
        $this->util->changeMenu('/queue/simple');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $this->util->find(
                Query::where('target', HOSTNAME_INVALID . '/32')
            )
        );
    }

    public function testFindNoCriteria()
    {
        $this->util->changeMenu('/queue/simple');
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
        $this->util->changeMenu('/queue/simple');
        $findResults = $this->util->find(
            function ($entry) {
                return $entry->getArgument(
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
            )->getArgument('.id')
        );
    }
    
    public function testByCallbackName()
    {
        if (!function_exists('isHostnameInvalid')) {
            include 'data://text/plain;base64,' . base64_encode(
                <<<HEREDOC
<?php
function isHostnameInvalid(\$entry) {
    return \$entry->getArgument(
        'target'
    ) === \PEAR2\Net\RouterOS\Client\Test\HOSTNAME_INVALID . '/32';
}
HEREDOC
            );
        }
        $this->util->changeMenu('/queue/simple');
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
            )->getArgument('.id')
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
            $originalResult->getArgument('.id'),
            $this->util->find($originalResult->getArgument('.id'))
        );
    }
    
    public function testFindByCommaSeparatedValue()
    {
        $this->util->changeMenu('/queue/simple');
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
        $this->util->changeMenu('/queue/simple');
        $queues = $this->util->getall();
        $this->assertInstanceOf(ROS_NAMESPACE . '\ResponseCollection', $queues);
        $this->assertSameSize($queues, $this->util);
    }

    public function testInvalidGetallAndCount()
    {
        $this->util->changeMenu('/queue');
        $this->assertFalse($this->util->getall());
        $this->assertCount(-1, $this->util);
    }
}
