<?php

/**
 * File scanoptions.php for PEAR2_Net_RouterOS.
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

$scanoptions = array(
    'ignore' => array()
);
$oldCwd = getcwd();
chdir(__DIR__);
foreach (new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        '.',
        RecursiveDirectoryIterator::UNIX_PATHS
        | RecursiveDirectoryIterator::SKIP_DOTS
    ),
    RecursiveIteratorIterator::LEAVES_ONLY
) as $path) {
    if ('.git' === $path->getFilename()) {
        $scanoptions['ignore'][$path->getRealPath()]
            = $path->isDir() ? 'dir' : 'file';
    }
}
chdir($oldCwd);
return $scanoptions;
