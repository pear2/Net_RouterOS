<?php
namespace PEAR2\Net\RouterOS;

//require_once
//    '../../PEAR2_Net_Transmitter.git/src/PEAR2/Net/Transmitter/Autoload.php';
//require_once '../src/PEAR2/Net/RouterOS/Autoload.php';
require_once 'PEAR2/Autoload.php';
\PEAR2\Autoload::initialize(realpath('../src'));
\PEAR2\Autoload::initialize(realpath('../../Net_Transmitter.git/src'));
\PEAR2\Autoload::initialize(realpath('../../Cache_SHM.git/src'));

/**
 * Resolves a hostname to an IP address.
 * 
 * Resolves a hostname to an IP address. Used instead of gethostbyname() in
 * order to enable resolutions to IPv6 hosts.
 * 
 * @param string $hostname Hostname to resolve.
 * 
 * @return string An IP (v4 or v6) for this hostname.
 */
function resolve($hostname)
{
    $info = dns_get_record($hostname);
    foreach ($info as $entry) {
        switch($entry['type']) {
        case 'A':
            return $entry['ip'];
        case 'AAAA':
        case 'A6':
            return $entry['ipv6'];
        case 'CNAME':
            if ($entry['host'] === $hostname) {
                return resolve($entry['target']);
            }
        }
    }
}

//Resolving HOSTNAME_* constants
$constants = array('HOSTNAME', 'HOSTNAME_INVALID', 'HOSTNAME_SILENT');
foreach ($constants as $constant) {
    define(__NAMESPACE__ . '\\' . $constant, resolve(constant($constant)));
}
