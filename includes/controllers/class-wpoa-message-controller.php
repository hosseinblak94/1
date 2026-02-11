<?php
defined('ABSPATH') || exit;

class WPOA_Message_Controller
{
    private WPOA_Message_Model      $message;
    private WPOA_Message_User_Model $message_user;
    private WPOA_Recipient_Model    $recipient;
    private WPOA_Tag_Model          $tag;
    private WPOA_Notification_Model $notification;

    public function __construct()
    {
        $this->message      = new WPOA_Message_Model();
        $this->message_user = new WPOA_Message_User_Model();
        $this->recipient    = new WPOA_Recipient_Model();
        $this->tag          = new WPOA_Tag_Model();
        $this->notification = new WPOA_Notification_Model();
    }

    public function save_draft(int $user_id, array $data): array
    {
        $message_id = absint($data['message_id'] ?? 0);

        if ($message_id > 0) {
            $this->message->update_draft($message_id, $data);
        } else {
            $message_id = $this->message->create_draft($user_id, $data);

            if (!$message_id) {
                return ['success' => false, 'message' => 'خطا در ایجاد پیش‌نویس.'];
            }

            $this->message_user->create_entry($message_id, $user_id, 'sender', 'drafts');
        }

        if (!empty($data['tags'])) {
            $this->tag->sync_message_tags($message_id, $data['tags']);
        }

        WPOA_Activity_Logger::log(
            WPOA_Activity_Model::ACTION_DRAFT_SAVED,
            'message',
            $message_id
        );

        return [
            'success'    => true,
            'message'    => 'پیش‌نویس ذخیره شد.',
            'message_id' => $message_id,
        ];
    }

    public function send(int $user_id, array $data): array
    {
        WPOA_Permission::verify_or_die('can_send_message');

        $message_id = absint($data['message_id'] ?? 0);

        if ($message_id > 0) {
            $this->message->update_draft($message_id, $data);
        } else {
            $message_id = $this->message->create_draft($user_id, $data);
            if (!$message_id) {
                return ['success' => false, 'message' => 'خطا در ایجاد نامه.'];
            }
        }

        $recipients = json_decode($data['recipients'] ?? '[]', true) ?: [];
        $cc         = json_decode($data['cc'] ?? '[]', true) ?: [];

        if (empty($recipients)) {
            return ['success' => false, 'message' => 'حداقل یک گیرنده انتخاب کنید.'];
        }

        $to_email_ids = json_decode($data['to_notify_email'] ?? '[]', true) ?: [];
        $to_sms_ids   = json_decode($data['to_notify_sms'] ?? '[]', true) ?: [];
        $cc_email_ids = json_decode($data['cc_notify_email'] ?? '[]', true) ?: [];
        $cc_sms_ids   = json_decode($data['cc_notify_sms'] ?? '[]', true) ?: [];

        $this->recipient->delete_for_message($message_id);

        foreach ($recipients as $uid) {
            $uid = absint($uid);
            $this->recipient->add(
                $message_id, $uid, 'to',
                in_array($uid, $to_email_ids),
                in_array($uid, $to_sms_ids)
            );
        }

        foreach ($cc as $uid) {
            $uid = absint($uid);
            $this->recipient->add(
                $message_id, $uid, 'cc',
                in_array($uid, $cc_email_ids),
                in_array($uid, $cc_sms_ids)
            );
        }

        if (!empty($data['tags'])) {
            $this->tag->sync_message_tags($message_id, $data['tags']);
        }

        $doc_number = $this->message->send($message_id, $user_id);

        if (!$doc_number) {
            return ['success' => false, 'message' => 'خطا در ارسال نامه.'];
        }

        $this->message_user->update_folder($user_id, $message_id, 'sent');

        foreach ($recipients as $uid) {
            $this->message_user->create_entry($message_id, absint($uid), 'to', 'inbox');
        }
        foreach ($cc as $uid) {
            $this->message_user->create_entry($message_id, absint($uid), 'cc', 'inbox');
        }

        $this->queue_notifications($message_id, $recipients, $cc, $to_email_ids, $to_sms_ids, $cc_email_ids, $cc_sms_ids);

        $reply_to_id  = absint($data['reply_to_id'] ?? 0);
        $forward_from = absint($data['forward_from_id'] ?? 0);
        $parent_id    = $reply_to_id ?: $forward_from;

        if ($parent_id > 0) {
            $this->message->set_thread($message_id, $parent_id);

            $log_action = $reply_to_id
                ? WPOA_Activity_Model::ACTION_MESSAGE_REPLIED
                : WPOA_Activity_Model::ACTION_MESSAGE_FORWARDED;

            WPOA_Activity_Logger::log($log_action, 'message', $message_id);
        }

        WPOA_Activity_Logger::log(
            WPOA_Activity_Model::ACTION_MESSAGE_SENT,
            'message',
            $message_id,
            $data['title'] ?? ''
        );

        return [
            'success'           => true,
            'message'           => 'نامه با موفقیت ارسال شد.',
            'system_doc_number' => $doc_number,
            'message_id'        => $message_id,
        ];
    }

