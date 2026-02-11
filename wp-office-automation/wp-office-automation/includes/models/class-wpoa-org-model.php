<?php
defined('ABSPATH') || exit;

class WPOA_Org_Model extends WPOA_Model
{
    protected string $table_suffix = 'org_units';

    /* ------------------------------------------------
     * UNITS
     * ------------------------------------------------ */

    public function create_unit(string $name, ?int $parent_id = null, string $desc = ''): int|false
    {
        return $this->insert([
            'name'        => sanitize_text_field($name),
            'slug'        => sanitize_title($name),
            'parent_id'   => $parent_id,
            'description' => sanitize_textarea_field($desc),
        ]);
    }

    public function update_unit(int $id, array $data): bool
    {
        $update = [];

        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
            $update['slug'] = sanitize_title($data['name']);
        }
        if (array_key_exists('parent_id', $data)) {
            $update['parent_id'] = $data['parent_id'] ? absint($data['parent_id']) : null;
        }
        if (isset($data['description'])) {
            $update['description'] = sanitize_textarea_field($data['description']);
        }

        if (empty($update)) {
            return true;
        }

        return $this->update($update, ['id' => $id]) !== false;
    }

    public function delete_unit(int $id): bool
    {
        $children = $this->query(
            "SELECT id FROM {$this->table()} WHERE parent_id = %d",
            [$id]
        );

        if (!empty($children)) {
            return false;
        }

        $user_org = $this->other_table('user_org');
        global $wpdb;
        $wpdb->delete($user_org, ['org_unit_id' => $id]);

        return $this->delete(['id' => $id]) !== false;
    }

    public function get_tree(): array
    {
        $all = $this->query(
            "SELECT * FROM {$this->table()} ORDER BY sort_order ASC, name ASC"
        );

        return $this->build_tree($all, null);
    }

    private function build_tree(array $items, ?int $parent_id): array
    {
        $branch = [];

        foreach ($items as $item) {
            $item_parent = $item->parent_id ? (int) $item->parent_id : null;

            if ($item_parent === $parent_id) {
                $children = $this->build_tree($items, (int) $item->id);
                $branch[] = [
                    'id'          => (int) $item->id,
                    'name'        => $item->name,
                    'description' => $item->description ?? '',
                    'parent_id'   => $item_parent,
                    'children'    => $children,
                ];
            }
        }

        return $branch;
    }

    /* ------------------------------------------------
     * ROLES
     * ------------------------------------------------ */

    public function get_all_roles(): array
    {
        $roles_table = $this->other_table('org_roles');

        return $this->query(
            "SELECT * FROM {$roles_table} ORDER BY sort_order ASC, name ASC"
        );
    }

    public function create_role(string $name, string $desc = ''): int|false
    {
        global $wpdb;
        $roles_table = $this->other_table('org_roles');

        $slug = sanitize_title($name);
        $exists = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$roles_table} WHERE slug = %s LIMIT 1", $slug)
        );

        if ($exists) {
            return false;
        }

        $wpdb->insert($roles_table, [
            'name'        => sanitize_text_field($name),
            'slug'        => $slug,
            'description' => sanitize_textarea_field($desc),
        ]);

        return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
    }

    public function update_role(int $id, array $data): bool
    {
        global $wpdb;
        $roles_table = $this->other_table('org_roles');

        $update = [];
        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
            $update['slug'] = sanitize_title($data['name']);
        }
        if (isset($data['description'])) {
            $update['description'] = sanitize_textarea_field($data['description']);
        }

        if (empty($update)) {
            return true;
        }

        return $wpdb->update($roles_table, $update, ['id' => $id]) !== false;
    }

    public function delete_role(int $id): bool
    {
        global $wpdb;
        $roles_table = $this->other_table('org_roles');
        $user_org    = $this->other_table('user_org');
        $perms       = $this->other_table('permissions');

        $assigned = $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$user_org} WHERE org_role_id = %d", $id)
        );

        if ((int) $assigned > 0) {
            return false;
        }

        $wpdb->delete($perms, ['org_role_id' => $id]);

        return $wpdb->delete($roles_table, ['id' => $id]) !== false;
    }

    /* ------------------------------------------------
     * USER ↔ ORG ASSIGNMENTS
     * ------------------------------------------------ */

    public function assign_user(int $user_id, int $unit_id, int $role_id, bool $is_primary = true): int|false
    {
        global $wpdb;
        $user_org = $this->other_table('user_org');

        if ($is_primary) {
            $wpdb->update($user_org, ['is_primary' => 0], ['user_id' => $user_id]);
        }

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$user_org} WHERE user_id = %d AND org_unit_id = %d LIMIT 1",
                $user_id, $unit_id
            )
        );

        if ($exists) {
            $wpdb->update($user_org, [
                'org_role_id' => $role_id,
                'is_primary'  => $is_primary ? 1 : 0,
            ], ['id' => $exists]);
            return (int) $exists;
        }

        $wpdb->insert($user_org, [
            'user_id'     => $user_id,
            'org_unit_id' => $unit_id,
            'org_role_id' => $role_id,
            'is_primary'  => $is_primary ? 1 : 0,
        ]);

        return $wpdb->insert_id ? (int) $wpdb->insert_id : false;
    }

    public function unassign_user(int $user_id, int $unit_id): bool
    {
        global $wpdb;
        $user_org = $this->other_table('user_org');

        return $wpdb->delete($user_org, [
            'user_id'     => $user_id,
            'org_unit_id' => $unit_id,
        ]) !== false;
    }

    public function get_user_assignments(int $user_id): array
    {
        global $wpdb;
        $user_org   = $this->other_table('user_org');
        $role_table = $this->other_table('org_roles');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT uo.*, r.name AS role_name, ou.name AS unit_name
                 FROM {$user_org} uo
                 LEFT JOIN {$role_table} r  ON r.id  = uo.org_role_id
                 LEFT JOIN {$this->table()} ou ON ou.id = uo.org_unit_id
                 WHERE uo.user_id = %d
                 ORDER BY uo.is_primary DESC",
                $user_id
            )
        ) ?: [];
    }

    public function get_unit_users(int $unit_id): array
    {
        global $wpdb;
        $user_org       = $this->other_table('user_org');
        $role_table     = $this->other_table('org_roles');
        $profiles_table = $this->other_table('user_profiles');

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT uo.user_id,
                        up.display_name,
                        u.user_email AS email,
                        r.name AS role_name
                 FROM {$user_org} uo
                 LEFT JOIN {$profiles_table} up ON up.user_id = uo.user_id
                 LEFT JOIN {$role_table} r      ON r.id       = uo.org_role_id
                 LEFT JOIN {$wpdb->users} u     ON u.ID       = uo.user_id
                 WHERE uo.org_unit_id = %d
                 ORDER BY up.display_name ASC",
                $unit_id
            )
        ) ?: [];
    }
}