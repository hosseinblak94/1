<?php
defined('ABSPATH') || exit;

class WPOA_Referral_Controller
{
    private WPOA_Referral_Model     $referral;
    private WPOA_Margin_Note_Model  $margin;
    private WPOA_Notification_Model $notification;
    private WPOA_Message_Model      $message;
    private WPOA_Message_User_Model $message_user;

    public function __construct()
    {
        $this->referral     = new WPOA_Referral_Model();
        $this->margin       = new WPOA_Margin_Note_Model();
        $this->notification = new WPOA_Notification_Model();
        $this->message      = new WPOA_Message_Model();
        $this->message_user = new WPOA_Message_User_Model();
    }

    public function create_referral(array $data, int $from_user_id): array
    {
        WPOA_Permission::verify_or_die('can_refer');

        $to_user_id = absint($data['to_user_id'] ?? 0);
        $message_id = absint($data['message_id'] ?? 0);

        if (!$to_user_id || !$message_id) {
            return ['success' => false, 'message' => 'اطلاعات ناقص است.'];
        }

        if ($to_user_id === $from_user_id) {
            return ['success' => false, 'message' => 'امکان ارجاع به خود وجود ندارد.'];
        }

        $ref_id = $this->referral->create([
            'message_id'      => $message_id,
            'from_user_id'    => $from_user_id,
            'to_user_id'      => $to_user_id,
            'type'            => $data['type'] ?? 'referral',
            'instruction'     => $data['instruction'] ?? '',
            'deadline'        => $data['deadline'] ?? null,
            'deadline_jalali' => $data['deadline_jalali'] ?? null,
            'parent_ref_id'   => $data['parent_ref_id'] ?? null,
        ]);

        if (!$ref_id) {
            return ['success' => false, 'message' => 'خطا در ایجاد ارجاع.'];
        }

        $this->message_user->create_entry($message_id, $to_user_id, 'to', 'inbox');

        $msg = $this->message->get_by_id($message_id);
        $type_label = WPOA_Referral_Model::TYPE_LABELS[$data['type'] ?? 'referral'] ?? 'ارجاع';

        $this->notification->queue($to_user_id, $message_id, 'email', [
            'subject' => sprintf('%s: %s', $type_label, $msg->title ?? ''),
            'body'    => sprintf('نامه‌ای با عنوان «%s» به شما %s شده است.', $msg->title ?? '', $type_label),
        ]);

        WPOA_Activity_Logger::log('referral_created', 'referral', $ref_id, $type_label);

        return [
            'success'     => true,
            'message'     => $type_label . ' با موفقیت ثبت شد.',
            'referral_id' => $ref_id,
        ];
    }

    public function respond(int $referral_id, int $user_id, string $status, string $response = ''): array
    {
        $result = $this->referral->respond($referral_id, $user_id, $status, $response);

        if (!$result) {
            return ['success' => false, 'message' => 'خطا در ثبت پاسخ یا دسترسی ندارید.'];
        }

        $ref       = $this->referral->get_full($referral_id);
        $status_fa = WPOA_Referral_Model::STATUS_LABELS[$status] ?? $status;

        if ($ref) {
            $this->notification->queue((int) $ref->from_user_id, (int) $ref->message_id, 'email', [
                'subject' => sprintf('پاسخ ارجاع: %s', $status_fa),
                'body'    => sprintf('ارجاع شما به %s با وضعیت «%s» پاسخ داده شد.', $ref->to_display_name ?? '', $status_fa),
            ]);
        }

        WPOA_Activity_Logger::log('referral_responded', 'referral', $referral_id, $status_fa);

        return ['success' => true, 'message' => 'پاسخ با موفقیت ثبت شد. وضعیت: ' . $status_fa];
    }

    public function re_refer(int $parent_referral_id, array $data, int $from_user_id): array
    {
        $parent = $this->referral->get_full($parent_referral_id);

        if (!$parent || (int) $parent->to_user_id !== $from_user_id) {
            return ['success' => false, 'message' => 'ارجاع والد یافت نشد یا دسترسی ندارید.'];
        }

        $data['message_id']    = (int) $parent->message_id;
        $data['parent_ref_id'] = $parent_referral_id;

        return $this->create_referral($data, $from_user_id);
    }

    public function get_message_referrals(int $message_id, int $user_id): array
    {
        $referrals = $this->referral->get_for_message($message_id);

        return [
            'success'   => true,
            'referrals' => array_map(fn($r) => [
                'id'                => (int) $r->id,
                'type'              => $r->type,
                'type_label'        => WPOA_Referral_Model::TYPE_LABELS[$r->type] ?? $r->type,
                'status'            => $r->status,
                'status_label'      => WPOA_Referral_Model::STATUS_LABELS[$r->status] ?? $r->status,
                'instruction'       => $r->instruction,
                'response'          => $r->response,
                'deadline_jalali'   => $r->deadline_jalali ?? '',
                'created_at'        => $r->created_at,
                'responded_at'      => $r->responded_at,
                'from_display_name' => $r->from_display_name ?? '',
                'from_avatar_url'   => $r->from_avatar_url ?? '',
                'to_display_name'   => $r->to_display_name ?? '',
                'to_avatar_url'     => $r->to_avatar_url ?? '',
                'is_mine'           => ((int) $r->to_user_id === $user_id),
                'can_respond'       => ((int) $r->to_user_id === $user_id && $r->status === 'pending'),
                'parent_ref_id'     => (int) ($r->parent_ref_id ?? 0),
            ], $referrals),
        ];
    }

