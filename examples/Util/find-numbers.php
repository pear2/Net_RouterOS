<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);
$util->setMenu('/ip arp');

//Outputs something similar to "*4de,*16a", since we targeted two entries:
//the one in position 0 and position 1. 
echo $util->find(0, 1);
