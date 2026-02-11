<?php
/**
 * Standalone AJAX Handlers
 *
 * @package WPOA
 */
defined('ABSPATH') || exit;

// ── Remove User From Org Position ──
add_action('wp_ajax_wpoa_remove_assignment', function () {
    if (!get_current_user_id()) {
        wp_send_json_error(['message' => 'وارد نشده‌اید.']);
        wp_die();
    }

    global $wpdb;
    $user_id     = intval($_POST['user_id'] ?? 0);
    $org_unit_id = intval($_POST['org_unit_id'] ?? 0);

    if (!$user_id || !$org_unit_id) {
        wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
        wp_die();
    }

    $table = $wpdb->prefix . 'wpoa_user_org';
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE user_id = %d AND org_unit_id = %d",
        $user_id, $org_unit_id
    ));

    if ($wpdb->last_error) {
        wp_send_json_error(['message' => $wpdb->last_error]);
        wp_die();
    }

    wp_send_json_success(['message' => 'کاربر از موقعیت حذف شد.']);
    wp_die();
});

// ── Create User ──
add_action('wp_ajax_wpoa_admin_create_user', function () {
    if (!current_user_can('create_users')) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
        wp_die();
    }

    $name     = sanitize_text_field($_POST['display_name'] ?? '');
    $email    = sanitize_email($_POST['email'] ?? '');
    $phone    = sanitize_text_field($_POST['phone'] ?? '');
    $sig_text = sanitize_textarea_field($_POST['sig_text'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = sanitize_text_field($_POST['role'] ?? 'subscriber');
    $unit_id  = intval($_POST['unit_id'] ?? 0);

    if (!$name || !$email) {
        wp_send_json_error(['message' => 'نام و ایمیل الزامی است.']);
        wp_die();
    }

    if (email_exists($email)) {
        wp_send_json_error(['message' => 'این ایمیل قبلاً ثبت شده.']);
        wp_die();
    }

    if (!$password) {
        $password = wp_generate_password(12);
    }

    $user_id = wp_insert_user([
        'user_login'   => $email,
        'user_email'   => $email,
        'display_name' => $name,
        'user_pass'    => $password,
        'role'         => $role,
    ]);

    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => $user_id->get_error_message()]);
        wp_die();
    }

    global $wpdb;

    $t = $wpdb->prefix . 'wpoa_user_profiles';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'")) {
        $wpdb->insert($t, [
            'user_id'        => $user_id,
            'phone'          => $phone,
            'signature_text' => $sig_text,
            'created_at'     => current_time('mysql'),
        ]);
    }

    if ($unit_id) {
        $t2 = $wpdb->prefix . 'wpoa_user_org';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t2}'")) {
            $wpdb->insert($t2, [
                'user_id'     => $user_id,
                'org_unit_id' => $unit_id,
                'org_role_id' => 0,
                'is_primary'  => 1,
                'created_at'  => current_time('mysql'),
            ]);
        }
    }

    wp_send_json_success(['message' => 'کاربر ایجاد شد.', 'user_id' => $user_id]);
    wp_die();
});

// ── Update User ──
add_action('wp_ajax_wpoa_admin_update_user', function () {
    if (!current_user_can('edit_users')) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
        wp_die();
    }

    $uid      = intval($_POST['user_id'] ?? 0);
    $name     = sanitize_text_field($_POST['display_name'] ?? '');
    $email    = sanitize_email($_POST['email'] ?? '');
    $phone    = sanitize_text_field($_POST['phone'] ?? '');
    $sig_text = sanitize_textarea_field($_POST['sig_text'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = sanitize_text_field($_POST['role'] ?? '');
    $unit_id  = intval($_POST['unit_id'] ?? 0);

    if (!$uid || !$name || !$email) {
        wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
        wp_die();
    }

    $existing = email_exists($email);
    if ($existing && $existing !== $uid) {
        wp_send_json_error(['message' => 'این ایمیل توسط کاربر دیگری استفاده شده.']);
        wp_die();
    }

    $args = ['ID' => $uid, 'display_name' => $name, 'user_email' => $email];
    if ($password) {
        $args['user_pass'] = $password;
    }

    $result = wp_update_user($args);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
        wp_die();
    }

    if ($role) {
        $u = get_userdata($uid);
        if ($u) {
            $u->set_role($role);
        }
    }

    global $wpdb;

    $t = $wpdb->prefix . 'wpoa_user_profiles';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'")) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE user_id = %d", $uid));
        if ($exists) {
            $wpdb->update($t, [
                'phone'          => $phone,
                'signature_text' => $sig_text,
            ], ['user_id' => $uid]);
        } else {
            $wpdb->insert($t, [
                'user_id'        => $uid,
                'phone'          => $phone,
                'signature_text' => $sig_text,
                'created_at'     => current_time('mysql'),
            ]);
        }
    }

    $t2 = $wpdb->prefix . 'wpoa_user_org';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t2}'")) {
        $wpdb->delete($t2, ['user_id' => $uid], ['%d']);
        if ($unit_id) {
            $wpdb->insert($t2, [
                'user_id'     => $uid,
                'org_unit_id' => $unit_id,
                'org_role_id' => 0,
                'is_primary'  => 1,
                'created_at'  => current_time('mysql'),
            ]);
        }
    }

    wp_send_json_success(['message' => 'اطلاعات کاربر ذخیره شد.']);
    wp_die();
});

