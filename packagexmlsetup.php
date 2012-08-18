<?php

$srcDirTask = array(
    'tasks:replace' => array(
        array(
            'attribs' => array(
                'from' => '../src',
                'to' => 'php_dir',
                'type' => 'pear-config'
            )
        )
    )
);
        
$srcFileTasks = array(
    'tasks:replace' => array(
        array(
            'attribs' => array(
                'from' => '~~summary~~',
                'to' => 'summary',
                'type' => 'package-info'
            )
        ),
        array(
            'attribs' => array(
                'from' => '~~description~~',
                'to' => 'description',
                'type' => 'package-info'
            )
        ),
        array(
            'attribs' => array(
                'from' => 'GIT: $Id$',
                'to' => 'version',
                'type' => 'package-info'
            )
        )
    )
);

$package->files['tests/bootstrap.php'] = array_merge_recursive(
    $package->files['tests/bootstrap.php']->getArrayCopy(), $srcDirTask,
    array(
        'tasks:replace' => array(
            array(
                'attribs' => array(
                    'from' => '../../PEAR2_Net_Transmitter.git/src/',
                    'to' => 'php_dir',
                    'type' => 'pear-config'
                )
            )
        )
    )
);

$package->files['docs/phpdoc.dist.xml'] = array_merge_recursive(
    $package->files['docs/phpdoc.dist.xml']->getArrayCopy(), $srcDirTask
);

$package->files['docs/doxygen.ini'] = array_merge_recursive(
    $package->files['docs/doxygen.ini']->getArrayCopy(), $srcDirTask,
    array(
        'tasks:replace' => array(
            array(
                'attribs' => array(
                    'from' => 'GIT: $Id$',
                    'to' => 'version',
                    'type' => 'package-info'
                )
            )
        )
    )
);
$hasCompatible = isset($compatible);
if ($hasCompatible) {
    $compatible->license = $package->license;
    $compatible->files[
        "test/{$package->channel}/{$package->name}/bootstrap.php"
        ] = array_merge_recursive(
            $compatible->files[
            "test/{$package->channel}/{$package->name}/bootstrap.php"
            ]->getArrayCopy(), $srcDirTask
        );

    $compatible->files[
        "doc/{$package->channel}/{$package->name}/phpdoc.dist.xml"
        ] = array_merge_recursive(
            $compatible->files[
            "doc/{$package->channel}/{$package->name}/phpdoc.dist.xml"
            ]->getArrayCopy(), $srcDirTask
        );

    $compatible->files["doc/{$package->channel}/{$package->name}/doxygen.ini"]
        = array_merge_recursive(
            $compatible->files[
            "doc/{$package->channel}/{$package->name}/doxygen.ini"
            ]->getArrayCopy(), $srcDirTask,
            array(
                'tasks:replace' => array(
                    array(
                        'attribs' => array(
                            'from' => 'GIT: $Id$',
                            'to' => 'version',
                            'type' => 'package-info'
                        )
                    )
                )
            )
        );
}

$oldCwd = getcwd();
chdir(__DIR__);
foreach (
    new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            'src',
            RecursiveDirectoryIterator::UNIX_PATHS
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    ) as $path) {
        $filename = $path->getPathname();
        
        $package->files[$filename] = array_merge_recursive(
            $package->files[$filename]->getArrayCopy(), $srcFileTasks
        );
        
    if ($hasCompatible) {
        $compatibleFilename = str_replace('src/', 'php/', $filename);
        $compatible->files[$compatibleFilename] = array_merge_recursive(
            $compatible->files[$compatibleFilename]->getArrayCopy(),
            $srcFileTasks
        );
    }
}
chdir($oldCwd);