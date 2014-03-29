<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$client = new RouterOS\Client('192.168.0.1', 'admin');

$addRequest = new RouterOS\Request('/ip/arp/add');

$addRequest->setArgument('address', '192.168.0.100');
$addRequest->setArgument('mac-address', '00:00:00:00:00:01');
if ($client->sendSync($addRequest)->getType() !== Response::TYPE_FINAL) {
    die("Error when creating ARP entry for '192.168.0.100'");
}

$addRequest->setArgument('address', '192.168.0.101');
$addRequest->setArgument('mac-address', '00:00:00:00:00:02');
if ($client->sendSync($addRequest)->getType() !== Response::TYPE_FINAL) {
    die("Error when creating ARP entry for '192.168.0.101'");
}

echo 'OK';
