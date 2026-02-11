<?php
defined('ABSPATH') || exit;

class WPOA_Plugin
{
    protected WPOA_Loader $loader;

    public function __construct()
    {
        $this->maybe_upgrade_db();
        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_ajax_hooks();
        $this->define_cron_hooks();
    }

    private function maybe_upgrade_db(): void
    {
        $installed = get_option('wpoa_db_version', '0.0.0');
        if (version_compare($installed, WPOA_VERSION, '<')) {
            WPOA_Installer::install();
        }
    }

    private function load_dependencies(): void
    {
        $this->loader = new WPOA_Loader();
    }

    private function set_locale(): void
    {
        $i18n = new WPOA_i18n();
        $this->loader->add_action('plugins_loaded', $i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks(): void
    {
        $admin = new WPOA_Admin();
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_assets');
        $this->loader->add_action('admin_menu', $admin, 'register_admin_menu');

        add_action('admin_init', function () {
            if (is_user_logged_in()) {
                (new WPOA_User_Profile_Model())->ensure_profile(get_current_user_id());
            }
        });

        $dashboard = new WPOA_Dashboard_Widget();
        $dashboard->register();

        $admin_bar = new WPOA_Admin_Bar();
        $admin_bar->register();

        $print = new WPOA_Print();
        $print->register();
    }

    private function define_ajax_hooks(): void
    {
        $ajax = new WPOA_Ajax();
        $this->loader->add_action('init', $ajax, 'register_hooks');
    }

    private function define_cron_hooks(): void
    {
        add_filter('cron_schedules', function (array $schedules): array {
            $schedules['wpoa_every_five_minutes'] = [
                'interval' => 300,
                'display'  => 'هر ۵ دقیقه',
            ];
            return $schedules;
        });

        add_action('init', function () {
            if (!wp_next_scheduled('wpoa_process_notifications')) {
                wp_schedule_event(time(), 'wpoa_every_five_minutes', 'wpoa_process_notifications');
            }
        });

        add_action('wpoa_process_notifications', function () {
            $notification_model = new WPOA_Notification_Model();
            $notification_model->process_queue(50);

            $referral_model = new WPOA_Referral_Model();
            $referral_model->expire_overdue();
        });
    }

    public function run(): void
    {
        $this->loader->run();
    }
}