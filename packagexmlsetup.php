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
                'from' => 'SVN: $WCREV$',
                'to' => 'version',
                'type' => 'package-info'
            )
        )
    )
);

$compatible->license = $package->license;

$package->files['tests/bootstrap.php'] = array_merge_recursive(
    $package->files['tests/bootstrap.php']->getArrayCopy(), $srcDirTask
);

$package->files['docs/docblox.xml'] = array_merge_recursive(
    $package->files['docs/docblox.xml']->getArrayCopy(), $srcDirTask
);

$package->files['docs/doxygen.ini'] = array_merge_recursive(
    $package->files['docs/doxygen.ini']->getArrayCopy(), $srcDirTask,
    array(
        'tasks:replace' => array(
            array(
                'attribs' => array(
                    'from' => 'SVN: $WCREV$',
                    'to' => 'version',
                    'type' => 'package-info'
                )
            )
        )
    )
);

$compatible->files[
    "test/{$package->channel}/{$package->name}/bootstrap.php"
    ] = array_merge_recursive(
        $compatible->files[
        "test/{$package->channel}/{$package->name}/bootstrap.php"
        ]->getArrayCopy(), $srcDirTask
);

$compatible->files["doc/{$package->channel}/{$package->name}/docblox.xml"]
    = array_merge_recursive(
        $compatible->files[
        "doc/{$package->channel}/{$package->name}/docblox.xml"
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
                        'from' => 'SVN: $WCREV$',
                        'to' => 'version',
                        'type' => 'package-info'
                    )
                )
            )
        )
);

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
        
        $compatibleFilename = str_replace('src/', 'php/', $filename);
        $compatible->files[$compatibleFilename] = array_merge_recursive(
            $compatible->files[$compatibleFilename]->getArrayCopy(),
            $srcFileTasks
        );
}
chdir($oldCwd);

?>
