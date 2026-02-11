<?php
defined('ABSPATH') || exit;

class WPOA_Ajax
{
    public function register_hooks(): void
    {
        $actions = [
            // Messages
            'wpoa_save_draft'          => 'handle_save_draft',
            'wpoa_send_message'        => 'handle_send_message',
            'wpoa_view_message'        => 'handle_view_message',
            'wpoa_prepare_reply'       => 'handle_prepare_reply',
            'wpoa_prepare_reply_all'   => 'handle_prepare_reply_all',
            'wpoa_prepare_forward'     => 'handle_prepare_forward',
            'wpoa_delete_draft'        => 'handle_delete_draft',

            // Inbox / folders
            'wpoa_get_folder'          => 'handle_get_folder',
            'wpoa_search_messages'     => 'handle_search_messages',
            'wpoa_advanced_search'     => 'handle_advanced_search',
            'wpoa_toggle_star'         => 'handle_toggle_star',
            'wpoa_toggle_pin'          => 'handle_toggle_pin',
            'wpoa_archive_message'     => 'handle_archive_message',
            'wpoa_trash_message'       => 'handle_trash_message',
            'wpoa_restore_message'     => 'handle_restore_message',
            'wpoa_delete_message'      => 'handle_delete_message',
            'wpoa_mark_unread'         => 'handle_mark_unread',
            'wpoa_batch_action'        => 'handle_batch_action',

            // Threading
            'wpoa_get_thread'          => 'handle_get_thread',

            // Attachments
            'wpoa_upload_attachment'   => 'handle_upload_attachment',
            'wpoa_delete_attachment'   => 'handle_delete_attachment',

            // Tags
            'wpoa_get_tags'            => 'handle_get_tags',
            'wpoa_create_tag'          => 'handle_create_tag',
            'wpoa_delete_tag'          => 'handle_delete_tag',

            // Users
            'wpoa_search_users'        => 'handle_search_users',

            // Profile
            'wpoa_get_profile'         => 'handle_get_profile',
            'wpoa_update_profile'      => 'handle_update_profile',
            'wpoa_upload_avatar'       => 'handle_upload_avatar',
            'wpoa_upload_signature'    => 'handle_upload_signature',
            'wpoa_change_password'     => 'handle_change_password',

            // Org management
            'wpoa_get_org_tree'        => 'handle_get_org_tree',
            'wpoa_create_org_unit'     => 'handle_create_org_unit',
            'wpoa_update_org_unit'     => 'handle_update_org_unit',
            'wpoa_delete_org_unit'     => 'handle_delete_org_unit',
            'wpoa_get_roles'           => 'handle_get_roles',
            'wpoa_create_role'         => 'handle_create_role',
            'wpoa_update_role'         => 'handle_update_role',
            'wpoa_delete_role'         => 'handle_delete_role',
            'wpoa_assign_user'         => 'handle_assign_user',
            'wpoa_get_unit_users'      => 'handle_get_unit_users',
            'wpoa_get_all_users'       => 'handle_get_all_users',

            // Settings
            'wpoa_get_settings'        => 'handle_get_settings',
            'wpoa_save_settings'       => 'handle_save_settings',

            // Activity log
            'wpoa_get_activity_log'    => 'handle_get_activity_log',
            'wpoa_get_message_history' => 'handle_get_message_history',

            // Referrals
            'wpoa_create_referral'     => 'handle_create_referral',
            'wpoa_respond_referral'    => 'handle_respond_referral',
            'wpoa_re_refer'            => 'handle_re_refer',
            'wpoa_get_msg_referrals'   => 'handle_get_msg_referrals',
            'wpoa_get_referral_queue'  => 'handle_get_referral_queue',
            'wpoa_get_referral_sent'   => 'handle_get_referral_sent',

            // Margin notes
            'wpoa_add_margin_note'     => 'handle_add_margin_note',
            'wpoa_get_margin_notes'    => 'handle_get_margin_notes',
            'wpoa_delete_margin_note'  => 'handle_delete_margin_note',

            // Read receipts
            'wpoa_get_read_receipts'   => 'handle_get_read_receipts',

            // Permissions
            'wpoa_get_role_perms'      => 'handle_get_role_perms',
            'wpoa_save_role_perms'     => 'handle_save_role_perms',            // org
            'wp_ajax_wpoa_get_org_chart'      => 'handle_get_org_chart',
            'wpoa_remove_assignment'      => 'handle_remove_assignment',

        ];

        foreach ($actions as $action => $method) {
            add_action("wp_ajax_{$action}", [$this, $method]);
        }
    }

