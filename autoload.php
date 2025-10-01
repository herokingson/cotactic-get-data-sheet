<?php
/**
 * Simple Autoloader for Google API Client
 * ใช้แทน Composer autoload
 */
spl_autoload_register(function($class) {
    $base_dir = plugin_dir_path(__FILE__) . 'google-api-client/src/';
    $file = $base_dir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
