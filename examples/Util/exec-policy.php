<?php
use PEAR2\Net\RouterOS;
require_once 'PEAR2/Autoload.php';

$util = new RouterOS\Util($client = new RouterOS\Client('192.168.88.1', 'admin', 'password'));
$util->setMenu('/tool');

$url = $_GET['url'];//assume $_GET['url'] equals something akin to "http://example.com/geoip.rsc?filter=all"...

$source = '
fetch url=$db keep-result=yes dst-path=$filename
# Give the script time to be written onto disk
:delay 2
/import file=$filename
';
$util->exec(
    $source,
    array(
        'db' => $url,
        'filename' => pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME) //... then this would be equal to "geoip.rsc"
    ),
    'read,write'
);