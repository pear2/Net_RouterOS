<?php
namespace PEAR2\Net\RouterOS;
//require_once
//    '../../PEAR2_Net_Transmitter.git/src/PEAR2/Net/Transmitter/Autoload.php';
//require_once '../src/PEAR2/Net/RouterOS/Autoload.php';
require_once 'PEAR2/Autoload.php';
\PEAR2\Autoload::initialize('../src');
\PEAR2\Autoload::initialize('../../PEAR2_Net_Transmitter.git/src');
\PEAR2\Autoload::initialize('../../PEAR2_Cache_SHM.git/src');

//Resolving HOSTNAME_* constants
$constants = array('HOSTNAME', 'HOSTNAME_INVALID', 'HOSTNAME_SILENT');
foreach ($constants as $constant) {
    $hostnames = dns_get_record(constant($constant));
    foreach ($hostnames as $hostname) {
        switch($hostname['type']) {
        case 'A':
        case 'AAAA':
        case 'A6':
            $newConstant = __NAMESPACE__ . '\\' . $constant;
            define($newConstant, $hostname['ip']);
            continue 3;
        }
    }
}