    public function get_my_queue(int $user_id, int $page = 1): array
    {
        $pending = $this->referral->get_pending_for_user($user_id, $page, 20);
        $count   = $this->referral->count_pending($user_id);

        return [
            'success'   => true,
            'referrals' => array_map(fn($r) => [
                'id'                => (int) $r->id,
                'message_id'        => (int) $r->message_id,
                'message_title'     => $r->message_title ?? '',
                'system_doc_number' => $r->system_doc_number ?? '',
                'priority'          => $r->priority ?? 'normal',
                'type'              => $r->type,
                'type_label'        => WPOA_Referral_Model::TYPE_LABELS[$r->type] ?? '',
                'instruction'       => $r->instruction,
                'deadline_jalali'   => $r->deadline_jalali ?? '',
                'from_display_name' => $r->from_display_name ?? '',
                'from_avatar_url'   => $r->from_avatar_url ?? '',
                'created_at'        => $r->created_at,
            ], $pending),
            'total' => $count,
        ];
    }

    public function get_my_sent(int $user_id, int $page = 1): array
    {
        $sent = $this->referral->get_sent_by_user($user_id, $page, 20);

        return [
            'success'   => true,
            'referrals' => array_map(fn($r) => [
                'id'              => (int) $r->id,
                'message_id'      => (int) $r->message_id,
                'message_title'   => $r->message_title ?? '',
                'type_label'      => WPOA_Referral_Model::TYPE_LABELS[$r->type] ?? '',
                'status'          => $r->status,
                'status_label'    => WPOA_Referral_Model::STATUS_LABELS[$r->status] ?? '',
                'to_display_name' => $r->to_display_name ?? '',
                'created_at'      => $r->created_at,
                'responded_at'    => $r->responded_at,
            ], $sent),
        ];
    }

    public function add_note(int $message_id, int $user_id, string $text, bool $is_private, ?int $referral_id): array
    {
        $note_id = $this->margin->add($message_id, $user_id, $text, $is_private, $referral_id);

        if (!$note_id) {
            return ['success' => false, 'message' => 'متن حاشیه نمی‌تواند خالی باشد.'];
        }

        WPOA_Activity_Logger::log('margin_note_added', 'message', $message_id);

        return ['success' => true, 'message' => 'حاشیه‌نویسی ثبت شد.', 'note_id' => $note_id];
    }

    public function get_notes(int $message_id, int $user_id): array
    {
        $notes = $this->margin->get_for_message($message_id, $user_id);

        return [
            'success' => true,
            'notes'   => array_map(fn($n) => [
                'id'          => (int) $n->id,
                'author_name' => $n->author_display_name ?? '',
                'avatar_url'  => $n->author_avatar_url ?? '',
                'note_text'   => $n->note_text,
                'is_private'  => (bool) $n->is_private,
                'is_mine'     => ((int) $n->user_id === $user_id),
                'referral_id' => (int) ($n->referral_id ?? 0),
                'created_at'  => $n->created_at,
            ], $notes),
        ];
    }

    public function delete_note(int $note_id, int $user_id): array
    {
        $result = $this->margin->remove($note_id, $user_id);

        return $result
            ? ['success' => true, 'message' => 'حاشیه حذف شد.']
            : ['success' => false, 'message' => 'خطا در حذف حاشیه.'];
    }

    public function get_read_receipts(int $message_id, int $requesting_user_id): array
    {
        WPOA_Permission::verify_or_die('can_view_read_receipt');

        global $wpdb;

        $mu_table = $wpdb->prefix . 'wpoa_message_users';
        $up_table = $wpdb->prefix . 'wpoa_user_profiles';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT mu.user_id, mu.role, mu.is_read, mu.read_at, mu.read_ip, mu.read_device,
                        up.display_name, up.avatar_url
                 FROM {$mu_table} mu
                 LEFT JOIN {$up_table} up ON up.user_id = mu.user_id
                 WHERE mu.message_id = %d AND mu.role != 'sender'
                 ORDER BY mu.read_at ASC",
                $message_id
            )
        ) ?: [];

        return [
            'success'  => true,
            'receipts' => array_map(fn($r) => [
                'user_id'      => (int) $r->user_id,
                'display_name' => $r->display_name ?? '',
                'avatar_url'   => $r->avatar_url ?? '',
                'role'         => $r->role,
                'is_read'      => (bool) $r->is_read,
                'read_at'      => $r->read_at,
                'read_ip'      => $r->read_ip ?? '',
            ], $rows),
        ];
    }
}