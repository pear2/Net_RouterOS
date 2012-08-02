<?php
$extrafiles = array();

$phpDir = Pyrus\Config::current()->php_dir . DIRECTORY_SEPARATOR;
$packages = array('PEAR2/Autoload', 'PEAR2/Cache/SHM', 'PEAR2/Net/Transmitter');

foreach ($packages as $pkg) {
    $prefix = $phpDir . $pkg;
    
    if (is_dir($prefix)) {
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $prefix,
                    RecursiveDirectoryIterator::UNIX_PATHS
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            ) as $path
        ) {
            $pathname = $path->getPathname();
            $extrafiles['src/' . $pathname] = $pathname;
        }
    }
    
    if (is_file($prefix . '.php')) {
        $extrafiles['src/' . $pkg . '.php'] = $prefix . '.php';
    }
}