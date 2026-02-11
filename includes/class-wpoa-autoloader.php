<?php
defined('ABSPATH') || exit;

class WPOA_Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    public static function autoload(string $class): void
    {
        if (strpos($class, 'WPOA_') !== 0) {
            return;
        }

        $file_name = 'class-' . strtolower(str_replace('_', '-', $class)) . '.php';

        $paths = [
            WPOA_PLUGIN_DIR . 'includes/' . $file_name,
            WPOA_PLUGIN_DIR . 'includes/core/' . $file_name,
            WPOA_PLUGIN_DIR . 'includes/models/' . $file_name,
            WPOA_PLUGIN_DIR . 'includes/controllers/' . $file_name,
            WPOA_PLUGIN_DIR . 'includes/helpers/' . $file_name,
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                return;
            }
        }
    }
}