    /* ================================================
     * VERIFICATION HELPERS
     * ================================================ */

    private function verify(string $capability = 'read'): int
    {
        check_ajax_referer('wpoa_ajax_nonce', 'nonce');

        if (!current_user_can($capability)) {
            wp_send_json_error(['message' => 'شما دسترسی ندارید.'], 403);
        }

        return get_current_user_id();
    }

    private function post(string $key, string $default = ''): string
    {
        return sanitize_text_field(wp_unslash($_POST[$key] ?? $default));
    }

    private function post_int(string $key, int $default = 0): int
    {
        return absint($_POST[$key] ?? $default);
    }

    private function post_raw(string $key): string
    {
        return wp_kses_post(wp_unslash($_POST[$key] ?? ''));
    }

    private function post_array(string $key): array
    {
        $val = $_POST[$key] ?? '[]';
        if (is_string($val)) {
            $decoded = json_decode(stripslashes($val), true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($val) ? $val : [];
    }

    /* ================================================
     * MESSAGE HANDLERS
     * ================================================ */

    public function handle_save_draft(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Message_Controller();

        $data = [
            'message_id'          => $this->post_int('message_id'),
            'title'               => $this->post('title'),
            'body'                => $this->post_raw('body'),
            'priority'            => $this->post('priority', 'normal'),
            'internal_doc_number' => $this->post('internal_doc_number'),
            'signature_type'      => $this->post('signature_type', 'none'),
            'internal_note'       => $this->post('internal_note'),
            'tags'                => $this->post('tags'),
        ];

        $result = $ctrl->save_draft($user_id, $data);
        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

public function handle_get_org_chart(): void
{
    $this->verify();
    global $wpdb;

    $t_units = $wpdb->prefix . 'wpoa_org_units';
    $t_uo    = $wpdb->prefix . 'wpoa_user_org';
    $t_roles = $wpdb->prefix . 'wpoa_org_roles';
    $t_prof  = $wpdb->prefix . 'wpoa_user_profiles';

    $units = $wpdb->get_results("SELECT * FROM {$t_units} ORDER BY id ASC");

    $assignments = $wpdb->get_results(
        "SELECT uo.org_unit_id, uo.user_id, uo.org_role_id,
                u.display_name, u.user_email,
                r.name AS role_name,
                p.avatar_url
         FROM {$t_uo} uo
         LEFT JOIN {$wpdb->users} u ON u.ID = uo.user_id
         LEFT JOIN {$t_roles} r ON r.id = uo.org_role_id
         LEFT JOIN {$t_prof} p ON p.user_id = uo.user_id
         ORDER BY uo.org_unit_id ASC"
    );

    $unit_users = [];
    foreach ($assignments as $a) {
        $uid = (int) $a->org_unit_id;
        $unit_users[$uid][] = [
            'user_id'      => (int) $a->user_id,
            'display_name' => $a->display_name ?? '',
            'email'        => $a->user_email ?? '',
            'role_name'    => $a->role_name ?? '',
            'avatar_url'   => $a->avatar_url ?? '',
        ];
    }

    $tree = [];
    $this->build_chart_tree($units, $unit_users, null, $tree);

    wp_send_json_success(['tree' => $tree]);
}
public function handle_remove_assignment(): void
{
    $this->verify();

    global $wpdb;

    $user_id     = intval($_POST['user_id'] ?? 0);
    $org_unit_id = intval($_POST['org_unit_id'] ?? 0);

    if (!$user_id || !$org_unit_id) {
        wp_send_json_error(['message' => 'اطلاعات ناقص است.']);
    }

    $table = $wpdb->prefix . 'wpoa_user_org';

    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$table} WHERE user_id = %d AND org_unit_id = %d",
        $user_id, $org_unit_id
    ));

    if ($wpdb->last_error) {
        wp_send_json_error(['message' => $wpdb->last_error]);
    }

    wp_send_json_success(['message' => 'کاربر از موقعیت حذف شد.']);
}

private function build_chart_tree(array $units, array $unit_users, ?int $parent_id, array &$result): void
{
    foreach ($units as $unit) {
        $pid = $unit->parent_id ? (int) $unit->parent_id : null;
        if ($pid !== $parent_id) continue;

        $children = [];
        $this->build_chart_tree($units, $unit_users, (int) $unit->id, $children);

        $result[] = [
            'id'          => (int) $unit->id,
            'name'        => $unit->name,
            'description' => $unit->description ?? '',
            'users'       => $unit_users[(int) $unit->id] ?? [],
            'children'    => $children,
        ];
    }
}


