<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$client = new RouterOS\Client('192.168.0.1', 'admin');

$addRequest = new RouterOS\Request('/ip/arp/add');

$addRequest->setArgument('address', '192.168.0.100');
$addRequest->setArgument('mac-address', '00:00:00:00:00:01');
$addRequest->setTag('arp1');
$client->sendAsync($addRequest);

$addRequest->setArgument('address', '192.168.0.101');
$addRequest->setArgument('mac-address', '00:00:00:00:00:02');
$addRequest->setTag('arp2');
$client->sendAsync($addRequest);

foreach ($client->completeRequest('arp1') as $response) {
    if ($response->getType() === Response::TYPE_ERROR) {
        echo "Error response for 'arp1'!\n";
    }
}

foreach ($client->completeRequest('arp2') as $response) {
    if ($response->getType() === Response::TYPE_ERROR) {
        echo "Error response for 'arp2'!\n";
    }
}

echo 'OK';
