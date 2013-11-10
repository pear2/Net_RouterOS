#!/usr/bin/env php
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

$hasArgs = $argc > 1;
if (count(get_included_files()) > 1 || $hasArgs) {
    Phar::mapPhar();
    $pkgDir = 'phar://' . __FILE__ . DIRECTORY_SEPARATOR .
            '@PACKAGE_NAME@-@PACKAGE_VERSION@' . DIRECTORY_SEPARATOR;

    include_once $pkgDir . DIRECTORY_SEPARATOR
        . 'src' . DIRECTORY_SEPARATOR
        . 'PEAR2' . DIRECTORY_SEPARATOR
        . 'Autoload.php';

    //Run console if there are any arguments
    if ($hasArgs) {
        include_once $pkgDir . DIRECTORY_SEPARATOR
            . 'bin' . DIRECTORY_SEPARATOR
            . 'roscon.php';
    }
    unset($pkgDir, $hasArgs);
    return;
}

$isNotCli = PHP_SAPI !== 'cli';
if ($isNotCli) {
    header('Content-Type: text/plain;charset=UTF-8');
}
echo "@PACKAGE_NAME@ @PACKAGE_VERSION@\n";

if (version_compare(phpversion(), '5.3.0', '<')) {
    echo "\nThis package requires PHP 5.3.0 or later.";
    exit(1);
}

$missing_extensions = array();
foreach (array('spl', 'pcre') as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}
if ($missing_extensions) {
    echo "\nYou must compile PHP with the following extensions enabled:\n",
        implode(', ', $missing_extensions), "\n",
        "or install the necessary extensions for your distribution.\n";
    exit(1);
}

if (extension_loaded('phar')) {
    try {
        $phar = new Phar(__FILE__);
        $sig = $phar->getSignature();
        echo "{$sig['hash_type']} hash: {$sig['hash']}\n";
    } catch (Exception $e) {
        echo <<<HEREDOC
The PHAR extension is available, but was unable to read this PHAR file's hash.
Regardless, you should not be having any trouble using the package by directly
including this file. In the unlikely case that you can't include it
successfully, you can instead extract one of the other archives, and include
its autoloader.

Exception details:

HEREDOC
            . $e . "\n";
    }
} else {
    echo <<<HEREDOC
If you wish to use this package directly from this archive, you need to install
and enable the PHAR extension. Otherwise, you must instead extract this
archive, and include the autoloader.

HEREDOC;
}

echo "\n" . str_repeat('=', 80) . "\n";
if (extension_loaded('openssl')) {
    echo <<<HEREDOC
The OpenSSL extension is loaded. If you can make any connection whatsoever, you
could also make an encrypted one to RouterOS 6.1 or later.

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
If you can't connect to RouterOS (SocketException with code equal to
SocketException::CODE_CONNECTION_FAIL), this means one of the following:

1. You haven't enabled the API service at RouterOS or you've enabled it on a
different TCP port. Make sure that the "api" service at "/ip service" is
enabled, and with that same TCP port (8728 by default).

2. You've mistyped the IP and/or port. Check the IP and port you've specified
are the ones you intended.

3. The router is not reachable from your web server for some reason. Try to
reach the router (!!!)from the web server(!!!) by other means (e.g. Winbox,
ping) using the same IP, and if you're unable to reach it, check the network
settings on your server, router and any intermediate nodes under your control
that may affect the connection.

4. Your web server is configured to forbid that outgoing connection. If you're
the web server administrator, check your web server's firewall's settings. If
you're on a hosting plan... Typically, shared hosts block all outgoing
connections, but it's also possible that only connections to that port are
blocked. Try to connect to a host on a popular port (21, 80, 443, etc.), and if
successful, change the API service port to that port. If the connection fails
even then, ask your host to configure their firewall so as to allow you to make
outgoing connections to the ip:port you've set the API service on.

5. The router has a filter/mangle/dst-nat rule that overrides the settings at
"/ip service". This is a very rare scenario, but if you want to be sure, try to
disable all rules that may cause such a thing, or (if you can afford it) set up
a fresh RouterOS in place of the existing one, and see if you can connect to it
instead. If you still can't connect, such a rule is certainly not the (only)
reason.

HEREDOC;
} else {
    echo <<<HEREDOC
WARNING: stream_socket_client() is disabled. Without it, you won't be able to
connect to any RouterOS host. Enable it in php.ini, or ask your host to enable
it for you.

HEREDOC;
}

echo "\n" . str_repeat('=', 80) . "\n";
echo <<<HEREDOC
This package provides a console. To see usage instructions, rerun this file
from the command line with "--help" as an argument.

HEREDOC;

__HALT_COMPILER();