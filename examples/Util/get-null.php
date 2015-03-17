<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);
$util->setMenu('/system identity');

//echoes "MikroTik", assuming you've never altered your router's identity.
echo $util->get(null, 'name');
