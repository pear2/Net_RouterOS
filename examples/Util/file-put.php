<?php
use PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util(
    $client = new RouterOS\Client('192.168.88.1', 'admin', 'password')
);

$filename = 'backup.auto.rsc';
$util->filePutContents($filename, file_get_contents($filename));
