<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);
$util->setMenu('/queue simple');
$util->move(2, 0);//Place the queue at position 2 above the queue at position 0

//Place the queues at positions 3 and 4 above the queue at position 0
//(the same one that was at position 2, before it was moved above)
$util->move($util->find(3, 4), 0);
