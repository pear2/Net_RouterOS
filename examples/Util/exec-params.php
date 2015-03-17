<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);
$util->setMenu('/ip arp');

$source = '
add address="192.168.88.$ip" mac-address="00:00:00:00:00:$mac" comment=$name
/tool
fetch url="http://example.com/?name=$name"
';
$util->exec(
    $source,
    array(
        'ip' => 100,
        'mac' => '01',
        'name' => 'customer_1'
    )
);
$util->exec(
    $source,
    array(
        'ip' => 101,
        'mac' => '02',
        'name' => 'customer_2'
    )
);
