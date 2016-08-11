<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);
$util->setMenu('/ip arp');

//With function
echo count($util) . "\n";

//With method call
echo $util->count() . "\n";

//Count only disabled ARP items
echo $util->count(RouterOS\Query::where('disabled', 'true')) . "\n";
