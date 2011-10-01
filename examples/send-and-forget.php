<?php
namespace PEAR2\Net\RouterOS;

require_once 'PEAR2/Net/RouterOS/Autoload.php';

$client = new Client('192.168.0.1', 'admin');

$addRequest = new Request('/ip/arp/add');

$addRequest->setArgument('address', '192.168.0.100');
$addRequest->setArgument('mac-address', '00:00:00:00:00:01');
$addRequest->setTag('arp1');
$client->sendAsync($addRequest);

$addRequest->setArgument('address', '192.168.0.101');
$addRequest->setArgument('mac-address', '00:00:00:00:00:02');
$addRequest->setTag('arp2');
$client->sendAsync($addRequest);
?>
