<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

try {
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password');
} catch (Exception $e) {
    die('Unable to connect to the router.');
}

//Custom function, defined specifically for the example
$responseHandler = function ($response) {
    if ($response->getType() === Response::TYPE_FINAL) {
        echo "{$response->getTag()} is done.\n";
    }
};

$addRequest = new RouterOS\Request('/ip/arp/add');

$addRequest->setArgument('address', '192.168.88.100');
$addRequest->setArgument('mac-address', '00:00:00:00:00:01');
$addRequest->setTag('arp1');
$client->sendAsync($addRequest, $responseHandler);

$addRequest->setArgument('address', '192.168.88.101');
$addRequest->setArgument('mac-address', '00:00:00:00:00:02');
$addRequest->setTag('arp2');
$client->sendAsync($addRequest, $responseHandler);

$client->loop();
//Example output:
/*
arp1 is done.
arp2 is done.
*/
