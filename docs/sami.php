<?php
use Sami\Sami;
use Symfony\Component\Finder\Finder;

return new Sami(
    Finder::create()
        ->files()
        ->name('*.php')
        ->exclude('docs')
        ->exclude('tests')
        ->exclude('examples')
        ->in($dir = dirname(__DIR__)),
    array(
        'title'                => 'PEAR2_Net_RouterOS documentation',
        'build_dir'            => __DIR__ . '/Reference/Sami/Doc',
        'cache_dir'            => __DIR__ . '/Reference/Sami/Cache',
        'default_opened_level' => 1
    )
);
