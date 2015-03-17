<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);
$util->setMenu('/ip arp');

foreach ($util->getAll() as $item) {
    echo 'IP: ', $item->getProperty('address'),
         ' MAC: ', $item->getProperty('mac-address'),
         "\n";
}
