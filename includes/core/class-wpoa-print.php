<?php
defined('ABSPATH') || exit;

class WPOA_Print
{
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_print_request']);
    }

    public function handle_print_request(): void
    {
        if (empty($_GET['wpoa_print'])) {
            return;
        }

        if (!current_user_can('read')) {
            wp_die('شما دسترسی ندارید.');
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'wpoa_print')) {
            wp_die('توکن امنیتی نامعتبر است.');
        }

        if (!WPOA_Permission::can('can_print')) {
            wp_die('شما مجوز چاپ را ندارید.');
        }

        $message_id = absint($_GET['wpoa_print']);
        $user_id    = get_current_user_id();

        $msg_user = new WPOA_Message_User_Model();
        $message  = $msg_user->get_user_message($user_id, $message_id);

        if (!$message) {
            wp_die('نامه یافت نشد یا دسترسی ندارید.');
        }

        $recipient_model = new WPOA_Recipient_Model();
        $recipients      = $recipient_model->get_for_message($message_id);

        $attachment_model = new WPOA_Attachment_Model();
        $attachments      = $attachment_model->get_for_message($message_id);

        $tag_model = new WPOA_Tag_Model();
        $tags      = $tag_model->get_message_tags($message_id);

        $settings_model = new WPOA_Settings_Model();
        $org_name       = $settings_model->get('org_name', 'سازمان');

        WPOA_Activity_Logger::log('message_print', 'message', $message_id);

        include WPOA_PLUGIN_DIR . 'includes/views/print-letter.php';
        exit;
    }
}