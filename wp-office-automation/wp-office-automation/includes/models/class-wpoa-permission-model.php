<?php
defined('ABSPATH') || exit;

class WPOA_Permission_Model extends WPOA_Model
{
    protected string $table_suffix = 'permissions';

    public const ALL_PERMISSIONS = [
        'can_send_message'      => 'ارسال نامه',
        'can_receive_message'   => 'دریافت نامه',
        'can_refer'             => 'ارجاع نامه',
        'can_approve'           => 'تأیید / رد نامه',
        'can_view_all_messages' => 'مشاهده همه نامه‌ها (نظارتی)',
        'can_print'             => 'چاپ نامه',
        'can_export'            => 'خروجی گزارشات',
        'can_manage_tags'       => 'مدیریت برچسب‌ها',
        'can_manage_users'      => 'مدیریت کاربران',
        'can_manage_org'        => 'مدیریت ساختار سازمانی',
        'can_manage_roles'      => 'مدیریت نقش‌ها',
        'can_manage_settings'   => 'مدیریت تنظیمات',
        'can_view_activity'     => 'مشاهده گزارش فعالیت‌ها',
        'can_view_read_receipt' => 'مشاهده وضعیت خوانده‌شدن',
    ];

    public function get_role_permissions(int $org_role_id): array
    {
        $rows = $this->query(
            "SELECT permission, granted FROM {$this->table()} WHERE org_role_id = %d",
            [$org_role_id]
        );

        $perms = [];
        foreach ($rows as $row) {
            $perms[$row->permission] = (bool) $row->granted;
        }

        return $perms;
    }

    public function role_can(int $org_role_id, string $permission): bool
    {
        $granted = $this->query_var(
            "SELECT granted FROM {$this->table()}
             WHERE org_role_id = %d AND permission = %s
             LIMIT 1",
            [$org_role_id, $permission]
        );

        if ($granted === null) {
            $basic = ['can_send_message', 'can_receive_message', 'can_print'];
            return in_array($permission, $basic, true);
        }

        return (bool) $granted;
    }

    public function get_role_permissions_full(int $org_role_id): array
    {
        $saved  = $this->get_role_permissions($org_role_id);
        $result = [];

        foreach (self::ALL_PERMISSIONS as $key => $label) {
            $result[$key] = [
                'label'   => $label,
                'granted' => $saved[$key] ?? false,
            ];
        }

        return $result;
    }

    public function set_role_permissions(int $org_role_id, array $permissions): void
    {
        $this->delete(['org_role_id' => $org_role_id]);

        foreach ($permissions as $perm => $granted) {
            if (!isset(self::ALL_PERMISSIONS[$perm])) {
                continue;
            }

            $this->insert([
                'org_role_id' => $org_role_id,
                'permission'  => sanitize_key($perm),
                'granted'     => $granted ? 1 : 0,
            ]);
        }
    }

    public function seed_defaults(int $org_role_id): void
    {
        $defaults = [
            'can_send_message'      => true,
            'can_receive_message'   => true,
            'can_refer'             => true,
            'can_approve'           => false,
            'can_print'             => true,
            'can_view_read_receipt' => true,
        ];

        $this->set_role_permissions($org_role_id, $defaults);
    }
}