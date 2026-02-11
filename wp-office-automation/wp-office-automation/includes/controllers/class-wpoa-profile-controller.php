<?php
defined('ABSPATH') || exit;

class WPOA_Profile_Controller
{
    private WPOA_User_Profile_Model $profile;

    public function __construct()
    {
        $this->profile = new WPOA_User_Profile_Model();
    }

    public function get_profile(int $user_id): array
    {
        $p = $this->profile->get_full_profile($user_id);

        if (!$p) {
            return ['success' => false, 'message' => 'پروفایل یافت نشد.'];
        }

        return [
            'success' => true,
            'profile' => [
                'user_id'             => (int) $p->user_id,
                'display_name'        => $p->display_name,
                'email'               => $p->email ?? '',
                'phone'               => $p->phone ?? '',
                'avatar_url'          => $p->avatar_url ?? '',
                'signature_text'      => $p->signature_text ?? '',
                'signature_image_url' => $p->signature_image_url ?? '',
                'org_role_name'       => $p->org_role_name ?? '',
                'org_unit_name'       => $p->org_unit_name ?? '',
            ],
        ];
    }

    public function update_profile(int $user_id, array $data): array
    {
        $this->profile->update_profile($user_id, $data);

        WPOA_Activity_Logger::log(WPOA_Activity_Model::ACTION_PROFILE_UPDATED, 'user', $user_id);

        return ['success' => true, 'message' => 'پروفایل بروزرسانی شد.'];
    }

    public function upload_avatar(int $user_id): array
    {
        if (empty($_FILES['avatar'])) {
            return ['success' => false, 'message' => 'فایلی انتخاب نشده است.'];
        }

        $file = $_FILES['avatar'];

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return ['success' => false, 'message' => $upload['error']];
        }

        $this->profile->update_avatar($user_id, $upload['url']);

        return [
            'success'  => true,
            'message'  => 'تصویر پروفایل بارگذاری شد.',
            'file_url' => $upload['url'],
        ];
    }

    public function upload_signature(int $user_id): array
    {
        if (empty($_FILES['signature_image'])) {
            return ['success' => false, 'message' => 'فایلی انتخاب نشده است.'];
        }

        $file = $_FILES['signature_image'];

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $upload = wp_handle_upload($file, ['test_form' => false]);

        if (isset($upload['error'])) {
            return ['success' => false, 'message' => $upload['error']];
        }

        $this->profile->update_signature_image($user_id, $upload['url']);

        return [
            'success'  => true,
            'message'  => 'تصویر امضا بارگذاری شد.',
            'file_url' => $upload['url'],
        ];
    }

    public function change_password(int $user_id, array $data): array
    {
        $current = $data['current_password'] ?? '';
        $new     = $data['new_password'] ?? '';
        $confirm = $data['confirm_password'] ?? '';

        if (empty($current) || empty($new)) {
            return ['success' => false, 'message' => 'رمز عبور فعلی و جدید الزامی است.'];
        }

        if ($new !== $confirm) {
            return ['success' => false, 'message' => 'رمز عبور جدید و تکرار آن مطابقت ندارد.'];
        }

        if (strlen($new) < 6) {
            return ['success' => false, 'message' => 'رمز عبور جدید باید حداقل ۶ کاراکتر باشد.'];
        }

        $user = get_userdata($user_id);

        if (!$user || !wp_check_password($current, $user->user_pass, $user_id)) {
            return ['success' => false, 'message' => 'رمز عبور فعلی صحیح نیست.'];
        }

        wp_set_password($new, $user_id);

        WPOA_Activity_Logger::log(WPOA_Activity_Model::ACTION_PASSWORD_CHANGED, 'user', $user_id);

        return ['success' => true, 'message' => 'رمز عبور با موفقیت تغییر کرد. لطفاً دوباره وارد شوید.'];
    }

    public function search_users(string $keyword): array
    {
        $users = $this->profile->search_users($keyword);

        return [
            'success' => true,
            'users'   => array_map(fn($u) => [
                'user_id'       => (int) $u->user_id,
                'display_name'  => $u->display_name,
                'avatar_url'    => $u->avatar_url ?? '',
                'org_role_name' => $u->org_role_name ?? '',
            ], $users),
        ];
    }
}