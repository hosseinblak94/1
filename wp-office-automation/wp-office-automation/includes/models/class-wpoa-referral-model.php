<?php
defined('ABSPATH') || exit;

class WPOA_Referral_Model extends WPOA_Model
{
    protected string $table_suffix = 'referrals';

    private const TYPES    = ['referral', 'approval', 'action', 'info'];
    private const STATUSES = ['pending', 'accepted', 'completed', 'rejected', 'expired'];

    public const TYPE_LABELS = [
        'referral' => 'ارجاع',
        'approval' => 'درخواست تأیید',
        'action'   => 'جهت اقدام',
        'info'     => 'جهت اطلاع',
    ];

    public const STATUS_LABELS = [
        'pending'   => 'در انتظار',
        'accepted'  => 'پذیرفته‌شده',
        'completed' => 'اقدام‌شده',
        'rejected'  => 'ردشده',
        'expired'   => 'منقضی‌شده',
    ];

    public function create(array $data): int|false
    {
        $from = absint($data['from_user_id'] ?? 0);
        $to   = absint($data['to_user_id'] ?? 0);
        $msg  = absint($data['message_id'] ?? 0);

        if (!$from || !$to || !$msg) {
            return false;
        }

        $type = $this->validate_enum($data['type'] ?? 'referral', self::TYPES, 'referral');

        $deadline        = null;
        $deadline_jalali = null;
        if (!empty($data['deadline'])) {
            $deadline        = sanitize_text_field($data['deadline']);
            $deadline_jalali = !empty($data['deadline_jalali'])
                ? sanitize_text_field($data['deadline_jalali'])
                : WPOA_Jalali_Helper::convert(strtotime($deadline));
        }

        return $this->insert([
            'message_id'      => $msg,
            'from_user_id'    => $from,
            'to_user_id'      => $to,
            'type'            => $type,
            'status'          => 'pending',
            'instruction'     => sanitize_textarea_field($data['instruction'] ?? ''),
            'deadline'        => $deadline,
            'deadline_jalali' => $deadline_jalali,
            'parent_ref_id'   => absint($data['parent_ref_id'] ?? 0) ?: null,
        ]);
    }

    public function respond(int $referral_id, int $user_id, string $status, string $response = ''): bool
    {
        $ref = $this->find($referral_id);

        if (!$ref || (int) $ref->to_user_id !== $user_id || $ref->status !== 'pending') {
            return false;
        }

        $status = $this->validate_enum($status, ['accepted', 'completed', 'rejected'], 'completed');

        return $this->update(
            [
                'status'       => $status,
                'response'     => sanitize_textarea_field($response),
                'responded_at' => current_time('mysql'),
            ],
            ['id' => $referral_id]
        ) !== false;
    }

    public function get_for_message(int $message_id): array
    {
        $profiles = $this->other_table('user_profiles');

        return $this->query(
            "SELECT r.*,
                    fp.display_name AS from_display_name,
                    fp.avatar_url   AS from_avatar_url,
                    tp.display_name AS to_display_name,
                    tp.avatar_url   AS to_avatar_url
             FROM {$this->table()} r
             LEFT JOIN {$profiles} fp ON fp.user_id = r.from_user_id
             LEFT JOIN {$profiles} tp ON tp.user_id = r.to_user_id
             WHERE r.message_id = %d
             ORDER BY r.created_at ASC",
            [$message_id]
        );
    }

    public function get_pending_for_user(int $user_id, int $page = 1, int $per_page = 20): array
    {
        $offset   = max(0, ($page - 1) * $per_page);
        $profiles = $this->other_table('user_profiles');
        $messages = $this->other_table('messages');

        return $this->query(
            "SELECT r.*,
                    fp.display_name AS from_display_name,
                    fp.avatar_url   AS from_avatar_url,
                    m.title         AS message_title,
                    m.system_doc_number,
                    m.priority
             FROM {$this->table()} r
             LEFT  JOIN {$profiles} fp ON fp.user_id = r.from_user_id
             INNER JOIN {$messages}  m ON m.id       = r.message_id
             WHERE r.to_user_id = %d
               AND r.status     = 'pending'
             ORDER BY
                CASE r.type
                    WHEN 'approval' THEN 1
                    WHEN 'action'   THEN 2
                    WHEN 'referral' THEN 3
                    WHEN 'info'     THEN 4
                END,
                r.deadline ASC,
                r.created_at ASC
             LIMIT %d OFFSET %d",
            [$user_id, $per_page, $offset]
        );
    }

    public function count_pending(int $user_id): int
    {
        return $this->count_where(
            'to_user_id = %d AND status = %s',
            [$user_id, 'pending']
        );
    }

    public function get_sent_by_user(int $user_id, int $page = 1, int $per_page = 20): array
    {
        $offset   = max(0, ($page - 1) * $per_page);
        $profiles = $this->other_table('user_profiles');
        $messages = $this->other_table('messages');

        return $this->query(
            "SELECT r.*,
                    tp.display_name AS to_display_name,
                    tp.avatar_url   AS to_avatar_url,
                    m.title         AS message_title,
                    m.system_doc_number
             FROM {$this->table()} r
             LEFT  JOIN {$profiles} tp ON tp.user_id = r.to_user_id
             INNER JOIN {$messages}  m ON m.id       = r.message_id
             WHERE r.from_user_id = %d
             ORDER BY r.created_at DESC
             LIMIT %d OFFSET %d",
            [$user_id, $per_page, $offset]
        );
    }

    public function get_full(int $referral_id): ?object
    {
        $profiles = $this->other_table('user_profiles');

        return $this->query_row(
            "SELECT r.*,
                    fp.display_name AS from_display_name,
                    fp.avatar_url   AS from_avatar_url,
                    tp.display_name AS to_display_name,
                    tp.avatar_url   AS to_avatar_url
             FROM {$this->table()} r
             LEFT JOIN {$profiles} fp ON fp.user_id = r.from_user_id
             LEFT JOIN {$profiles} tp ON tp.user_id = r.to_user_id
             WHERE r.id = %d
             LIMIT 1",
            [$referral_id]
        );
    }

    public function expire_overdue(): int
    {
        global $wpdb;
        $wpdb->query(
            "UPDATE {$this->table()}
             SET status = 'expired'
             WHERE status   = 'pending'
               AND deadline IS NOT NULL
               AND deadline < NOW()"
        );
        return (int) $wpdb->rows_affected;
    }
}