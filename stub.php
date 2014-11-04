<?php

/**
 * Stub for PEAR2_Net_RouterOS.
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

if (count(get_included_files()) > 1 || ('cli' === PHP_SAPI && $argc > 1)) {
    Phar::mapPhar();

    include_once 'phar://' . __FILE__ . DIRECTORY_SEPARATOR
        . '@PACKAGE_NAME@-@PACKAGE_VERSION@' . DIRECTORY_SEPARATOR
        . 'src' . DIRECTORY_SEPARATOR
        . 'PEAR2' . DIRECTORY_SEPARATOR
        . 'Autoload.php';

    //Run console if there are any arguments,
    //and we are running directly.
    if ('cli' === PHP_SAPI && $argc > 1 && 2 === count(get_included_files())) {
        include_once 'phar://' . __FILE__ . DIRECTORY_SEPARATOR
            . '@PACKAGE_NAME@-@PACKAGE_VERSION@' . DIRECTORY_SEPARATOR
            . 'scripts' . DIRECTORY_SEPARATOR
            . 'roscon.php';
    }
    return;
}

if ('cli' !== PHP_SAPI) {
    header('Content-Type: text/plain;charset=UTF-8');
}
echo "@PACKAGE_NAME@ @PACKAGE_VERSION@\n";

if (version_compare(phpversion(), '5.3.0', '<')) {
    echo "\nERROR: This package requires PHP 5.3.0 or later.\n";
    exit(1);
}

$missing_extensions = array();
foreach (array('spl', 'pcre') as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}
if ($missing_extensions) {
    echo "\nERROR: You must compile PHP with the following extensions enabled:\n",
        implode(', ', $missing_extensions), "\n",
        "or install the necessary extensions for your distribution.\n";
    exit(1);
}

$supportsPhar = extension_loaded('phar');
if ($supportsPhar) {
    try {
        $phar = new Phar(__FILE__);
        $sig = $phar->getSignature();
        echo "{$sig['hash_type']} hash: {$sig['hash']}\n";
    } catch (Exception $e) {
        echo <<<HEREDOC

The PHAR extension is available, but was unable to read this PHAR file's hash.

HEREDOC;
        if (false !== strpos($e->getMessage(), 'file extension')) {
            echo <<<HEREDOC
This can happen if you've renamed the file to ".php" instead of ".phar".
Regardless, you should be able to include this file without problems.
HEREDOC;
        } else {
            echo 'Details: ' . $e->getMessage();
        }
    }
} else {
    echo <<<HEREDOC
WARNING: If you wish to use this package directly from this archive, you need
         to install and enable the PHAR extension. Otherwise, you must instead
         extract this archive, and include the autoloader.

HEREDOC;
}

echo "\n" . str_repeat('=', 80) . "\n";
if (extension_loaded('openssl')) {
    echo <<<HEREDOC
The OpenSSL extension is loaded. If you can make any connection whatsoever, you
should also be able to make an encrypted one to RouterOS 6.1 or later.

Note that due to known issues with PHP itself, encrypted connections may be
unstable (as in "sometimes disconnect suddenly" or "sometimes hang when you use
Client::sendSync() and/or Client::completeRequest() and/or Client::loop()
without a timeout").

HEREDOC;
} else {
    echo <<<HEREDOC
WARNING: The OpenSSL extension is not loaded.
         You can't make encrypted connections without it.

HEREDOC;
}

echo "\n" . str_repeat('=', 80) . "\n";
if (function_exists('stream_socket_client')) {
    echo <<<HEREDOC
The stream_socket_client() function is enabled.
If you can't connect to RouterOS from your code, try to connect using the API
console. Make sure to check your web server's firewall.

HEREDOC;
} else {
    echo <<<HEREDOC
WARNING: stream_socket_client() is disabled.
         Without it, you won't be able to connect to any RouterOS host.
         Enable it in php.ini, or ask your host to enable it for you.

HEREDOC;
}

echo "\n" . str_repeat('=', 80) . "\n";
$supportsResolver = function_exists('stream_resolve_include_path');
if (!$supportsPhar && !$supportsResolver) {
    echo <<<HEREDOC

WARNING: You can't use the API console in any way.
         If you want to use it, you must enable the PHAR extension
         (compiled into PHP by default) and/or the
         stream_resolve_include_path() function (available since PHP 5.3.2).

HEREDOC;
} else {
    if ($supportsPhar) {
        echo <<<HEREDOC
You can access the console by rerunning this file from the command line with
arguments. To see usage instructions, use the "--help" argument.

HEREDOC;
    }
    if ($supportsResolver) {
        echo <<<HEREDOC
Note that if you extract this PHAR file (or install it with Pyrus, PEAR or
Composer), you can also use the console through the "roscon" executable file.

HEREDOC;
    } else {
        echo <<<HEREDOC
WARNING: You can ONLY use the console through the PHAR file, because the
         stream_resolve_include_path() function is not available.

HEREDOC;
    }
}
__HALT_COMPILER();