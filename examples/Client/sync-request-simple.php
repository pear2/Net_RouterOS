<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

try {
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password');
} catch (Exception $e) {
    die('Unable to connect to the router.');
    //Inspect $e if you want to know details about the failure.
}

$responses = $client->sendSync(new RouterOS\Request('/ip/arp/print'));

foreach ($responses as $response) {
    if ($response->getType() === Response::TYPE_DATA) {
        echo 'IP: ', $response->getProperty('address'),
        ' MAC: ', $response->getProperty('mac-address'),
        "\n";
    }
}
//Example output:
/*
IP: 192.168.88.100 MAC: 00:00:00:00:00:01
IP: 192.168.88.101 MAC: 00:00:00:00:00:02
 */
