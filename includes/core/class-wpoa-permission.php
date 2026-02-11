<?php
defined('ABSPATH') || exit;

class WPOA_Permission
{
    private static array $cache = [];

    public static function can(string $permission): bool
    {
        $user_id = get_current_user_id();

        if ($user_id === 0) {
            return false;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        return self::user_can($user_id, $permission);
    }

    public static function user_can(int $user_id, string $permission): bool
    {
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        if (!isset(self::$cache[$user_id])) {
            self::$cache[$user_id] = self::load_user_permissions($user_id);
        }

        return self::$cache[$user_id][$permission] ?? false;
    }

    private static function load_user_permissions(int $user_id): array
    {
        $org_model  = new WPOA_Org_Model();
        $perm_model = new WPOA_Permission_Model();

        $assignments = $org_model->get_user_assignments($user_id);

        $merged = [];

        foreach ($assignments as $assignment) {
            $role_perms = $perm_model->get_role_permissions((int) $assignment->org_role_id);

            foreach ($role_perms as $perm => $granted) {
                if ($granted) {
                    $merged[$perm] = true;
                } elseif (!isset($merged[$perm])) {
                    $merged[$perm] = false;
                }
            }
        }

        if (empty($assignments)) {
            $merged['can_send_message']    = true;
            $merged['can_receive_message'] = true;
            $merged['can_print']           = true;
        }

        return $merged;
    }

    public static function flush(?int $user_id = null): void
    {
        if ($user_id !== null) {
            unset(self::$cache[$user_id]);
        } else {
            self::$cache = [];
        }
    }

    public static function verify_or_die(string $permission): void
    {
        if (!self::can($permission)) {
            wp_send_json_error([
                'message' => 'شما مجوز انجام این عملیات را ندارید.',
            ], 403);
        }
    }
}