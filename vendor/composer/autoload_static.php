<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit5accfcbe58e04a39f992f12e11a34e42
{
    public static $files = array (
        'decc78cc4436b1292c6c0d151b19445c' => __DIR__ . '/..' . '/phpseclib/phpseclib/phpseclib/bootstrap.php',
    );

    public static $prefixLengthsPsr4 = array (
        'p' => 
        array (
            'phpseclib\\' => 10,
        ),
        'G' => 
        array (
            'Gitplus\\' => 8,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'phpseclib\\' => 
        array (
            0 => __DIR__ . '/..' . '/phpseclib/phpseclib/phpseclib',
        ),
        'Gitplus\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit5accfcbe58e04a39f992f12e11a34e42::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit5accfcbe58e04a39f992f12e11a34e42::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit5accfcbe58e04a39f992f12e11a34e42::$classMap;

        }, null, ClassLoader::class);
    }
}
