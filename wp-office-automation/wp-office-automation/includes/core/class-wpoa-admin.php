<?php
defined('ABSPATH') || exit;

class WPOA_Admin
{
    public function enqueue_assets(string $hook_suffix): void
    {
        if (strpos($hook_suffix, 'wpoa') === false) {
            return;
        }

        wp_enqueue_style('dashicons');

        wp_enqueue_style(
            'wpoa-admin-rtl',
            WPOA_PLUGIN_URL . 'assets/css/admin-rtl.css',
            [],
            WPOA_VERSION
        );

        if (strpos($hook_suffix, 'wpoa-compose') !== false) {
            wp_enqueue_editor();
        }

        wp_enqueue_script(
            'wpoa-admin-js',
            WPOA_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WPOA_VERSION,
            true
        );

        $user_id   = get_current_user_id();
        $msg_user  = new WPOA_Message_User_Model();
        $ref_model = new WPOA_Referral_Model();

        wp_localize_script('wpoa-admin-js', 'WPOA', [
            'ajax_url'     => admin_url('admin-ajax.php'),
            'nonce'        => wp_create_nonce('wpoa_ajax_nonce'),
            'print_nonce'  => wp_create_nonce('wpoa_print'),
            'admin_url'    => admin_url('admin.php'),
            'user_id'      => $user_id,
            'is_admin'     => current_user_can('manage_options'),
            'can_refer'    => WPOA_Permission::can('can_refer'),
            'can_approve'  => WPOA_Permission::can('can_approve'),
            'compose_url'  => admin_url('admin.php?page=wpoa-compose'),
            'inbox_url'    => admin_url('admin.php?page=wpoa-inbox'),
            'unread_count' => $msg_user->count_unread($user_id),
            'ref_count'    => $ref_model->count_pending($user_id),
        ]);
    }

    public function register_admin_menu(): void
    {
        $user_id   = get_current_user_id();
        $msg_user  = new WPOA_Message_User_Model();
        $unread    = $msg_user->count_unread($user_id);
        $badge     = $unread > 0 ? ' <span class="awaiting-mod">' . $unread . '</span>' : '';

        add_menu_page(
            'اتوماسیون اداری',
            'اتوماسیون اداری' . $badge,
            'read',
            'wpoa-inbox',
            [$this, 'page_inbox'],
            'dashicons-email-alt',
            26
        );

        add_submenu_page('wpoa-inbox', 'صندوق دریافت', 'صندوق دریافت' . $badge, 'read',
            'wpoa-inbox', [$this, 'page_inbox']);

        add_submenu_page('wpoa-inbox', 'ارسال‌شده', 'ارسال‌شده', 'read',
            'wpoa-sent', [$this, 'page_sent']);

        add_submenu_page('wpoa-inbox', 'نامه جدید', 'نامه جدید', 'read',
            'wpoa-compose', [$this, 'page_compose']);

        $ref_model   = new WPOA_Referral_Model();
        $ref_pending = $ref_model->count_pending($user_id);
        $ref_badge   = $ref_pending > 0 ? ' <span class="awaiting-mod">' . $ref_pending . '</span>' : '';

        add_submenu_page('wpoa-inbox', 'ارجاعات من', 'ارجاعات من' . $ref_badge, 'read',
            'wpoa-referrals', [$this, 'page_referrals']);

        add_submenu_page('wpoa-inbox', 'ارجاعات ارسالی', 'ارجاعات ارسالی', 'read',
            'wpoa-referrals-sent', [$this, 'page_referrals_sent']);

        add_submenu_page('wpoa-inbox', 'پروفایل سازمانی', 'پروفایل سازمانی', 'read',
            'wpoa-profile', [$this, 'page_profile']);

        add_submenu_page('wpoa-inbox', 'مدیریت سازمان', 'مدیریت سازمان', 'manage_options',
            'wpoa-org', [$this, 'page_org']);

        add_submenu_page('wpoa-inbox', 'مدیریت کاربران', 'مدیریت کاربران', 'manage_options',
            'wpoa-users', [$this, 'page_users']);

        // Hidden page for editing/creating users (no menu item)
        add_submenu_page(null, 'ویرایش کاربر', '', 'manage_options',
            'wpoa-user-edit', [$this, 'page_user_edit']);

        add_submenu_page('wpoa-inbox', 'گزارش فعالیت‌ها', 'گزارش فعالیت‌ها', 'manage_options',
            'wpoa-activity', [$this, 'page_activity']);

        add_submenu_page('wpoa-inbox', 'تنظیمات', 'تنظیمات', 'manage_options',
            'wpoa-settings', [$this, 'page_settings']);
    }

    public function page_inbox(): void
    {
        if (!current_user_can('read')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/inbox.php';
    }

    public function page_sent(): void
    {
        if (!current_user_can('read')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/inbox.php';
    }

    public function page_compose(): void
    {
        if (!current_user_can('read')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/compose.php';
    }

    public function page_referrals(): void
    {
        if (!current_user_can('read')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/inbox.php';
    }

    public function page_referrals_sent(): void
    {
        if (!current_user_can('read')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/inbox.php';
    }

    public function page_profile(): void
    {
        if (!current_user_can('read')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/profile.php';
    }

    public function page_org(): void
    {
        if (!current_user_can('manage_options')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/org-management.php';
    }

    public function page_users(): void
    {
        if (!current_user_can('manage_options')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/user-management.php';
    }

    public function page_user_edit(): void
    {
        if (!current_user_can('manage_options')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/user-edit.php';
    }

    public function page_activity(): void
    {
        if (!current_user_can('manage_options')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/activity-log.php';
    }

    public function page_settings(): void
    {
        if (!current_user_can('manage_options')) { wp_die('شما دسترسی ندارید.'); }
        include WPOA_PLUGIN_DIR . 'includes/views/settings.php';
    }
}