    public function handle_send_message(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Message_Controller();

        $data = [
            'message_id'          => $this->post_int('message_id'),
            'title'               => $this->post('title'),
            'body'                => $this->post_raw('body'),
            'priority'            => $this->post('priority', 'normal'),
            'internal_doc_number' => $this->post('internal_doc_number'),
            'signature_type'      => $this->post('signature_type', 'none'),
            'internal_note'       => $this->post('internal_note'),
            'tags'                => $this->post('tags'),
            'recipients'          => $this->post('recipients'),
            'cc'                  => $this->post('cc'),
            'to_notify_email'     => $this->post('to_notify_email'),
            'to_notify_sms'       => $this->post('to_notify_sms'),
            'cc_notify_email'     => $this->post('cc_notify_email'),
            'cc_notify_sms'       => $this->post('cc_notify_sms'),
            'reply_to_id'         => $this->post_int('reply_to_id'),
            'forward_from_id'     => $this->post_int('forward_from_id'),
        ];

        $result = $ctrl->send($user_id, $data);
        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_view_message(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Message_Controller();
        $result = $ctrl->view($user_id, $message_id);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_prepare_reply(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Message_Controller();
        $result = $ctrl->prepare_reply($user_id, $message_id, false);

        wp_send_json_success($result);
    }

    public function handle_prepare_reply_all(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Message_Controller();
        $result = $ctrl->prepare_reply($user_id, $message_id, true);

        wp_send_json_success($result);
    }

    public function handle_prepare_forward(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Message_Controller();
        $result = $ctrl->prepare_forward($user_id, $message_id);

        wp_send_json_success($result);
    }

    public function handle_delete_draft(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->delete_draft($user_id, $message_id);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    /* ================================================
     * INBOX / FOLDER HANDLERS
     * ================================================ */

    public function handle_get_folder(): void
    {
        $user_id = $this->verify();
        $folder  = $this->post('folder', 'inbox');
        $page    = $this->post_int('page', 1);

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->get_folder($user_id, $folder, $page);

        wp_send_json_success($result);
    }

    public function handle_search_messages(): void
    {
        $user_id = $this->verify();
        $keyword = $this->post('keyword');
        $page    = $this->post_int('page', 1);

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->search($user_id, $keyword, $page);

        wp_send_json_success($result);
    }

    public function handle_advanced_search(): void
    {
        $user_id = $this->verify();

        $filters = [
            'keyword'   => $this->post('keyword'),
            'priority'  => $this->post('priority'),
            'date_from' => $this->post('date_from'),
            'date_to'   => $this->post('date_to'),
            'sender_id' => $this->post_int('sender_id'),
            'tag_id'    => $this->post_int('tag_id'),
            'folder'    => $this->post('folder'),
        ];

        $page    = $this->post_int('page', 1);
        $model   = new WPOA_Message_User_Model();
        $results = $model->advanced_search($user_id, $filters, $page, 20);

        wp_send_json_success([
            'success'  => true,
            'messages' => array_map(fn($row) => [
                'message_id'        => (int) $row->message_id,
                'title'             => $row->title ?: '(بدون عنوان)',
                'priority'          => $row->priority,
                'system_doc_number' => $row->system_doc_number ?? '',
                'sent_at_jalali'    => $row->sent_at_jalali ?? '',
                'is_read'           => (int) $row->is_read,
                'is_starred'        => (int) $row->is_starred,
                'is_pinned'         => (int) $row->is_pinned,
                'thread_id'         => (int) ($row->thread_id ?? 0),
                'sender'            => [
                    'user_id'      => (int) ($row->sender_id ?? 0),
                    'display_name' => $row->sender_display_name ?? '',
                    'avatar_url'   => $row->sender_avatar_url ?? '',
                ],
            ], $results),
        ]);
    }

    public function handle_toggle_star(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->toggle_star($user_id, $message_id);

        wp_send_json_success($result);
    }

    public function handle_toggle_pin(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->toggle_pin($user_id, $message_id);

        wp_send_json_success($result);
    }

    public function handle_archive_message(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->archive($user_id, $message_id);

        wp_send_json_success($result);
    }

    public function handle_trash_message(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->trash($user_id, $message_id);

        wp_send_json_success($result);
    }

    public function handle_restore_message(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->restore($user_id, $message_id);

        wp_send_json_success($result);
    }

    public function handle_delete_message(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->permanent_delete($user_id, $message_id);

        wp_send_json_success($result);
    }

    public function handle_mark_unread(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->mark_unread($user_id, $message_id);

        wp_send_json_success($result);
    }

    public function handle_batch_action(): void
    {
        $user_id     = $this->verify();
        $message_ids = $this->post_array('message_ids');
        $action      = $this->post('batch_action');

        $ctrl   = new WPOA_Inbox_Controller();
        $result = $ctrl->batch_action($user_id, $message_ids, $action);

        wp_send_json_success($result);
    }

    /* ================================================
     * THREADING
     * ================================================ */

    public function handle_get_thread(): void
    {
        $this->verify();
        $message_id = $this->post_int('message_id');

        $model  = new WPOA_Message_Model();
        $thread = $model->get_thread($message_id);

        wp_send_json_success([
            'success' => true,
            'thread'  => array_map(fn($m) => [
                'id'                  => (int) $m->id,
                'title'               => $m->title,
                'body'                => $m->body,
                'priority'            => $m->priority,
                'system_doc_number'   => $m->system_doc_number,
                'sent_at_jalali'      => $m->sent_at_jalali ?? '',
                'sender_display_name' => $m->sender_display_name ?? '',
                'sender_avatar_url'   => $m->sender_avatar_url ?? '',
                'parent_id'           => (int) ($m->parent_id ?? 0),
            ], $thread),
        ]);
    }

    /* ================================================
     * ATTACHMENTS
     * ================================================ */

    public function handle_upload_attachment(): void
    {
        $user_id    = $this->verify();
        $message_id = $this->post_int('message_id');

        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'فایلی انتخاب نشده است.']);
        }

        $settings = new WPOA_Settings_Model();
        $max_mb   = (int) $settings->get('max_attachment_size_mb', '10');
        $allowed  = $settings->get('allowed_attachment_types', 'jpg,jpeg,png,pdf,doc,docx,zip');

        $att_model  = new WPOA_Attachment_Model();
        $validation = $att_model->validate_upload($_FILES['file'], $max_mb, $allowed);

        if (!$validation['valid']) {
            wp_send_json_error(['message' => $validation['error']]);
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $upload = wp_handle_upload($_FILES['file'], ['test_form' => false]);

        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']]);
        }

        $att_id = $att_model->add($message_id, $user_id, [
            'file_name' => $_FILES['file']['name'],
            'file_url'  => $upload['url'],
            'file_path' => $upload['file'],
            'file_size' => $_FILES['file']['size'],
            'file_type' => $upload['type'],
        ]);

        wp_send_json_success([
            'success'       => true,
            'message'       => 'فایل آپلود شد.',
            'attachment_id' => $att_id,
            'file_name'     => $_FILES['file']['name'],
            'file_url'      => $upload['url'],
            'file_size'     => $_FILES['file']['size'],
        ]);
    }