    private function queue_notifications(
        int   $message_id,
        array $recipients,
        array $cc,
        array $to_email_ids,
        array $to_sms_ids,
        array $cc_email_ids,
        array $cc_sms_ids
    ): void {
        $msg = $this->message->get_by_id($message_id);
        if (!$msg) return;

        $subject = 'نامه جدید: ' . ($msg->title ?: '(بدون عنوان)');
        $body    = sprintf('نامه‌ای با عنوان «%s» برای شما ارسال شده است.', $msg->title);

        foreach ($recipients as $uid) {
            $uid = absint($uid);
            if (in_array($uid, $to_email_ids)) {
                $this->notification->queue($uid, $message_id, 'email', [
                    'subject' => $subject,
                    'body'    => $body,
                ]);
            }
            if (in_array($uid, $to_sms_ids)) {
                $this->notification->queue($uid, $message_id, 'sms', [
                    'subject' => $subject,
                    'body'    => $body,
                ]);
            }
        }

        foreach ($cc as $uid) {
            $uid = absint($uid);
            if (in_array($uid, $cc_email_ids)) {
                $this->notification->queue($uid, $message_id, 'email', [
                    'subject' => '(رونوشت) ' . $subject,
                    'body'    => $body,
                ]);
            }
            if (in_array($uid, $cc_sms_ids)) {
                $this->notification->queue($uid, $message_id, 'sms', [
                    'subject' => '(رونوشت) ' . $subject,
                    'body'    => $body,
                ]);
            }
        }
    }

    public function view(int $user_id, int $message_id): array
    {
        $message = $this->message_user->get_user_message($user_id, $message_id);

        if (!$message) {
            return ['success' => false, 'message' => 'نامه یافت نشد.'];
        }

        $this->message_user->mark_read($user_id, $message_id);

        WPOA_Activity_Logger::log(
            WPOA_Activity_Model::ACTION_MESSAGE_READ,
            'message',
            $message_id
        );

        $recipients  = $this->recipient->get_for_message($message_id);
        $attachments = (new WPOA_Attachment_Model())->get_for_message($message_id);
        $tags        = $this->tag->get_message_tags($message_id);

        return [
            'success'     => true,
            'message'     => $this->format_message($message),
            'recipients'  => $this->format_recipients($recipients),
            'attachments' => $this->format_attachments($attachments),
            'tags'        => $this->format_tags($tags),
        ];
    }

