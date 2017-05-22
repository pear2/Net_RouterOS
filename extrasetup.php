<?php

/**
 * File extrasetup.php for PEAR2_Net_RouterOS.
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

$packages = array(
    'pear2.php.net' => array(
        'PEAR2_Autoload',
        'PEAR2_Cache_SHM',
        'PEAR2_Net_Transmitter',
        'PEAR2_Console_CommandLine',
        'PEAR2_Console_Color'
    )
);

$extrafiles = array();
$config = Pyrus\Config::current();
$registry = $config->registry;
$phpDir = $config->php_dir;
$dataDir = $config->data_dir;

foreach ($packages as $channel => $channelPackages) {
    foreach ($channelPackages as $package) {
        foreach ($registry->toPackage($package, $channel)->installcontents
            as $file => $info) {
            if (strpos($file, 'php/') === 0 || strpos($file, 'src/') === 0) {
                $filename = substr($file, 4);
                $extrafiles['src/' . $filename]
                    = realpath($phpDir . DIRECTORY_SEPARATOR . $filename);
            } elseif (strpos($file, 'data/') === 0) {
                $filename = substr($file, 5);
                $extrafiles["data/{$channel}/{$package}/{$filename}"]
                    = realpath($dataDir . DIRECTORY_SEPARATOR . $channel . DIRECTORY_SEPARATOR . $package . DIRECTORY_SEPARATOR . $filename);
            }
        }
    }
}
