<?php
defined('ABSPATH') || exit;

class WPOA_Org_Controller
{
    private WPOA_Org_Model $org;

    public function __construct()
    {
        $this->org = new WPOA_Org_Model();
    }

    public function get_tree(): array
    {
        return [
            'success' => true,
            'tree'    => $this->org->get_tree(),
        ];
    }

    public function create_unit(array $data): array
    {
        $name      = sanitize_text_field($data['name'] ?? '');
        $parent_id = !empty($data['parent_id']) ? absint($data['parent_id']) : null;
        $desc      = sanitize_textarea_field($data['description'] ?? '');

        if (empty($name)) {
            return ['success' => false, 'message' => 'نام واحد الزامی است.'];
        }

        $id = $this->org->create_unit($name, $parent_id, $desc);

        if (!$id) {
            return ['success' => false, 'message' => 'خطا در ایجاد واحد.'];
        }

        WPOA_Activity_Logger::log(WPOA_Activity_Model::ACTION_ORG_UNIT_CREATED, 'unit', $id, $name);

        return ['success' => true, 'message' => 'واحد سازمانی ایجاد شد.', 'unit_id' => $id];
    }

    public function update_unit(int $id, array $data): array
    {
        $this->org->update_unit($id, $data);
        return ['success' => true, 'message' => 'واحد سازمانی بروزرسانی شد.'];
    }

    public function delete_unit(int $id): array
    {
        $result = $this->org->delete_unit($id);

        if (!$result) {
            return ['success' => false, 'message' => 'این واحد دارای زیرواحد است و قابل حذف نیست.'];
        }

        WPOA_Activity_Logger::log(WPOA_Activity_Model::ACTION_ORG_UNIT_DELETED, 'unit', $id);

        return ['success' => true, 'message' => 'واحد سازمانی حذف شد.'];
    }

    public function get_roles(): array
    {
        $roles = $this->org->get_all_roles();

        return [
            'success' => true,
            'roles'   => array_map(fn($r) => [
                'id'          => (int) $r->id,
                'name'        => $r->name,
                'slug'        => $r->slug,
                'description' => $r->description ?? '',
            ], $roles),
        ];
    }

    public function create_role(array $data): array
    {
        $name = sanitize_text_field($data['name'] ?? '');
        $desc = sanitize_textarea_field($data['description'] ?? '');

        if (empty($name)) {
            return ['success' => false, 'message' => 'نام نقش الزامی است.'];
        }

        $id = $this->org->create_role($name, $desc);

        if (!$id) {
            return ['success' => false, 'message' => 'نقشی با این نام وجود دارد.'];
        }

        $perm_model = new WPOA_Permission_Model();
        $perm_model->seed_defaults($id);

        WPOA_Activity_Logger::log(WPOA_Activity_Model::ACTION_ORG_ROLE_CREATED, 'role', $id, $name);

        return ['success' => true, 'message' => 'نقش ایجاد شد.', 'role_id' => $id];
    }

    public function update_role(int $id, array $data): array
    {
        $this->org->update_role($id, $data);
        return ['success' => true, 'message' => 'نقش بروزرسانی شد.'];
    }

    public function delete_role(int $id): array
    {
        $result = $this->org->delete_role($id);

        if (!$result) {
            return ['success' => false, 'message' => 'این نقش به کاربرانی اختصاص داده شده و قابل حذف نیست.'];
        }

        WPOA_Activity_Logger::log(WPOA_Activity_Model::ACTION_ORG_ROLE_DELETED, 'role', $id);

        return ['success' => true, 'message' => 'نقش حذف شد.'];
    }

    public function assign_user(int $user_id, int $org_unit_id, int $org_role_id, bool $is_primary): array
{
    if (!$user_id || !$org_unit_id) {
        return ['success' => false, 'message' => 'اطلاعات ناقص است.'];
    }

    $data = [
        'user_id'     => $user_id,
        'org_unit_id' => $org_unit_id,
        'is_primary'  => $is_primary ? 1 : 0,
        'created_at'  => current_time('mysql'),
    ];

    $formats = ['%d', '%d', $org_role_id ? '%d' : '%s', '%d', '%s'];

    global $wpdb;
    $table = $wpdb->prefix . 'wpoa_user_org';

    // Check if assignment already exists
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE user_id = %d AND org_unit_id = %d",
        $user_id, $org_unit_id
    ));

    if ($exists) {
        $wpdb->update($table, $data, ['id' => $exists]);
    } else {
        $wpdb->insert($table, $data, $formats);
    }

    return ['success' => true, 'message' => 'کاربر اختصاص یافت.'];
}

    public function get_unit_users(int $unit_id): array
    {
        return [
            'success' => true,
            'users'   => $this->org->get_unit_users($unit_id),
        ];
    }

    public function get_all_users(int $page = 1): array
    {
        $profile = new WPOA_User_Profile_Model();
        $users   = $profile->get_all_users($page, 50);

        return [
            'success' => true,
            'users'   => array_map(fn($u) => [
                'user_id'       => (int) $u->user_id,
                'display_name'  => $u->display_name,
                'user_login'    => $u->user_login ?? '',
                'email'         => $u->email ?? '',
                'org_role_name' => $u->org_role_name ?? '',
                'org_unit_name' => $u->org_unit_name ?? '',
            ], $users),
        ];
    }
}