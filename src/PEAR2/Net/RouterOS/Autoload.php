<?php

/**
 * ~~summary~~
 * 
 * ~~description~~
 * 
 * PHP version 5
 * 
 * @category  Net
 * @package   PEAR2_Net_RouterOS
 * @author    Vasil Rangelov <boen.robot@gmail.com>
 * @copyright 2011 Vasil Rangelov
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @version   SVN: $WCREV$
 * @link      http://netrouteros.sourceforge.net/
 */
/**
 * The namespace declaration.
 */
namespace PEAR2\Net\RouterOS;

/**
 * Loads a specified class.
 * 
 * Loads a specified class from the namespace.
 * 
 * @param string $class The classname (with namespace) to load.
 * 
 * @return void
 */
function autoload($class)
{
    $namespace = __NAMESPACE__ . '\\';
    if (strpos($class, $namespace) === 0) {
        $path = __DIR__ . DIRECTORY_SEPARATOR .
            strtr(
                substr($class, strlen($namespace)), '\\', DIRECTORY_SEPARATOR
            ) . '.php';
        $file = realpath($path);
        if (is_string($file) && strpos($file, __DIR__) === 0) {
            include_once $file;
        }
    }
}

spl_autoload_register(__NAMESPACE__ . '\autoload', true);