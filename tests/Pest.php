<?php

namespace {
    require_once __DIR__ . '/../vendor/autoload.php';

    if (!class_exists('AppData')) {
        class_alias(\Janmensik\Jmlib\AppData::class, 'AppData');
    }
    if (!class_exists('Modul')) {
        class_alias(\Janmensik\Jmlib\Modul::class, 'Modul');
    }
}

namespace PozarniPoplach {
    /**
     * Shadowing global mysqli_real_escape_string for tests.
     */
    if (!function_exists('PozarniPoplach\mysqli_real_escape_string')) {
        function mysqli_real_escape_string($mysqli, $string) {
            if (is_object($mysqli) && method_exists($mysqli, 'real_escape_string')) {
                return $mysqli->real_escape_string($string);
            }
            return addslashes($string);
        }
    }
}

namespace {
    /*
    |--------------------------------------------------------------------------
    | Expectations
    |--------------------------------------------------------------------------
    */

    expect()->extend('toBeOne', function () {
        return $this->toBe(1);
    });

    /*
    |--------------------------------------------------------------------------
    | Functions
    |--------------------------------------------------------------------------
    */

    function mockApp() {
        // Setup environment for testing
        $_ENV['ABSOLUTE_URL'] = 'http://localhost';
        
        $APPD = \Janmensik\Jmlib\AppData::getInstance();
        
        return $APPD;
    }

    function clearAppData() {
        $refl = new ReflectionClass(\Janmensik\Jmlib\AppData::class);
        $instance = $refl->getProperty('instance');
        $instance->setValue(null, null);
        return \Janmensik\Jmlib\AppData::getInstance();
    }
}
