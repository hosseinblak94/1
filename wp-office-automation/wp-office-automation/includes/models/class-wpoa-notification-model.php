<?php
defined('ABSPATH') || exit;

class WPOA_Notification_Model extends WPOA_Model
{
    protected string $table_suffix = 'notification_queue';

    public function queue(
        int    $user_id,
        int    $message_id,
        string $channel,
        array  $content
    ): int|false {
        $wp_user   = get_userdata($user_id);
        $recipient = '';

        if ($channel === 'email' && $wp_user) {
            $recipient = $wp_user->user_email;
        } elseif ($channel === 'sms') {
            $profile   = new WPOA_User_Profile_Model();
            $p         = $profile->get_by_user_id($user_id);
            $recipient = $p->phone ?? '';
        }

        if (empty($recipient)) {
            return false;
        }

        return $this->insert([
            'user_id'    => $user_id,
            'message_id' => $message_id,
            'channel'    => $this->validate_enum($channel, ['email', 'sms'], 'email'),
            'recipient'  => sanitize_text_field($recipient),
            'subject'    => sanitize_text_field($content['subject'] ?? ''),
            'body'       => sanitize_textarea_field($content['body'] ?? ''),
            'status'     => 'pending',
        ]);
    }

    public function process_queue(int $batch_size = 50): int
    {
        $pending = $this->query(
            "SELECT * FROM {$this->table()}
             WHERE status = 'pending' AND scheduled_at <= NOW()
             ORDER BY created_at ASC
             LIMIT %d",
            [$batch_size]
        );

        $processed = 0;

        foreach ($pending as $item) {
            $sent = false;

            if ($item->channel === 'email') {
                $sent = $this->send_email($item);
            } elseif ($item->channel === 'sms') {
                $sent = $this->send_sms($item);
            }

            if ($sent) {
                $this->update(
                    ['status' => 'sent', 'sent_at' => current_time('mysql')],
                    ['id' => $item->id]
                );
                $processed++;
            } else {
                $attempts = (int) $item->attempts + 1;
                $status   = ($attempts >= 3) ? 'failed' : 'pending';

                $this->update(
                    ['attempts' => $attempts, 'status' => $status, 'last_error' => 'Send failed'],
                    ['id' => $item->id]
                );
            }
        }

        return $processed;
    }

    private function send_email(object $item): bool
    {
        $settings = new WPOA_Settings_Model();

        if ($settings->get('email_notifications_enabled', '1') !== '1') {
            return false;
        }

        $org_name = $settings->get('org_name', 'سازمان');

        $html = '<!DOCTYPE html><html dir="rtl" lang="fa"><head><meta charset="UTF-8"></head>';
        $html .= '<body style="font-family:Tahoma,sans-serif;direction:rtl;text-align:right;background:#f5f5f5;padding:20px;">';
        $html .= '<div style="max-width:600px;margin:0 auto;background:#fff;border:1px solid #ddd;border-radius:6px;overflow:hidden;">';
        $html .= '<div style="background:#0073aa;color:#fff;padding:15px 20px;font-size:16px;">' . esc_html($org_name) . ' — اتوماسیون اداری</div>';
        $html .= '<div style="padding:25px;line-height:2;font-size:14px;">' . nl2br(esc_html($item->body)) . '</div>';
        $html .= '<div style="padding:15px 20px;background:#f9f9f9;border-top:1px solid #eee;font-size:11px;color:#888;text-align:center;">';
        $html .= 'این ایمیل به صورت خودکار ارسال شده است.</div>';
        $html .= '</div></body></html>';

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $org_name . ' <' . get_option('admin_email') . '>',
        ];

        return wp_mail($item->recipient, $item->subject, $html, $headers);
    }

    private function send_sms(object $item): bool
    {
        $settings = new WPOA_Settings_Model();

        if ($settings->get('sms_notifications_enabled', '0') !== '1') {
            return false;
        }

        $result = apply_filters('wpoa_send_sms', false, [
            'to'       => $item->recipient,
            'message'  => $item->body,
            'provider' => $settings->get('sms_api_provider', ''),
            'api_key'  => $settings->get('sms_api_key', ''),
            'sender'   => $settings->get('sms_sender_number', ''),
        ]);

        return (bool) $result;
    }
}