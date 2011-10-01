<?php
namespace PEAR2\Net\RouterOS;

require_once 'PEAR2/Net/RouterOS/Autoload.php';

$client = new Client('192.168.0.1', 'admin');

$responses = $client->sendSync(new Request('/ip/arp/print'));

foreach ($responses as $response) {
    if ($response->getType() === Response::TYPE_DATA) {
        echo 'IP: ', $response->getArgument('address'),
        ' MAC: ', $response->getArgument('mac-address'),
        "\n";
    }
}
//Example output:
/*
IP: 192.168.0.100 MAC: 00:00:00:00:00:01
IP: 192.168.0.101 MAC: 00:00:00:00:00:02
 */
?>
