<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);
$util->setMenu('/ip arp');

$util->exec(
    '
add address=192.168.88.100 mac-address=00:00:00:00:00:01 comment=customer_1
add address=192.168.88.101 mac-address=00:00:00:00:00:02 comment=customer_2
/tool
fetch url="http://example.com/?name=customer_1"
fetch url="http://example.com/?name=customer_2"
    '
);
