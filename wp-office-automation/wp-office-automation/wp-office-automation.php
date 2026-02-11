<?php
/**
 * Plugin Name: اتوماسیون اداری و نامه‌نگاری وردپرس
 * Plugin URI:  https://example.com
 * Description: سیستم اتوماسیون اداری و نامه‌نگاری داخلی در پیشخوان وردپرس — ارجاع، تأیید، حاشیه‌نویسی، چارت سازمانی
 * Version:     1.0.0
 * Author:      Mahla Chat
 * Author URI:  mailto:mahlachat@gmail.com
 * Text Domain: wpoa
 * Domain Path: /languages
 * Requires PHP: 8.1
 */

defined('ABSPATH') || exit;

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>';
        echo esc_html('افزونه اتوماسیون اداری نیاز به PHP 8.1 یا بالاتر دارد.');
        echo '</p></div>';
    });
    return;
}

define('WPOA_VERSION',         '1.0.0');
define('WPOA_PLUGIN_FILE',     __FILE__);
define('WPOA_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WPOA_PLUGIN_DIR',      plugin_dir_path(__FILE__));
define('WPOA_PLUGIN_URL',      plugin_dir_url(__FILE__));

require_once WPOA_PLUGIN_DIR . 'includes/class-wpoa-autoloader.php';
WPOA_Autoloader::register();

add_action('plugins_loaded', function () {
    $plugin = new WPOA_Plugin();
    $plugin->run();
});

register_activation_hook(__FILE__, function () {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    WPOA_Installer::install();
    update_option('wpoa_version', WPOA_VERSION);
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    $ts = wp_next_scheduled('wpoa_process_notifications');
    if ($ts) {
        wp_unschedule_event($ts, 'wpoa_process_notifications');
    }
});

register_uninstall_hook(__FILE__, 'wpoa_uninstall_callback');
function wpoa_uninstall_callback(): void
{
    if (!current_user_can('delete_plugins')) {
        return;
    }
    WPOA_Installer::uninstall();
}
require_once WPOA_PLUGIN_DIR . 'includes/ajax-handlers.php';