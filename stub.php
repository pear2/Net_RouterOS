<?php
if (count(get_included_files()) > 1) {
    Phar::mapPhar();
    include_once 'phar://' . __FILE__ . DIRECTORY_SEPARATOR .
        '@PACKAGE_NAME@-@PACKAGE_VERSION@' . DIRECTORY_SEPARATOR . 'src'
        . DIRECTORY_SEPARATOR . 'PEAR2' . DIRECTORY_SEPARATOR . 'Autoload.php';
} else {
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
    foreach (array('phar', 'spl', 'pcre') as $ext) {
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
            echo "{$sig['hash_type']} hash: {$sig['hash']}\n\n";
        } catch (Exception $e) {
            echo <<<HEREDOC
The PHAR extension is available, but was unable to read this PHAR file's hash.
Regardless, you should not be having any trouble using the package by directly
including the archive.

Exception details:
HEREDOC
                . $e . "\n\n";
        }
    } else {
        echo <<<HEREDOC
If you wish to use this package directly from this archive, you need to install
and enable the PHAR extension. Otherwise, you must instead extract this archive,
and include the autoloader.


HEREDOC;
    }
    
    if (function_exists('stream_socket_client')) {
        echo <<<HEREDOC
The stream_socket_client() function is enabled.\n
If you can't connect to RouterOS (SocketException with code 100), this means one
of the following:\n
1. You haven't enabled the API service at RouterOS or you've enabled it on a
different TCP port. Make sure that the "api" service at "/ip services" is
enabled, and with that same TCP port (8728 by default).\n
2. You've mistyped the IP and/or port. Check the IP and port you've specified
are the one you intended.\n
3. The router is not reachable from your web server. Try to reach the router
from the web server by other means (e.g. Winbox, ping) using the same IP, and if
you're unable to reach it, check your network's settings.\n
2. Your web server is configured to forbid that outgoing connection. If you're
the web server administrator, check your firewall's settings. If you're on a
hosting plan... Typically, shared hosts block all outgoing connections, but it's
also possible that only connections to that port are blocked. Try to connect to
a host on a popular port (21, 80, etc.), and if successful, change the API
service port to that port. If the connection fails even then, ask your host to
configure their firewall so as to allow you to make outgoing connections to the
ip:port you've set the API service on.
HEREDOC;
    } else {
        echo <<<HEREDOC
WARNING: stream_socket_client() is disabled. Without it, you won't be able to
connect to any RouterOS host. Enable it in php.ini, or ask your host to enable
it for you.
HEREDOC;
    }
    
}

__HALT_COMPILER();