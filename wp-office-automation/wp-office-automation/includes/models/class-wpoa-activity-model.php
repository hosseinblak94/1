<?php
defined('ABSPATH') || exit;

class WPOA_Activity_Model extends WPOA_Model
{
    protected string $table_suffix = 'activity_log';

    public const ACTION_MESSAGE_SENT      = 'message_sent';
    public const ACTION_MESSAGE_READ      = 'message_read';
    public const ACTION_MESSAGE_REPLIED   = 'message_replied';
    public const ACTION_MESSAGE_FORWARDED = 'message_forwarded';
    public const ACTION_MESSAGE_DELETED   = 'message_deleted';
    public const ACTION_MESSAGE_RESTORED  = 'message_restored';
    public const ACTION_DRAFT_SAVED       = 'draft_saved';
    public const ACTION_PROFILE_UPDATED   = 'profile_updated';
    public const ACTION_PASSWORD_CHANGED  = 'password_changed';
    public const ACTION_SETTINGS_SAVED    = 'settings_saved';
    public const ACTION_ORG_UNIT_CREATED  = 'org_unit_created';
    public const ACTION_ORG_UNIT_DELETED  = 'org_unit_deleted';
    public const ACTION_ORG_ROLE_CREATED  = 'org_role_created';
    public const ACTION_ORG_ROLE_DELETED  = 'org_role_deleted';
    public const ACTION_USER_ASSIGNED     = 'user_assigned';

    private const ACTION_LABELS = [
        'message_sent'        => 'ارسال نامه',
        'message_read'        => 'مشاهده نامه',
        'message_replied'     => 'پاسخ به نامه',
        'message_forwarded'   => 'ارسال مجدد نامه',
        'message_deleted'     => 'حذف نامه',
        'message_restored'    => 'بازیابی نامه',
        'message_print'       => 'چاپ نامه',
        'draft_saved'         => 'ذخیره پیش‌نویس',
        'attachment_uploaded'  => 'آپلود پیوست',
        'attachment_deleted'   => 'حذف پیوست',
        'profile_updated'     => 'بروزرسانی پروفایل',
        'password_changed'    => 'تغییر رمز عبور',
        'org_unit_created'    => 'ایجاد واحد سازمانی',
        'org_unit_updated'    => 'بروزرسانی واحد سازمانی',
        'org_unit_deleted'    => 'حذف واحد سازمانی',
        'org_role_created'    => 'ایجاد نقش',
        'org_role_deleted'    => 'حذف نقش',
        'user_assigned'       => 'اختصاص کاربر به واحد',
        'settings_saved'      => 'ذخیره تنظیمات',
        'referral_created'    => 'ایجاد ارجاع',
        'referral_responded'  => 'پاسخ به ارجاع',
        'margin_note_added'   => 'ثبت حاشیه‌نویسی',
        'permissions_updated' => 'بروزرسانی مجوزها',
    ];

    public function log(
        int    $user_id,
        string $action,
        string $object_type = '',
        int    $object_id   = 0,
        string $details     = ''
    ): int|false {
        return $this->insert([
            'user_id'     => $user_id,
            'action'      => sanitize_key($action),
            'object_type' => sanitize_key($object_type),
            'object_id'   => absint($object_id),
            'details'     => sanitize_textarea_field($details),
            'ip_address'  => self::get_client_ip(),
            'user_agent'  => sanitize_text_field(
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
            ),
        ]);
    }

    public function get_recent(int $page = 1, int $per_page = 30, array $filters = []): array
    {
        $offset         = max(0, ($page - 1) * $per_page);
        $profiles_table = $this->other_table('user_profiles');

        $where_parts  = ['1=1'];
        $where_params = [];

        if (!empty($filters['user_id'])) {
            $where_parts[]  = 'al.user_id = %d';
            $where_params[] = absint($filters['user_id']);
        }
        if (!empty($filters['action'])) {
            $where_parts[]  = 'al.action = %s';
            $where_params[] = sanitize_key($filters['action']);
        }
        if (!empty($filters['date_from'])) {
            $where_parts[]  = 'al.created_at >= %s';
            $where_params[] = sanitize_text_field($filters['date_from']) . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where_parts[]  = 'al.created_at <= %s';
            $where_params[] = sanitize_text_field($filters['date_to']) . ' 23:59:59';
        }

        $where_sql = implode(' AND ', $where_parts);
        $params    = array_merge($where_params, [$per_page, $offset]);

        return $this->query(
            "SELECT al.*,
                    up.display_name AS user_display_name,
                    up.avatar_url   AS user_avatar_url
             FROM {$this->table()} al
             LEFT JOIN {$profiles_table} up ON up.user_id = al.user_id
             WHERE {$where_sql}
             ORDER BY al.created_at DESC
             LIMIT %d OFFSET %d",
            $params
        );
    }

    public function count_filtered(array $filters = []): int
    {
        $where_parts  = ['1=1'];
        $where_params = [];

        if (!empty($filters['user_id'])) {
            $where_parts[]  = 'user_id = %d';
            $where_params[] = absint($filters['user_id']);
        }
        if (!empty($filters['action'])) {
            $where_parts[]  = 'action = %s';
            $where_params[] = sanitize_key($filters['action']);
        }
        if (!empty($filters['date_from'])) {
            $where_parts[]  = 'created_at >= %s';
            $where_params[] = sanitize_text_field($filters['date_from']) . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where_parts[]  = 'created_at <= %s';
            $where_params[] = sanitize_text_field($filters['date_to']) . ' 23:59:59';
        }

        return $this->count_where(implode(' AND ', $where_parts), $where_params);
    }

    public function get_message_history(int $message_id): array
    {
        $profiles_table = $this->other_table('user_profiles');

        return $this->query(
            "SELECT al.*,
                    up.display_name AS user_display_name
             FROM {$this->table()} al
             LEFT JOIN {$profiles_table} up ON up.user_id = al.user_id
             WHERE al.object_type = 'message'
               AND al.object_id   = %d
             ORDER BY al.created_at ASC",
            [$message_id]
        );
    }

    public static function get_action_label(string $action): string
    {
        return self::ACTION_LABELS[$action] ?? $action;
    }

    public static function get_action_options(): array
    {
        return self::ACTION_LABELS;
    }

    public function purge_old(int $days = 365): int
    {
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table()} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
        return (int) $wpdb->rows_affected;
    }

    private static function get_client_ip(): string
    {
        $headers = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = trim(explode(',', $_SERVER[$header])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return sanitize_text_field($ip);
                }
            }
        }

        return '0.0.0.0';
    }
}