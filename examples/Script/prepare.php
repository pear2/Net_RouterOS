<?php
use PEAR2\Net\RouterOS;
require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);
$util->setMenu('/system scheduler')->add(
    array(
        'name' => 'logger',
        'interval' => '1m',
        'on-event' => RouterOS\Script::prepare(
            '/log info $phpver',
            array(
                'phpver' => phpversion()
            )
        )
    )
);