    public function handle_delete_attachment(): void
    {
        $user_id       = $this->verify();
        $attachment_id = $this->post_int('attachment_id');

        $model  = new WPOA_Attachment_Model();
        $result = $model->remove($attachment_id, $user_id);

        $result
            ? wp_send_json_success(['success' => true, 'message' => 'پیوست حذف شد.'])
            : wp_send_json_error(['message' => 'خطا در حذف پیوست.']);
    }

    /* ================================================
     * TAGS
     * ================================================ */

    public function handle_get_tags(): void
    {
        $this->verify();
        $model = new WPOA_Tag_Model();

        wp_send_json_success([
            'success' => true,
            'tags'    => array_map(fn($t) => [
                'id'    => (int) $t->id,
                'name'  => $t->name,
                'color' => $t->color,
            ], $model->get_all()),
        ]);
    }

    public function handle_create_tag(): void
    {
        $this->verify();
        $name  = $this->post('name');
        $color = $this->post('color', '#0073aa');

        $model  = new WPOA_Tag_Model();
        $tag_id = $model->create($name, $color);

        $tag_id
            ? wp_send_json_success(['success' => true, 'message' => 'برچسب ایجاد شد.', 'tag_id' => $tag_id])
            : wp_send_json_error(['message' => 'برچسبی با این نام وجود دارد.']);
    }

