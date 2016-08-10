<?php

namespace PEAR2\Net\RouterOS\Test\Util;

use PEAR2\Net\RouterOS\Client;
use PEAR2\Net\RouterOS\InvalidArgumentException;
use PEAR2\Net\RouterOS\NotSupportedException;
use PEAR2\Net\RouterOS\Query;
use PEAR2\Net\RouterOS\Request;
use PEAR2\Net\RouterOS\Response;
use PEAR2\Net\RouterOS\RouterErrorException;
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
        $this->assertSame('/', $this->util->setMenu('/')->getMenu());
        $this->assertSame('/', $this->util->setMenu('')->getMenu());
        $this->assertSame(
            '/queue',
            $this->util->setMenu('queue')->getMenu()
        );
        $this->assertSame(
            '/',
            $this->util->setMenu('..')->getMenu()
        );
        $this->assertSame(
            '/',
            $this->util->setMenu('queue ..')->getMenu()
        );
        $this->assertSame(
            '/queue',
            $this->util->setMenu('/queue')->getMenu()
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

    public function testNewRequest()
    {
        //Simple request
        $request = $this->util->setMenu('/queue simple')->newRequest('print');
        $this->assertInstanceOf(ROS_NAMESPACE . '\Request', $request);
        $this->assertSame('/queue/simple/print', $request->getCommand());
        $this->assertEmpty($request->getIterator()->getArrayCopy());
        $this->assertNull($request->getQuery());
        $this->assertNull($request->getTag());

        //Complex request
        $request = $this->util->setMenu('/queue simple')->newRequest(
            'print',
            array('detail', 'from'=> TEST_QUEUE_NAME),
            Query::where('target', HOSTNAME_INVALID . '/32'),
            'test'
        );
        $this->assertInstanceOf(ROS_NAMESPACE . '\Request', $request);
        $this->assertSame('/queue/simple/print', $request->getCommand());
        $this->assertSame(
            array(
                'detail' => '',
                'from'=> TEST_QUEUE_NAME
            ),
            $request->getIterator()->getArrayCopy()
        );
        $this->assertInstanceOf(ROS_NAMESPACE . '\Query', $request->getQuery());
        $this->assertSame('test', $request->getTag());

        //Failed request (API syntax)
        try {
            $request = $this->util->setMenu('/queue simple')->newRequest(
                '../tree/print',
                array('detail'),
                Query::where('target', HOSTNAME_INVALID . '/32'),
                'test'
            );
            $this->fail('Exception for invalid request not thrown');
        } catch (NotSupportedException $e) {
            $this->assertSame(
                NotSupportedException::CODE_MENU_MISMATCH,
                $e->getCode()
            );
            $this->assertSame(
                '../tree/print',
                $e->getValue()
            );
        }
        
        //Failed request (CLI syntax)
        try {
            $request = $this->util->setMenu('/queue simple')->newRequest(
                '.. tree print',
                array('detail'),
                Query::where('target', HOSTNAME_INVALID . '/32'),
                'test'
            );
            $this->fail('Exception for invalid request not thrown');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(
                InvalidArgumentException::CODE_CMD_INVALID,
                $e->getCode()
            );
        }
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

        $this->client->setStreamingResponses(true);
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

        $this->client->setStreamingResponses(true);
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

        $this->client->setStreamingResponses(true);
        $this->util->setMenu('/queue/simple');
        $findResults = $this->util->find(
            function ($entry) {
                return stream_get_contents(
                    $entry->getProperty('target')
                ) === HOSTNAME_INVALID . '/32';
            }
        );
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $findResults
        );
        $this->assertSame(
            $findResults,
            stream_get_contents(
                $this->client->sendSync(
                    new Request(
                        '/queue/simple/print',
                        Query::where('target', HOSTNAME_INVALID . '/32')
                    )
                )->getProperty('.id')
            )
        );
    }
    
    public function testFindByCallbackName()
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

        $this->client->setStreamingResponses(true);
        $this->util->setMenu('/queue/simple');
        $findResults = $this->util->find('isHostnameInvalid');
        $this->assertRegExp(
            '/^' . self::REGEX_ID . '$/',
            $findResults
        );
        $this->assertSame(
            $findResults,
            stream_get_contents(
                $this->client->sendSync(
                    new Request(
                        '/queue/simple/print',
                        Query::where('target', HOSTNAME_INVALID . '/32')
                    )
                )->getProperty('.id')
            )
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

        $this->client->setStreamingResponses(true);
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


        $this->client->setStreamingResponses(true);
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

        $this->client->setStreamingResponses(true);
        $this->util->setMenu('/queue/simple');
        $queues = $this->util->getAll();
        $this->assertInstanceOf(ROS_NAMESPACE . '\ResponseCollection', $queues);
        $this->assertSameSize($queues, $this->util);

        $this->client->setStreamingResponses(false);
        $this->util->setMenu('/');
        try {
            $this->util->getAll();
            $this->fail(
                'There should not be "print" at the root menu'
            );
        } catch (RouterErrorException $e) {
            $this->assertSame(RouterErrorException::CODE_GETALL_ERROR, $e->getCode());
            $this->assertInstanceof(ROS_NAMESPACE . '\ResponseCollection', $e->getResponses());
            $this->assertSame(Response::TYPE_ERROR, $e->getResponses()->getType());
        }
    }

    public function testInvalidCount()
    {
        $this->util->setMenu('/queue');
        $this->assertCount(-1, $this->util);
    }

    public function providerProhibitedArgs()
    {
        return array(
            'follow'        => array(array('follow'), 0),
            'follow-only'   => array(array('follow-only'), 0),
            'count-only'    => array(array('count-only'), 0)
        );
    }

    /**
     * @param array $args Arguments for the Util::getAll() call.
     *
     * @return void
     *
     * @dataProvider providerProhibitedArgs
     */
    public function testGetallArgExceptions(array $args, $argKey)
    {
        $this->util->setMenu('/queue simple');
        try {
            $this->util->getAll($args);
            $this->fail('Supplying these arguments should result in an exception');
        } catch (NotSupportedException $e) {
            $this->assertSame($args[$argKey], $e->getValue());
            $this->assertSame(
                NotSupportedException::CODE_ARG_PROHIBITED,
                $e->getCode()
            );
        }
    }
}