// ── Delete User ──
add_action('wp_ajax_wpoa_admin_delete_user', function () {
    if (!current_user_can('delete_users')) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
        wp_die();
    }

    require_once ABSPATH . 'wp-admin/includes/user.php';

    $uid = intval($_POST['user_id'] ?? 0);

    if (!$uid) {
        wp_send_json_error(['message' => 'شناسه کاربر الزامی است.']);
        wp_die();
    }

    if ($uid === get_current_user_id()) {
        wp_send_json_error(['message' => 'نمی‌توانید خودتان را حذف کنید.']);
        wp_die();
    }

    $deleted = wp_delete_user($uid);
    if (!$deleted) {
        wp_send_json_error(['message' => 'حذف ناموفق بود.']);
        wp_die();
    }

    global $wpdb;

    $t1 = $wpdb->prefix . 'wpoa_user_org';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t1}'")) {
        $wpdb->delete($t1, ['user_id' => $uid], ['%d']);
    }

    $t2 = $wpdb->prefix . 'wpoa_user_profiles';
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t2}'")) {
        $wpdb->delete($t2, ['user_id' => $uid], ['%d']);
    }

    wp_send_json_success(['message' => 'کاربر حذف شد.']);
    wp_die();
});

// ── Upload Avatar (Admin) ──
add_action('wp_ajax_wpoa_admin_upload_avatar', function () {
    if (!current_user_can('edit_users')) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
        wp_die();
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id) {
        wp_send_json_error(['message' => 'شناسه کاربر مشخص نیست.']);
        wp_die();
    }

    if (empty($_FILES['avatar'])) {
        wp_send_json_error(['message' => 'فایلی انتخاب نشده.']);
        wp_die();
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_upload('avatar', 0);
    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => $attachment_id->get_error_message()]);
        wp_die();
    }

    $url = wp_get_attachment_url($attachment_id);

    global $wpdb;
    $t = $wpdb->prefix . 'wpoa_user_profiles';

    if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'")) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE user_id = %d", $user_id));
        if ($exists) {
            $wpdb->update($t, ['avatar_url' => $url], ['user_id' => $user_id]);
        } else {
            $wpdb->insert($t, [
                'user_id'    => $user_id,
                'avatar_url' => $url,
                'created_at' => current_time('mysql'),
            ]);
        }
    }

    wp_send_json_success(['url' => $url, 'message' => 'تصویر ذخیره شد.']);
    wp_die();
});

// ── Upload Signature Image (Admin) ──
add_action('wp_ajax_wpoa_admin_upload_signature', function () {
    if (!current_user_can('edit_users')) {
        wp_send_json_error(['message' => 'دسترسی ندارید.']);
        wp_die();
    }

    $user_id = intval($_POST['user_id'] ?? 0);
    if (!$user_id) {
        wp_send_json_error(['message' => 'شناسه کاربر مشخص نیست.']);
        wp_die();
    }

    if (empty($_FILES['signature'])) {
        wp_send_json_error(['message' => 'فایلی انتخاب نشده.']);
        wp_die();
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $attachment_id = media_handle_upload('signature', 0);
    if (is_wp_error($attachment_id)) {
        wp_send_json_error(['message' => $attachment_id->get_error_message()]);
        wp_die();
    }

    $url = wp_get_attachment_url($attachment_id);

    global $wpdb;
    $t = $wpdb->prefix . 'wpoa_user_profiles';

    if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'")) {
        $exists = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE user_id = %d", $user_id));
        if ($exists) {
            $wpdb->update($t, ['signature_image_url' => $url], ['user_id' => $user_id]);
        } else {
            $wpdb->insert($t, [
                'user_id'             => $user_id,
                'signature_image_url' => $url,
                'created_at'          => current_time('mysql'),
            ]);
        }
    }

    wp_send_json_success(['url' => $url, 'message' => 'تصویر امضا ذخیره شد.']);
    wp_die();
});