<?php
defined('ABSPATH') || exit;

class WPOA_User_Profile_Model extends WPOA_Model
{
    protected string $table_suffix = 'user_profiles';

    public function ensure_profile(int $user_id): void
    {
        $exists = $this->query_var(
            "SELECT id FROM {$this->table()} WHERE user_id = %d LIMIT 1",
            [$user_id]
        );

        if ($exists) {
            return;
        }

        $wp_user = get_userdata($user_id);
        if (!$wp_user) {
            return;
        }

        $this->insert([
            'user_id'      => $user_id,
            'display_name' => $wp_user->display_name,
        ]);
    }

    public function get_by_user_id(int $user_id): ?object
    {
        return $this->query_row(
            "SELECT * FROM {$this->table()} WHERE user_id = %d LIMIT 1",
            [$user_id]
        );
    }

    public function get_full_profile(int $user_id): ?object
    {
        $org_table  = $this->other_table('user_org');
        $role_table = $this->other_table('org_roles');
        $unit_table = $this->other_table('org_units');

        return $this->query_row(
            "SELECT up.*,
                    u.user_email AS email,
                    u.user_login,
                    uo.org_role_id,
                    uo.org_unit_id,
                    r.name AS org_role_name,
                    ou.name AS org_unit_name
             FROM {$this->table()} up
             INNER JOIN {$GLOBALS['wpdb']->users} u ON u.ID = up.user_id
             LEFT  JOIN {$org_table}  uo ON uo.user_id = up.user_id AND uo.is_primary = 1
             LEFT  JOIN {$role_table} r  ON r.id = uo.org_role_id
             LEFT  JOIN {$unit_table} ou ON ou.id = uo.org_unit_id
             WHERE up.user_id = %d
             LIMIT 1",
            [$user_id]
        );
    }

    public function update_profile(int $user_id, array $data): bool
    {
        $update = [];

        if (isset($data['display_name'])) {
            $update['display_name'] = sanitize_text_field($data['display_name']);
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => $update['display_name'],
            ]);
        }

        if (isset($data['phone'])) {
            $update['phone'] = sanitize_text_field($data['phone']);
        }

        if (isset($data['signature_text'])) {
            $update['signature_text'] = sanitize_textarea_field($data['signature_text']);
        }

        if (empty($update)) {
            return true;
        }

        return $this->update($update, ['user_id' => $user_id]) !== false;
    }

    public function update_avatar(int $user_id, string $url): bool
    {
        return $this->update(
            ['avatar_url' => esc_url_raw($url)],
            ['user_id' => $user_id]
        ) !== false;
    }

    public function update_signature_image(int $user_id, string $url): bool
    {
        return $this->update(
            ['signature_image_url' => esc_url_raw($url)],
            ['user_id' => $user_id]
        ) !== false;
    }

    public function search_users(string $keyword, int $limit = 20): array
    {
        global $wpdb;

        $like = '%' . $wpdb->esc_like(sanitize_text_field($keyword)) . '%';

        $org_table  = $this->other_table('user_org');
        $role_table = $this->other_table('org_roles');

        return $this->query(
            "SELECT up.user_id,
                    up.display_name,
                    up.avatar_url,
                    u.user_email,
                    r.name AS org_role_name
             FROM {$this->table()} up
             INNER JOIN {$wpdb->users} u ON u.ID = up.user_id
             LEFT  JOIN {$org_table}  uo ON uo.user_id = up.user_id AND uo.is_primary = 1
             LEFT  JOIN {$role_table} r  ON r.id = uo.org_role_id
             WHERE up.display_name LIKE %s
                OR u.user_email    LIKE %s
                OR u.user_login    LIKE %s
             ORDER BY up.display_name ASC
             LIMIT %d",
            [$like, $like, $like, $limit]
        );
    }

    public function get_all_users(int $page = 1, int $per_page = 50): array
    {
        global $wpdb;

        $offset     = max(0, ($page - 1) * $per_page);
        $org_table  = $this->other_table('user_org');
        $role_table = $this->other_table('org_roles');
        $unit_table = $this->other_table('org_units');

        return $this->query(
            "SELECT up.user_id,
                    up.display_name,
                    up.avatar_url,
                    u.user_email AS email,
                    u.user_login,
                    r.name  AS org_role_name,
                    ou.name AS org_unit_name
             FROM {$this->table()} up
             INNER JOIN {$wpdb->users} u ON u.ID = up.user_id
             LEFT  JOIN {$org_table}  uo ON uo.user_id = up.user_id AND uo.is_primary = 1
             LEFT  JOIN {$role_table} r  ON r.id = uo.org_role_id
             LEFT  JOIN {$unit_table} ou ON ou.id = uo.org_unit_id
             ORDER BY up.display_name ASC
             LIMIT %d OFFSET %d",
            [$per_page, $offset]
        );
    }
}