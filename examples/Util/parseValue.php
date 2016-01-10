<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);

$util->setMenu('/system resource');
$uptime = RouterOS\Script::parseValue($util->get(null, 'uptime'));

$now = new DateTime;

//Will output something akin to 'Running since: Sunday, 18 Aug 2013 14:03:01'
echo 'Running since: ' . $now->sub($uptime)->format(DateTime::COOKIE);
