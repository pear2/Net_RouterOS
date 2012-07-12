<?php
$extrafiles = array();

foreach (
    array(
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PEAR2_Net_Transmitter.git',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PEAR2_Cache_SHM.git'
    ) as $packageRoot
) {
    $pkg = new \Pyrus\Package(
        $packageRoot . DIRECTORY_SEPARATOR . 'package.xml'
    );
    foreach (array('tests', 'docs') as $folder) {
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $packageRoot . DIRECTORY_SEPARATOR . $folder,
                    RecursiveDirectoryIterator::UNIX_PATHS
                ),
                RecursiveIteratorIterator::LEAVES_ONLY
            ) as $path
        ) {
            unset($pkg->files[$path->getPathname()]);
        }
    }
    $extrafiles[] = $pkg;
}

//$transmitterPackage
//    = new \Pyrus\Package(
//        __DIR__ . DIRECTORY_SEPARATOR
//        . '../PEAR2_Net_Transmitter.git/package.xml'
//    );
//unset($transmitterPackage->files['docs/docblox.xml']);
//unset($transmitterPackage->files['docs/doxygen.ini']);
//
//unset($transmitterPackage->files['tests/ClientTest.php']);
//unset($transmitterPackage->files['tests/ServerTest.php']);
//unset($transmitterPackage->files['tests/UnconnectedTest.php']);
//unset($transmitterPackage->files['tests/bootstrap.php']);
//unset($transmitterPackage->files['tests/phpunit.xml']);
//unset($transmitterPackage->files['tests/secondaryPeer.xml']);
//unset($transmitterPackage->files['tests/secondaryPeer.bat']);
//
//$shmPackage
//    = new \Pyrus\Package(
//        __DIR__ . DIRECTORY_SEPARATOR
//        . '../PEAR2_Cache_SHM.git/package.xml'
//    );
//unset($shmPackage->files['docs/phpdoc.dist.xml']);
//unset($shmPackage->files['docs/doxygen.ini']);
//
//unset($shmPackage->files['tests/bootstrap.php']);
//unset($shmPackage->files['tests/phpunit.xml']);
//
//$extrafiles = array($transmitterPackage, $shmPackage);