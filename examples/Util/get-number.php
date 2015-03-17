<?php
use PEAR2\Net\RouterOS;
require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util($client = new RouterOS\Client('192.168.88.1', 'admin', 'password'));
$util->setMenu('/ip arp');
echo $util->get(0, 'address');//echoes "192.168.88.1", assuming we had the previous example executed under an empty ARP list
