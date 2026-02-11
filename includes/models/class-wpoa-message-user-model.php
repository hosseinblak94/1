<?php
defined('ABSPATH') || exit;

class WPOA_Message_User_Model extends WPOA_Model
{
    protected string $table_suffix = 'message_users';

    private const FOLDERS = ['inbox', 'sent', 'drafts', 'archive', 'trash'];

    public function create_entry(
        int    $message_id,
        int    $user_id,
        string $role   = 'to',
        string $folder = 'inbox'
    ): int|false {
        $existing = $this->query_row(
            "SELECT id FROM {$this->table()} WHERE message_id = %d AND user_id = %d LIMIT 1",
            [$message_id, $user_id]
        );

        if ($existing) {
            return (int) $existing->id;
        }

        return $this->insert([
            'message_id' => $message_id,
            'user_id'    => $user_id,
            'role'       => $this->validate_enum($role, ['sender', 'to', 'cc'], 'to'),
            'folder'     => $this->validate_enum($folder, self::FOLDERS, 'inbox'),
            'is_read'    => ($role === 'sender') ? 1 : 0,
        ]);
    }

    public function get_folder(int $user_id, string $folder, int $page = 1, int $per_page = 20): array
    {
        $folder          = $this->validate_enum($folder, self::FOLDERS, 'inbox');
        $offset          = max(0, ($page - 1) * $per_page);
        $messages_table  = $this->other_table('messages');
        $profiles_table  = $this->other_table('user_profiles');

        return $this->query(
            "SELECT mu.id AS mu_id,
                    mu.is_read, mu.is_starred, mu.is_pinned,
                    mu.folder, mu.role AS user_role,
                    m.id AS message_id,
                    m.title, m.priority, m.status,
                    m.system_doc_number,
                    m.internal_doc_number,
                    m.sent_at_jalali,
                    m.sender_id,
                    up.display_name AS sender_display_name,
                    up.avatar_url   AS sender_avatar_url
             FROM {$this->table()} mu
             INNER JOIN {$messages_table} m  ON m.id = mu.message_id
             LEFT  JOIN {$profiles_table} up ON up.user_id = m.sender_id
             WHERE mu.user_id    = %d
               AND mu.folder     = %s
               AND mu.is_deleted = 0
             ORDER BY mu.is_pinned DESC, m.sent_at DESC
             LIMIT %d OFFSET %d",
            [$user_id, $folder, $per_page, $offset]
        );
    }

public function count_folder(int $user_id, string $folder): int
{
    global $wpdb;

    $mu = $wpdb->prefix . 'wpoa_message_users';
    $m  = $wpdb->prefix . 'wpoa_messages';

    switch ($folder) {
        case 'inbox':
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$mu} mu
                 INNER JOIN {$m} m ON m.id = mu.message_id
                 WHERE mu.user_id = %d
                   AND mu.type IN ('to','cc')
                   AND mu.is_archived = 0
                   AND mu.is_trashed = 0
                   AND mu.deleted_at IS NULL
                   AND m.status = 'sent'",
                $user_id
            ));

        case 'sent':
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$mu} mu
                 INNER JOIN {$m} m ON m.id = mu.message_id
                 WHERE mu.user_id = %d
                   AND mu.type = 'sender'
                   AND mu.is_trashed = 0
                   AND mu.deleted_at IS NULL
                   AND m.status = 'sent'",
                $user_id
            ));

        case 'drafts':
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$mu} mu
                 INNER JOIN {$m} m ON m.id = mu.message_id
                 WHERE mu.user_id = %d
                   AND mu.type = 'sender'
                   AND mu.is_trashed = 0
                   AND mu.deleted_at IS NULL
                   AND m.status = 'draft'",
                $user_id
            ));

        case 'starred':
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$mu} mu
                 WHERE mu.user_id = %d
                   AND mu.is_starred = 1
                   AND mu.is_trashed = 0
                   AND mu.deleted_at IS NULL",
                $user_id
            ));

        case 'archive':
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$mu} mu
                 WHERE mu.user_id = %d
                   AND mu.is_archived = 1
                   AND mu.is_trashed = 0
                   AND mu.deleted_at IS NULL",
                $user_id
            ));

        case 'trash':
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*)
                 FROM {$mu} mu
                 WHERE mu.user_id = %d
                   AND mu.is_trashed = 1
                   AND mu.deleted_at IS NULL",
                $user_id
            ));

        default:
            return 0;
    }
}

    public function count_unread(int $user_id): int
    {
        return $this->count_where(
            'user_id = %d AND folder = %s AND is_read = 0 AND is_deleted = 0',
            [$user_id, 'inbox']
        );
    }

    public function mark_read(int $user_id, int $message_id): bool
    {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200));

        return $this->update(
            [
                'is_read'     => 1,
                'read_at'     => current_time('mysql'),
                'read_ip'     => $ip,
                'read_device' => $ua,
            ],
            ['user_id' => $user_id, 'message_id' => $message_id]
        ) !== false;
    }

    public function mark_unread(int $user_id, int $message_id): bool
    {
        return $this->update(
            ['is_read' => 0, 'read_at' => null],
            ['user_id' => $user_id, 'message_id' => $message_id]
        ) !== false;
    }

    public function toggle_star(int $user_id, int $message_id): bool
    {
        $current = $this->query_var(
            "SELECT is_starred FROM {$this->table()}
             WHERE user_id = %d AND message_id = %d LIMIT 1",
            [$user_id, $message_id]
        );

        $new_val = ((int) $current === 1) ? 0 : 1;

        return $this->update(
            ['is_starred' => $new_val],
            ['user_id' => $user_id, 'message_id' => $message_id]
        ) !== false;
    }

    public function toggle_pin(int $user_id, int $message_id): bool
    {
        $current = $this->query_var(
            "SELECT is_pinned FROM {$this->table()}
             WHERE user_id = %d AND message_id = %d LIMIT 1",
            [$user_id, $message_id]
        );

        $new_val = ((int) $current === 1) ? 0 : 1;

        return $this->update(
            ['is_pinned' => $new_val],
            ['user_id' => $user_id, 'message_id' => $message_id]
        ) !== false;
    }

    public function update_folder(int $user_id, int $message_id, string $folder): bool
    {
        $folder = $this->validate_enum($folder, self::FOLDERS, 'inbox');

        return $this->update(
            ['folder' => $folder],
            ['user_id' => $user_id, 'message_id' => $message_id]
        ) !== false;
    }

    public function soft_delete(int $user_id, int $message_id): bool
    {
        return $this->update(
            ['is_deleted' => 1],
            ['user_id' => $user_id, 'message_id' => $message_id]
        ) !== false;
    }

    public function restore(int $user_id, int $message_id): bool
    {
        return $this->update(
            ['is_deleted' => 0, 'folder' => 'inbox'],
            ['user_id' => $user_id, 'message_id' => $message_id]
        ) !== false;
    }

    public function delete_entry(int $user_id, int $message_id): bool
    {
        return $this->delete([
            'user_id'    => $user_id,
            'message_id' => $message_id,
        ]) !== false;
    }

    public function get_user_message(int $user_id, int $message_id): ?object
    {
        $messages_table = $this->other_table('messages');
        $profiles_table = $this->other_table('user_profiles');

        return $this->query_row(
            "SELECT mu.*,
                    m.title, m.body, m.priority, m.status,
                    m.system_doc_number, m.internal_doc_number,
                    m.signature_type, m.signature_text, m.signature_image_url,
                    m.internal_note, m.sent_at, m.sent_at_jalali,
                    m.sender_id, m.thread_id, m.parent_id,
                    up.display_name AS sender_display_name,
                    up.avatar_url   AS sender_avatar_url
             FROM {$this->table()} mu
             INNER JOIN {$messages_table} m  ON m.id = mu.message_id
             LEFT  JOIN {$profiles_table} up ON up.user_id = m.sender_id
             WHERE mu.user_id    = %d
               AND mu.message_id = %d
             LIMIT 1",
            [$user_id, $message_id]
        );
    }

    public function get_starred(int $user_id, int $page = 1, int $per_page = 20): array
    {
        $offset          = max(0, ($page - 1) * $per_page);
        $messages_table  = $this->other_table('messages');
        $profiles_table  = $this->other_table('user_profiles');

        return $this->query(
            "SELECT mu.id AS mu_id,
                    mu.is_read, mu.is_starred, mu.is_pinned,
                    mu.folder, mu.role AS user_role,
                    m.id AS message_id,
                    m.title, m.priority, m.status,
                    m.system_doc_number,
                    m.sent_at_jalali,
                    m.sender_id,
                    up.display_name AS sender_display_name,
                    up.avatar_url   AS sender_avatar_url
             FROM {$this->table()} mu
             INNER JOIN {$messages_table} m  ON m.id = mu.message_id
             LEFT  JOIN {$profiles_table} up ON up.user_id = m.sender_id
             WHERE mu.user_id    = %d
               AND mu.is_deleted = 0
               AND mu.is_starred = 1
             ORDER BY mu.is_pinned DESC, m.sent_at DESC
             LIMIT %d OFFSET %d",
            [$user_id, $per_page, $offset]
        );
    }

    public function count_starred(int $user_id): int
    {
        return $this->count_where(
            'user_id = %d AND is_deleted = 0 AND is_starred = 1',
            [$user_id]
        );
    }

    public function search(int $user_id, string $keyword, int $page = 1, int $per_page = 20): array
    {
        global $wpdb;

        $offset         = max(0, ($page - 1) * $per_page);
        $messages_table = $this->other_table('messages');
        $profiles_table = $this->other_table('user_profiles');
        $like           = '%' . $wpdb->esc_like(sanitize_text_field($keyword)) . '%';

        return $this->query(
            "SELECT mu.id AS mu_id,
                    mu.is_read, mu.is_starred, mu.is_pinned,
                    mu.folder, mu.role AS user_role,
                    m.id AS message_id,
                    m.title, m.priority, m.status,
                    m.system_doc_number,
                    m.sent_at_jalali,
                    m.sender_id,
                    up.display_name AS sender_display_name,
                    up.avatar_url   AS sender_avatar_url
             FROM {$this->table()} mu
             INNER JOIN {$messages_table} m  ON m.id = mu.message_id
             LEFT  JOIN {$profiles_table} up ON up.user_id = m.sender_id
             WHERE mu.user_id    = %d
               AND mu.is_deleted = 0
               AND (m.title LIKE %s OR m.body LIKE %s
                    OR m.system_doc_number LIKE %s)
             ORDER BY m.sent_at DESC
             LIMIT %d OFFSET %d",
            [$user_id, $like, $like, $like, $per_page, $offset]
        );
    }

    public function advanced_search(int $user_id, array $filters, int $page = 1, int $per_page = 20): array
    {
        global $wpdb;

        $offset          = max(0, ($page - 1) * $per_page);
        $messages_table  = $this->other_table('messages');
        $profiles_table  = $this->other_table('user_profiles');
        $msg_tags_table  = $this->other_table('message_tags');

        $where_parts  = ['mu.user_id = %d', 'mu.is_deleted = 0'];
        $where_params = [$user_id];
        $joins        = '';

        if (!empty($filters['keyword'])) {
            $kw = '%' . $wpdb->esc_like(sanitize_text_field($filters['keyword'])) . '%';
            $where_parts[]  = '(m.title LIKE %s OR m.body LIKE %s)';
            $where_params[] = $kw;
            $where_params[] = $kw;
        }

        if (!empty($filters['priority'])) {
            $p = $this->validate_enum($filters['priority'], ['low','normal','important','instant'], '');
            if ($p) {
                $where_parts[]  = 'm.priority = %s';
                $where_params[] = $p;
            }
        }

        if (!empty($filters['date_from'])) {
            $where_parts[]  = 'm.sent_at >= %s';
            $where_params[] = sanitize_text_field($filters['date_from']) . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $where_parts[]  = 'm.sent_at <= %s';
            $where_params[] = sanitize_text_field($filters['date_to']) . ' 23:59:59';
        }

        if (!empty($filters['sender_id'])) {
            $where_parts[]  = 'm.sender_id = %d';
            $where_params[] = absint($filters['sender_id']);
        }

        if (!empty($filters['folder'])) {
            $folder = $this->validate_enum($filters['folder'], self::FOLDERS, '');
            if ($folder) {
                $where_parts[]  = 'mu.folder = %s';
                $where_params[] = $folder;
            }
        }

        if (!empty($filters['tag_id'])) {
            $joins .= " INNER JOIN {$msg_tags_table} mt_filter ON mt_filter.message_id = m.id";
            $where_parts[]  = 'mt_filter.tag_id = %d';
            $where_params[] = absint($filters['tag_id']);
        }

        $where_sql = implode(' AND ', $where_parts);
        $params    = array_merge($where_params, [$per_page, $offset]);

        return $this->query(
            "SELECT mu.id AS mu_id,
                    mu.is_read, mu.is_starred, mu.is_pinned,
                    mu.folder, mu.role AS user_role,
                    m.id AS message_id,
                    m.title, m.priority, m.status,
                    m.system_doc_number,
                    m.internal_doc_number,
                    m.sent_at_jalali,
                    m.sent_at,
                    m.sender_id,
                    m.thread_id,
                    up.display_name AS sender_display_name,
                    up.avatar_url   AS sender_avatar_url
             FROM {$this->table()} mu
             INNER JOIN {$messages_table} m  ON m.id = mu.message_id
             LEFT  JOIN {$profiles_table} up ON up.user_id = m.sender_id
             {$joins}
             WHERE {$where_sql}
             ORDER BY mu.is_pinned DESC, m.sent_at DESC
             LIMIT %d OFFSET %d",
            $params
        );
    }
}