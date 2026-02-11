<?php
defined('ABSPATH') || exit;

class WPOA_Dashboard_Widget
{
    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'add_widget']);
    }

    public function add_widget(): void
    {
        if (!current_user_can('read')) {
            return;
        }

        wp_add_dashboard_widget(
            'wpoa_dashboard_widget',
            'اتوماسیون اداری — نامه‌های اخیر',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        include WPOA_PLUGIN_DIR . 'includes/views/dashboard-widget.php';
    }
}