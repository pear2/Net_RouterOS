<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

try {
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password');
} catch (Exception $e) {
    die('Unable to connect to the router.');
}

$addRequest = new RouterOS\Request('/ip/arp/add');

$addRequest->setArgument('address', '192.168.88.100');
$addRequest->setArgument('mac-address', '00:00:00:00:00:01');
$addRequest->setTag('arp1');
$client->sendAsync($addRequest);

$addRequest->setArgument('address', '192.168.88.101');
$addRequest->setArgument('mac-address', '00:00:00:00:00:02');
$addRequest->setTag('arp2');
$client->sendAsync($addRequest);

$client->loop();

$responses = $client->extractNewResponses();
foreach ($responses as $response) {
    if ($response->getType() !== Response::TYPE_FINAL) {
        echo "Error with {$response->getTag()}!\n";
    } else {
        echo "OK with {$response->getTag()}!\n";
    }
}
//Example output:
/*
OK with arp1
OK with arp2
 */
