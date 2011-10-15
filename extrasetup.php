<?php

$transmitterPackage
    = new \PEAR2\Pyrus\Package(
        __DIR__ . DIRECTORY_SEPARATOR
        . '../PEAR2_Net_Transmitter.git/package.xml'
    );
unset($transmitterPackage->files['docs/docblox.xml']);
unset($transmitterPackage->files['docs/doxygen.ini']);

unset($transmitterPackage->files['tests/ClientTest.php']);
unset($transmitterPackage->files['tests/ServerTest.php']);
unset($transmitterPackage->files['tests/UnconnectedTest.php']);
unset($transmitterPackage->files['tests/bootstrap.php']);
unset($transmitterPackage->files['tests/phpunit.xml']);
unset($transmitterPackage->files['tests/secondaryPeer.xml']);
unset($transmitterPackage->files['tests/secondaryPeer.bat']);

$extrafiles = array($transmitterPackage);