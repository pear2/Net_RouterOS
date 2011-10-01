<?php

$transmitterPackage
    = new \PEAR2\Pyrus\Package(
        __DIR__ . DIRECTORY_SEPARATOR
        . '../../PEAR2_Net_Transmitter@sourceforge.net/trunk/package.xml'
    );
unset($transmitterPackage->files['docs/docblox.xml']);
unset($transmitterPackage->files['docs/doxygen.ini']);
unset($transmitterPackage->files['tests/configuration.xml']);
unset($transmitterPackage->files['tests/bootstrap.php']);
$extrafiles = array($transmitterPackage);