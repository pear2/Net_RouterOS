<?php
namespace PEAR2\Net\RouterOS;

require_once 'PEAR2/Autoload.php';
\PEAR2\Autoload::initialize(realpath('../src'));
\PEAR2\Autoload::initialize(realpath('../../Net_Transmitter.git/src'));
\PEAR2\Autoload::initialize(realpath('../../Cache_SHM.git/src'));

define('ROS_NAMESPACE', __NAMESPACE__);

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
    $value = resolve(constant($constant));
    define(
        __NAMESPACE__ . '\Client\Test\\' . $constant,
        $value
    );
    define(
        __NAMESPACE__ . '\Util\Test\\' . $constant,
        $value
    );
    define(
        __NAMESPACE__ . '\Misc\Test\\' . $constant,
        $value
    );
}