    public function prepare_reply(int $user_id, int $message_id, bool $reply_all = false): array
    {
        $original = $this->message_user->get_user_message($user_id, $message_id);

        if (!$original) {
            return ['success' => false, 'message' => 'نامه یافت نشد.'];
        }

        $title = 'پاسخ: ' . ($original->title ?: '');
        $body  = '<br><br><hr><blockquote>' . $original->body . '</blockquote>';

        $reply_recipients = [];
        if ((int) $original->sender_id !== $user_id) {
            $profile = new WPOA_User_Profile_Model();
            $sender  = $profile->get_by_user_id((int) $original->sender_id);
            if ($sender) {
                $reply_recipients[] = [
                    'user_id'      => (int) $original->sender_id,
                    'display_name' => $sender->display_name,
                    'avatar_url'   => $sender->avatar_url ?? '',
                ];
            }
        }

        $reply_cc = [];
        if ($reply_all) {
            $all_recipients = $this->recipient->get_for_message($message_id);
            foreach ($all_recipients as $r) {
                if ((int) $r->user_id === $user_id) continue;
                $already = false;
                foreach ($reply_recipients as $rr) {
                    if ($rr['user_id'] === (int) $r->user_id) { $already = true; break; }
                }
                if (!$already) {
                    $reply_cc[] = [
                        'user_id'      => (int) $r->user_id,
                        'display_name' => $r->display_name ?? '',
                        'avatar_url'   => $r->avatar_url ?? '',
                    ];
                }
            }
        }

        return [
            'success'    => true,
            'title'      => $title,
            'body'       => $body,
            'priority'   => $original->priority,
            'recipients' => $reply_recipients,
            'cc'         => $reply_cc,
            'reply_to_id'=> $message_id,
        ];
    }

    public function prepare_forward(int $user_id, int $message_id): array
    {
        $original = $this->message_user->get_user_message($user_id, $message_id);

        if (!$original) {
            return ['success' => false, 'message' => 'نامه یافت نشد.'];
        }

        $title = 'ارسال مجدد: ' . ($original->title ?: '');
        $body  = '<br><br><hr><blockquote>' . $original->body . '</blockquote>';

        $attachments = (new WPOA_Attachment_Model())->get_for_message($message_id);

        return [
            'success'              => true,
            'title'                => $title,
            'body'                 => $body,
            'priority'             => $original->priority,
            'internal_doc_number'  => $original->internal_doc_number ?? '',
            'original_attachments' => $this->format_attachments($attachments),
            'forward_from_id'      => $message_id,
        ];
    }

    private function format_message(object $m): array
    {
        return [
            'id'                  => (int) $m->message_id,
            'title'               => $m->title ?: '(بدون عنوان)',
            'body'                => $m->body,
            'priority'            => $m->priority,
            'status'              => $m->status,
            'system_doc_number'   => $m->system_doc_number ?? '',
            'internal_doc_number' => $m->internal_doc_number ?? '',
            'signature_type'      => $m->signature_type,
            'signature_text'      => $m->signature_text ?? '',
            'signature_image_url' => $m->signature_image_url ?? '',
            'internal_note'       => $m->internal_note ?? '',
            'sent_at_jalali'      => $m->sent_at_jalali ?? '',
            'thread_id'           => (int) ($m->thread_id ?? 0),
            'parent_id'           => (int) ($m->parent_id ?? 0),
            'is_read'             => (int) $m->is_read,
            'is_starred'          => (int) $m->is_starred,
            'is_pinned'           => (int) $m->is_pinned,
            'sender'              => [
                'user_id'      => (int) $m->sender_id,
                'display_name' => $m->sender_display_name ?? '',
                'avatar_url'   => $m->sender_avatar_url ?? '',
            ],
        ];
    }

    private function format_recipients(array $recipients): array
    {
        return array_map(fn($r) => [
            'user_id'      => (int) $r->user_id,
            'display_name' => $r->display_name ?? '',
            'avatar_url'   => $r->avatar_url ?? '',
            'type'         => $r->type,
        ], $recipients);
    }

    private function format_attachments(array $attachments): array
    {
        return array_map(fn($a) => [
            'id'        => (int) $a->id,
            'file_name' => $a->file_name,
            'file_url'  => $a->file_url,
            'file_size' => (int) $a->file_size,
            'file_type' => $a->file_type,
        ], $attachments);
    }

    private function format_tags(array $tags): array
    {
        return array_map(fn($t) => [
            'id'    => (int) $t->id,
            'name'  => $t->name,
            'color' => $t->color,
        ], $tags);
    }
}