    public function handle_delete_tag(): void
    {
        $this->verify('manage_options');
        $tag_id = $this->post_int('tag_id');

        $model = new WPOA_Tag_Model();
        $model->remove($tag_id);

        wp_send_json_success(['success' => true, 'message' => 'برچسب حذف شد.']);
    }

    /* ================================================
     * USER SEARCH
     * ================================================ */

    public function handle_search_users(): void
    {
        $this->verify();
        $keyword = $this->post('keyword');

        $ctrl   = new WPOA_Profile_Controller();
        $result = $ctrl->search_users($keyword);

        wp_send_json_success($result);
    }

    /* ================================================
     * PROFILE
     * ================================================ */

    public function handle_get_profile(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Profile_Controller();
        $result  = $ctrl->get_profile($user_id);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_update_profile(): void
    {
        $user_id = $this->verify();

        $data = [
            'display_name'   => $this->post('display_name'),
            'phone'          => $this->post('phone'),
            'signature_text' => $this->post('signature_text'),
        ];

        $ctrl   = new WPOA_Profile_Controller();
        $result = $ctrl->update_profile($user_id, $data);

        wp_send_json_success($result);
    }

    public function handle_upload_avatar(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Profile_Controller();
        $result  = $ctrl->upload_avatar($user_id);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_upload_signature(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Profile_Controller();
        $result  = $ctrl->upload_signature($user_id);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_change_password(): void
    {
        $user_id = $this->verify();

        $data = [
            'current_password' => $_POST['current_password'] ?? '',
            'new_password'     => $_POST['new_password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
        ];

        $ctrl   = new WPOA_Profile_Controller();
        $result = $ctrl->change_password($user_id, $data);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    /* ================================================
     * ORG MANAGEMENT
     * ================================================ */

    public function handle_get_org_tree(): void
    {
        $this->verify();
        $ctrl = new WPOA_Org_Controller();
        wp_send_json_success($ctrl->get_tree());
    }

public function handle_create_org_unit(): void
{
    $this->verify();
    global $wpdb;

    $name      = sanitize_text_field($_POST['name'] ?? '');
    $parent_id = intval($_POST['parent_id'] ?? 0);
    $desc      = sanitize_textarea_field($_POST['description'] ?? '');

    if (!$name) {
        wp_send_json_error(['message' => 'نام واحد الزامی است.']);
    }

    $table = $wpdb->prefix . 'wpoa_org_units';

    try {
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            wp_send_json_error(['message' => 'جدول واحدها وجود ندارد.']);
        }

        $wpdb->insert(
            $table,
            [
                'name'        => $name,
                'parent_id'   => $parent_id ?: null,
                'description' => $desc,
                'created_at'  => current_time('mysql'),
            ],
            ['%s', $parent_id ? '%d' : '%s', '%s', '%s']
        );

        if ($wpdb->last_error) {
            wp_send_json_error(['message' => 'خطای پایگاه داده: ' . $wpdb->last_error]);
        }

        wp_send_json_success([
            'message' => 'واحد ایجاد شد.',
            'unit_id' => $wpdb->insert_id,
            'id'      => $wpdb->insert_id,
        ]);

    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'خطا: ' . $e->getMessage()]);
    }
}

public function handle_update_org_unit(): void
{
    $this->verify();
    global $wpdb;

    $unit_id = intval($_POST['unit_id'] ?? 0);
    $name    = sanitize_text_field($_POST['name'] ?? '');
    $desc    = sanitize_textarea_field($_POST['description'] ?? '');

    if (!$unit_id || !$name) {
        wp_send_json_error(['message' => 'شناسه و عنوان الزامی است.']);
    }

    $table = $wpdb->prefix . 'wpoa_org_units';

    try {
        $wpdb->update(
            $table,
            ['name' => $name, 'description' => $desc],
            ['id' => $unit_id],
            ['%s', '%s'],
            ['%d']
        );

        if ($wpdb->last_error) {
            wp_send_json_error(['message' => 'خطا: ' . $wpdb->last_error]);
        }

        wp_send_json_success(['message' => 'ذخیره شد.']);
    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'خطا: ' . $e->getMessage()]);
    }
}

public function handle_delete_org_unit(): void
{
    $this->verify();
    global $wpdb;

    $unit_id = intval($_POST['unit_id'] ?? 0);
    if (!$unit_id) {
        wp_send_json_error(['message' => 'شناسه الزامی است.']);
    }

    $t_units = $wpdb->prefix . 'wpoa_org_units';
    $t_uo    = $wpdb->prefix . 'wpoa_user_org';

    try {
        // Get all child IDs recursively
        $all_ids = [$unit_id];
        $children = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$t_units} WHERE parent_id = %d", $unit_id
        ));
        while (!empty($children)) {
            $all_ids = array_merge($all_ids, $children);
            $placeholders = implode(',', array_fill(0, count($children), '%d'));
            $children = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$t_units} WHERE parent_id IN ({$placeholders})",
                ...$children
            ));
        }

        // Delete assignments
        $ids_str = implode(',', array_map('intval', $all_ids));
        $wpdb->query("DELETE FROM {$t_uo} WHERE org_unit_id IN ({$ids_str})");

        // Delete units
        $wpdb->query("DELETE FROM {$t_units} WHERE id IN ({$ids_str})");

        wp_send_json_success(['message' => 'حذف شد.']);

    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'خطا: ' . $e->getMessage()]);
    }
}

    public function handle_get_roles(): void
    {
        $this->verify();
        $ctrl = new WPOA_Org_Controller();
        wp_send_json_success($ctrl->get_roles());
    }

    public function handle_create_role(): void
    {
        $this->verify('manage_options');
        $ctrl   = new WPOA_Org_Controller();
        $result = $ctrl->create_role([
            'name'        => $this->post('name'),
            'description' => $this->post('description'),
        ]);
        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_update_role(): void
    {
        $this->verify('manage_options');
        $id   = $this->post_int('role_id');
        $ctrl = new WPOA_Org_Controller();
        $result = $ctrl->update_role($id, [
            'name'        => $this->post('name'),
            'description' => $this->post('description'),
        ]);
        wp_send_json_success($result);
    }

    public function handle_delete_role(): void
    {
        $this->verify('manage_options');
        $id     = $this->post_int('role_id');
        $ctrl   = new WPOA_Org_Controller();
        $result = $ctrl->delete_role($id);
        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

public function handle_assign_user(): void
{
    $this->verify();
    global $wpdb;

    $table       = $wpdb->prefix . 'wpoa_user_org';
    $org_unit_id = intval($_POST['org_unit_id'] ?? 0);

    if (!$org_unit_id) {
        wp_send_json_error(['message' => 'واحد سازمانی الزامی است.']);
    }

    // Parse user IDs
    $raw = $_POST['user_ids'] ?? $_POST['user_id'] ?? '';
    $user_ids = [];

    if (is_string($raw) && substr($raw, 0, 1) === '[') {
        $decoded = json_decode(stripslashes($raw), true);
        if (is_array($decoded)) $user_ids = array_map('intval', $decoded);
    } elseif (is_array($raw)) {
        $user_ids = array_map('intval', $raw);
    } else {
        $v = intval($raw);
        if ($v) $user_ids[] = $v;
    }

    $user_ids = array_filter(array_unique($user_ids));
    if (empty($user_ids)) {
        wp_send_json_error(['message' => 'حداقل یک کاربر انتخاب کنید.']);
    }

    try {
        if (!$wpdb->get_var("SHOW TABLES LIKE '{$table}'")) {
            wp_send_json_error(['message' => 'جدول وجود ندارد.']);
        }

        $count = 0;
        $moved = [];

        foreach ($user_ids as $uid) {
            // Check current assignment
            $current = $wpdb->get_row($wpdb->prepare(
                "SELECT uo.org_unit_id, u2.name AS unit_name
                 FROM {$table} uo
                 LEFT JOIN {$wpdb->prefix}wpoa_org_units u2 ON u2.id = uo.org_unit_id
                 WHERE uo.user_id = %d LIMIT 1",
                $uid
            ));

            // Remove from all positions (one position per user)
            $wpdb->delete($table, ['user_id' => $uid], ['%d']);

            // Assign to new position
            $result = $wpdb->insert($table, [
                'user_id'     => $uid,
                'org_unit_id' => $org_unit_id,
                'org_role_id' => 0,
                'is_primary'  => 1,
                'created_at'  => current_time('mysql'),
            ], ['%d', '%d', '%d', '%d', '%s']);

            if ($result !== false) {
                $count++;
                if ($current && (int) $current->org_unit_id !== $org_unit_id) {
                    $moved[] = $current->unit_name ?? '';
                }
            }
        }

        if ($wpdb->last_error) {
            wp_send_json_error(['message' => 'خطا: ' . $wpdb->last_error]);
        }

        $msg = $count . ' کاربر اختصاص یافت.';
        if (!empty($moved)) {
            $msg .= ' (جابجایی از: ' . implode('، ', array_filter($moved)) . ')';
        }

        wp_send_json_success(['message' => $msg]);

    } catch (Throwable $e) {
        wp_send_json_error(['message' => 'خطا: ' . $e->getMessage()]);
    }
}

    public function handle_get_unit_users(): void
    {
        $this->verify();
        $unit_id = $this->post_int('unit_id');
        $ctrl    = new WPOA_Org_Controller();
        wp_send_json_success($ctrl->get_unit_users($unit_id));
    }

    public function handle_get_all_users(): void
    {
        $this->verify();
        $page = $this->post_int('page', 1);
        $ctrl = new WPOA_Org_Controller();
        wp_send_json_success($ctrl->get_all_users($page));
    }

    /* ================================================
     * SETTINGS
     * ================================================ */

    public function handle_get_settings(): void
    {
        $this->verify('manage_options');
        $ctrl = new WPOA_Settings_Controller();
        wp_send_json_success($ctrl->get());
    }

    public function handle_save_settings(): void
    {
        $this->verify('manage_options');

        $keys = [
            'org_name', 'messages_per_page',
            'email_notifications_enabled', 'sms_notifications_enabled',
            'sms_api_provider', 'sms_api_key', 'sms_sender_number',
            'max_attachment_size_mb', 'allowed_attachment_types',
        ];

        $data = [];
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $data[$key] = $this->post($key);
            }
        }

        $ctrl   = new WPOA_Settings_Controller();
        $result = $ctrl->save($data);

        wp_send_json_success($result);
    }

    /* ================================================
     * ACTIVITY LOG
     * ================================================ */

    public function handle_get_activity_log(): void
    {
        $this->verify('manage_options');

        $page    = $this->post_int('page', 1);
        $filters = [
            'user_id'   => $this->post_int('user_id'),
            'action'    => $this->post('action_filter'),
            'date_from' => $this->post('date_from'),
            'date_to'   => $this->post('date_to'),
        ];

        $model = new WPOA_Activity_Model();
        $logs  = $model->get_recent($page, 30, $filters);
        $total = $model->count_filtered($filters);

        wp_send_json_success([
            'success'     => true,
            'logs'        => array_map(fn($l) => [
                'id'           => (int) $l->id,
                'user_name'    => $l->user_display_name ?? '',
                'avatar_url'   => $l->user_avatar_url ?? '',
                'action'       => $l->action,
                'action_label' => WPOA_Activity_Model::get_action_label($l->action),
                'object_type'  => $l->object_type,
                'object_id'    => (int) $l->object_id,
                'details'      => $l->details,
                'ip_address'   => $l->ip_address,
                'created_at'   => $l->created_at,
            ], $logs),
            'total'       => $total,
            'total_pages' => (int) ceil($total / 30),
        ]);
    }

    public function handle_get_message_history(): void
    {
        $this->verify();
        $message_id = $this->post_int('message_id');

        $model   = new WPOA_Activity_Model();
        $history = $model->get_message_history($message_id);

        wp_send_json_success([
            'success' => true,
            'history' => array_map(fn($h) => [
                'user_name'    => $h->user_display_name ?? '',
                'action_label' => WPOA_Activity_Model::get_action_label($h->action),
                'details'      => $h->details,
                'created_at'   => $h->created_at,
            ], $history),
        ]);
    }

    /* ================================================
     * REFERRALS
     * ================================================ */

    public function handle_create_referral(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        $result  = $ctrl->create_referral([
            'message_id'      => $this->post_int('message_id'),
            'to_user_id'      => $this->post_int('to_user_id'),
            'type'            => $this->post('type', 'referral'),
            'instruction'     => $this->post('instruction'),
            'deadline'        => $this->post('deadline'),
            'deadline_jalali' => $this->post('deadline_jalali'),
        ], $user_id);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_respond_referral(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        $result  = $ctrl->respond(
            $this->post_int('referral_id'),
            $user_id,
            $this->post('status', 'completed'),
            $this->post('response')
        );

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_re_refer(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        $result  = $ctrl->re_refer($this->post_int('parent_ref_id'), [
            'to_user_id'      => $this->post_int('to_user_id'),
            'type'            => $this->post('type', 'referral'),
            'instruction'     => $this->post('instruction'),
            'deadline'        => $this->post('deadline'),
            'deadline_jalali' => $this->post('deadline_jalali'),
        ], $user_id);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_get_msg_referrals(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        wp_send_json_success($ctrl->get_message_referrals($this->post_int('message_id'), $user_id));
    }

    public function handle_get_referral_queue(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        wp_send_json_success($ctrl->get_my_queue($user_id, $this->post_int('page', 1)));
    }

    public function handle_get_referral_sent(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        wp_send_json_success($ctrl->get_my_sent($user_id, $this->post_int('page', 1)));
    }

    /* ================================================
     * MARGIN NOTES
     * ================================================ */

    public function handle_add_margin_note(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        $result  = $ctrl->add_note(
            $this->post_int('message_id'),
            $user_id,
            $this->post('note_text'),
            $this->post('is_private', '0') === '1',
            $this->post_int('referral_id') ?: null
        );

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    public function handle_get_margin_notes(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        wp_send_json_success($ctrl->get_notes($this->post_int('message_id'), $user_id));
    }

    public function handle_delete_margin_note(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        $result  = $ctrl->delete_note($this->post_int('note_id'), $user_id);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    /* ================================================
     * READ RECEIPTS
     * ================================================ */

    public function handle_get_read_receipts(): void
    {
        $user_id = $this->verify();
        $ctrl    = new WPOA_Referral_Controller();
        $result  = $ctrl->get_read_receipts($this->post_int('message_id'), $user_id);

        $result['success'] ? wp_send_json_success($result) : wp_send_json_error($result);
    }

    /* ================================================
     * PERMISSIONS
     * ================================================ */

    public function handle_get_role_perms(): void
    {
        $this->verify('manage_options');
        $role_id = $this->post_int('role_id');

        $model = new WPOA_Permission_Model();
        wp_send_json_success([
            'success'     => true,
            'permissions' => $model->get_role_permissions_full($role_id),
        ]);
    }

    public function handle_save_role_perms(): void
    {
        $this->verify('manage_options');
        $role_id = $this->post_int('role_id');
        $perms   = $this->post_array('permissions');

        $clean = [];
        foreach ($perms as $key => $val) {
            $clean[sanitize_key($key)] = ($val === '1' || $val === true);
        }

        $model = new WPOA_Permission_Model();
        $model->set_role_permissions($role_id, $clean);

        WPOA_Permission::flush();
        WPOA_Activity_Logger::log('permissions_updated', 'role', $role_id);

        wp_send_json_success(['success' => true, 'message' => 'مجوزها ذخیره شد.']);
    }
}