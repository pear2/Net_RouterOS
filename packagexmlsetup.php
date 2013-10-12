<?php

/**
 * packagexmlsetup.php for PEAR2_Net_RouterOS.
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

/**
 * References the package in $package
 */
use Pyrus\Developer\PackageFile\v2;

$packageGen = function (
    v2 $package,
    v2 $compatible = null
) {
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
    $verTask = array(
        'tasks:replace' => array(
            array(
                'attribs' => array(
                    'from' => '../../PEAR2_Net_Transmitter.git/src/',
                    'to' => 'php_dir',
                    'type' => 'pear-config'
                )
            )
        )
    );
    $srcFileTasks = array_merge_recursive(
        array(
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
                )
            )
        ),
        $verTask
    );

    $package->files['data/roscon.xml'] = array_merge_recursive(
        $package->files['data/roscon.xml']->getArrayCopy(),
        $verTask
    );

    $package->files['tests/bootstrap.php'] = array_merge_recursive(
        $package->files['tests/bootstrap.php']->getArrayCopy(),
        $srcDirTask,
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
        $package->files['docs/phpdoc.dist.xml']->getArrayCopy(),
        $srcDirTask
    );
    $package->files['docs/apigen.neon'] = array_merge_recursive(
        $package->files['docs/apigen.neon']->getArrayCopy(),
        $srcDirTask
    );
    $package->files['docs/doxygen.ini'] = array_merge_recursive(
        $package->files['docs/doxygen.ini']->getArrayCopy(),
        $srcDirTask,
        $verTask
    );

    $hasCompatible = null !== $compatible;
    if ($hasCompatible) {
        $compatible->license = $package->license;
        $compatible->files[
            "test/{$package->channel}/{$package->name}/bootstrap.php"
            ] = array_merge_recursive(
                $compatible->files[
                "test/{$package->channel}/{$package->name}/bootstrap.php"
                ]->getArrayCopy(),
                $srcDirTask,
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

        $compatible->files[
            "doc/{$package->channel}/{$package->name}/phpdoc.dist.xml"
            ] = array_merge_recursive(
                $compatible->files[
                "doc/{$package->channel}/{$package->name}/phpdoc.dist.xml"
                ]->getArrayCopy(),
                $srcDirTask
            );

        $compatible->files["doc/{$package->channel}/{$package->name}/doxygen.ini"]
            = array_merge_recursive(
                $compatible->files[
                "doc/{$package->channel}/{$package->name}/doxygen.ini"
                ]->getArrayCopy(),
                $srcDirTask,
                $verTask
            );
    }

    $oldCwd = getcwd();
    chdir(__DIR__);
    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            'src',
            RecursiveDirectoryIterator::UNIX_PATHS
            | RecursiveDirectoryIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    ) as $path) {
            $filename = $path->getPathname();

            $package->files[$filename] = array_merge_recursive(
                $package->files[$filename]->getArrayCopy(),
                $srcFileTasks
            );

        if ($hasCompatible) {
            $compatibleFilename = str_replace('src/', 'php/', $filename);
            $compatible->files[$compatibleFilename] = array_merge_recursive(
                $compatible->files[$compatibleFilename]->getArrayCopy(),
                $srcFileTasks
            );
        }
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            '.',
            RecursiveDirectoryIterator::UNIX_PATHS
            | RecursiveDirectoryIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    ) as $path) {
            $filename = substr($path->getPathname(), 2);

        if (isset($package->files[$filename])) {
            $as = (strpos($filename, 'examples') === 0)
                ? $filename
                : substr($filename, strpos($filename, '/') + 1);
            $package->getReleaseToInstall('php')->installAs($filename, $as);
        }
    }
    chdir($oldCwd);
    return array($package, $compatible);
};

list($package, $compatible) = $packageGen(
    $package,
    isset($compatible) ? $compatible : null
);
if (null === $compatible) {
    unset($compatible);
}
