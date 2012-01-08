<?php
namespace PEAR2\Net\RouterOS;
require_once
    '../../PEAR2_Net_Transmitter.git/src/PEAR2/Net/Transmitter/Autoload.php';
require_once '../src/PEAR2/Net/RouterOS/Autoload.php';

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
            var_dump(define($newConstant, $hostname['ip']));
            var_dump(defined($newConstant));
            continue 3;
        }
    }
}