<?php
defined('ABSPATH') || exit;

class WPOA_Admin_Bar
{
    public function register(): void
    {
        add_action('admin_bar_menu', [$this, 'add_node'], 999);
        add_action('admin_head', [$this, 'inline_css']);
    }

    public function add_node(\WP_Admin_Bar $wp_admin_bar): void
    {
        if (!is_user_logged_in() || !current_user_can('read')) {
            return;
        }

        $user_id = get_current_user_id();
        $model   = new WPOA_Message_User_Model();
        $count   = $model->count_unread($user_id);

        $badge = '';
        if ($count > 0) {
            $badge = '<span class="wpoa-ab-badge">' . (int) $count . '</span>';
        }

        $wp_admin_bar->add_node([
            'id'    => 'wpoa-inbox-link',
            'title' => '<span class="ab-icon dashicons dashicons-email"></span>'
                     . '<span class="ab-label">نامه‌ها</span>' . $badge,
            'href'  => admin_url('admin.php?page=wpoa-inbox'),
            'meta'  => [
                'title' => sprintf('صندوق دریافت (%d خوانده‌نشده)', $count),
            ],
        ]);
    }

    public function inline_css(): void
    {
        ?>
        <style>
            #wp-admin-bar-wpoa-inbox-link .ab-icon.dashicons {
                font-size: 18px;
                margin-top: 2px;
            }
            #wp-admin-bar-wpoa-inbox-link .ab-label {
                font-family: Tahoma, sans-serif;
            }
            .wpoa-ab-badge {
                display: inline-block;
                background: #d63638;
                color: #fff;
                border-radius: 10px;
                padding: 0 6px;
                font-size: 10px;
                line-height: 18px;
                margin-right: 4px;
                vertical-align: top;
                margin-top: 5px;
            }
        </style>
        <?php
    }
}