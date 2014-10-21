<?php

/**
 * bootstrap.php for PEAR2_Net_RouterOS.
 * 
 * PHP version 5.3
 * 
 * @category  Net
 * @package   PEAR2_Net_RouterOS
 * @author    Vasil Rangelov <boen.robot@gmail.com>
 * @copyright 2011 Vasil Rangelov
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @version   GIT: $Id$
 * @link      http://pear2.php.net/PEAR2_Net_RouterOS
 */

namespace PEAR2\Net\RouterOS;

/**
 * Possible autoloader to initialize.
 */
use PEAR2\Autoload;

chdir(__DIR__);

$autoloader = stream_resolve_include_path('../vendor/autoload.php');
if (false !== $autoloader) {
    include_once $autoloader;
} else {
    $autoloader = stream_resolve_include_path('PEAR2/Autoload.php');
    if (false !== $autoloader) {
        include_once $autoloader;
        Autoload::initialize(realpath('../src'));
        Autoload::initialize(realpath('../../Net_Transmitter.git/src'));
        Autoload::initialize(realpath('../../Cache_SHM.git/src'));
    } else {
        die('No recognized autoloader is available.');
    }
}
unset($autoloader);

$defineConstants = !defined('ROS_NAMESPACE');
if ($defineConstants) {
    define('ROS_NAMESPACE', __NAMESPACE__);
}

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
$resolve = function ($hostname) use (&$resolve) {
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
                return $resolve($entry['target']);
            }
        }
    }
};

if ($defineConstants) {
    //Resolving HOSTNAME_* constants
    $constants = array('HOSTNAME', 'HOSTNAME_INVALID', 'HOSTNAME_SILENT');
    foreach ($constants as $constant) {
        $value = $resolve(constant($constant));
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
}
