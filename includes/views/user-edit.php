<?php
defined('ABSPATH') || exit;

if (!current_user_can('manage_options')) {
    wp_die('شما دسترسی ندارید.');
}

global $wpdb;

$t_prof  = $wpdb->prefix . 'wpoa_user_profiles';
$t_uo    = $wpdb->prefix . 'wpoa_user_org';
$t_units = $wpdb->prefix . 'wpoa_org_units';

// ── Mode: edit or create ──
$edit_user_id = intval($_GET['user_id'] ?? 0);
$is_edit      = (bool) $edit_user_id;
$page_title   = $is_edit ? 'ویرایش کاربر' : 'ایجاد کاربر جدید';

// ── Default values ──
$display_name      = '';
$email             = '';
$phone             = '';
$sig_text          = '';
$avatar_url        = '';
$sig_img_url       = '';
$user_role         = 'subscriber';
$org_position_name = '';
$org_unit_id       = 0;
$superior_name     = '';
$superior_position = '';
$superior_avatar   = '';

// ── Load existing user data ──
if ($is_edit) {
    $target_user = get_userdata($edit_user_id);
    if (!$target_user) {
        wp_die('کاربر یافت نشد.');
    }

    $display_name = $target_user->display_name;
    $email        = $target_user->user_email;
    $user_role    = !empty($target_user->roles) ? $target_user->roles[0] : 'subscriber';

    // Profile data
    try {
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t_prof}'")) {
            $p = $wpdb->get_row($wpdb->prepare(
                "SELECT phone, signature_text, avatar_url, signature_image_url
                 FROM {$t_prof} WHERE user_id = %d LIMIT 1",
                $edit_user_id
            ));
            if ($p) {
                $phone       = $p->phone ?? '';
                $sig_text    = $p->signature_text ?? '';
                $avatar_url  = $p->avatar_url ?? '';
                $sig_img_url = $p->signature_image_url ?? '';
            }
        }
    } catch (Throwable $e) {}

    // Org data
    try {
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t_uo}'")) {
            $my_unit = $wpdb->get_row($wpdb->prepare(
                "SELECT uo.org_unit_id, un.name AS unit_name, un.parent_id
                 FROM {$t_uo} uo
                 LEFT JOIN {$t_units} un ON un.id = uo.org_unit_id
                 WHERE uo.user_id = %d LIMIT 1",
                $edit_user_id
            ));
            if ($my_unit) {
                $org_position_name = $my_unit->unit_name ?? '';
                $org_unit_id       = (int) $my_unit->org_unit_id;

                if ($my_unit->parent_id) {
                    $parent = $wpdb->get_row($wpdb->prepare(
                        "SELECT id, name FROM {$t_units} WHERE id = %d",
                        (int) $my_unit->parent_id
                    ));
                    if ($parent) {
                        $superior_position = $parent->name;
                        $sup = $wpdb->get_row($wpdb->prepare(
                            "SELECT u.display_name, p.avatar_url
                             FROM {$t_uo} uo
                             LEFT JOIN {$wpdb->users} u ON u.ID = uo.user_id
                             LEFT JOIN {$t_prof} p ON p.user_id = uo.user_id
                             WHERE uo.org_unit_id = %d LIMIT 1",
                            (int) $parent->id
                        ));
                        if ($sup) {
                            $superior_name   = $sup->display_name ?? '';
                            $superior_avatar = $sup->avatar_url ?? '';
                        }
                    }
                }
            }
        }
    } catch (Throwable $e) {}
}

// ── All positions for dropdown ──
$all_positions = [];
try {
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t_units}'")) {
        $pos_raw = $wpdb->get_results("SELECT id, name FROM {$t_units} ORDER BY id ASC");
        foreach ($pos_raw as $p) {
            $all_positions[] = ['id' => (int) $p->id, 'name' => $p->name];
        }
    }
} catch (Throwable $e) {}

// ── WP Roles ──
$wp_roles = wp_roles()->roles;

// ── URLs ──
$back_url = admin_url('admin.php?page=wpoa-users');
?>
<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-header.php'; ?>

<!-- ═══════════ HERO ═══════════ -->
<div class="wpoa-profile-hero">
    <div class="wpoa-hero-content">
        <div class="wpoa-hero-avatar" id="wpoa-ue-avatar-wrap">
            <?php if ($avatar_url): ?>
                <img src="<?php echo esc_url($avatar_url); ?>" alt="" id="wpoa-ue-avatar-img">
            <?php else: ?>
                <span class="dashicons dashicons-admin-users" id="wpoa-ue-avatar-ph"></span>
            <?php endif; ?>
        </div>

        <div class="wpoa-hero-info">
            <h2 class="wpoa-hero-name"><?php echo $is_edit ? esc_html($display_name) : 'کاربر جدید'; ?></h2>
            <p class="wpoa-hero-email"><?php echo $is_edit ? esc_html($email) : 'ایجاد حساب کاربری جدید در سیستم'; ?></p>
            <?php if ($org_position_name): ?>
                <span class="wpoa-hero-role">
                    <span class="dashicons dashicons-businessman"></span>
                    <?php echo esc_html($org_position_name); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="wpoa-hero-stats">
            <a href="<?php echo esc_url($back_url); ?>" class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" style="text-decoration:none;">
                <span class="dashicons dashicons-arrow-right-alt"></span> بازگشت به لیست
            </a>
        </div>
    </div>
</div>

<!-- ═══════════ BODY ═══════════ -->
<div class="wpoa-profile-body" id="wpoa-ue-page">
    <div class="wpoa-profile-columns">

        <!-- ── RIGHT COLUMN ── -->
        <div class="wpoa-profile-col">

            <!-- Personal Info -->
            <div class="wpoa-profile-section-glass">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--accent-light);color:var(--accent);">
                        <span class="dashicons dashicons-admin-users"></span>
                    </span>
                    <h3>اطلاعات شخصی</h3>
                </div>
                <div class="wpoa-profile-fields">
                    <div class="wpoa-pf-field">
                        <label for="wpoa-ue-name">نام نمایشی *</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-nametag"></span>
                            <input type="text" id="wpoa-ue-name" value="<?php echo esc_attr($display_name); ?>" placeholder="نام و نام خانوادگی">
                        </div>
                    </div>
                    <div class="wpoa-pf-field">
                        <label for="wpoa-ue-email">ایمیل *</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-email-alt2"></span>
                            <input type="email" id="wpoa-ue-email" value="<?php echo esc_attr($email); ?>" dir="ltr" placeholder="user@example.com">
                        </div>
                    </div>
                    <div class="wpoa-pf-field">
                        <label for="wpoa-ue-phone">شماره تلفن</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-phone"></span>
                            <input type="text" id="wpoa-ue-phone" value="<?php echo esc_attr($phone); ?>" dir="ltr" placeholder="09XXXXXXXXX">
                        </div>
                    </div>
                    <div class="wpoa-pf-field">
                        <label for="wpoa-ue-role">نقش وردپرس</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-shield"></span>
                            <select id="wpoa-ue-role" style="width:100%;padding:10px 14px;padding-right:40px;border:none;background:transparent;font-family:var(--font);font-size:13px;appearance:auto;">
                                <?php foreach ($wp_roles as $key => $r): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($user_role, $key); ?>>
                                        <?php echo esc_html($r['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Organizational Info -->
            <div class="wpoa-profile-section-glass">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--purple-light);color:var(--purple);">
                        <span class="dashicons dashicons-networking"></span>
                    </span>
                    <h3>اطلاعات سازمانی</h3>
                </div>
                <div class="wpoa-profile-fields">
                    <div class="wpoa-pf-field">
                        <label for="wpoa-ue-unit">موقعیت سازمانی</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-building"></span>
                            <select id="wpoa-ue-unit" style="width:100%;padding:10px 14px;padding-right:40px;border:none;background:transparent;font-family:var(--font);font-size:13px;appearance:auto;">
                                <option value="0">— بدون موقعیت —</option>
                                <?php foreach ($all_positions as $pos): ?>
                                    <option value="<?php echo $pos['id']; ?>" <?php selected($org_unit_id, $pos['id']); ?>>
                                        <?php echo esc_html($pos['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <?php if ($is_edit): ?>
                    <div class="wpoa-pf-field">
                        <label>مافوق سازمانی</label>
                        <?php if ($superior_name): ?>
                            <div style="display:flex;align-items:center;gap:12px;padding:14px;background:rgba(88,86,214,0.05);border:1.5px solid rgba(88,86,214,0.12);border-radius:var(--glass-radius-xs);">
                                <div style="width:44px;height:44px;border-radius:50%;overflow:hidden;border:2.5px solid rgba(88,86,214,0.2);flex-shrink:0;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,0.5);">
                                    <?php if ($superior_avatar): ?>
                                        <img src="<?php echo esc_url($superior_avatar); ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%;" alt="">
                                    <?php else: ?>
                                        <span class="dashicons dashicons-admin-users" style="color:#5856D6;opacity:0.5;width:22px;height:22px;font-size:22px;"></span>
                                    <?php endif; ?>
                                </div>
                                <div style="flex:1;">
                                    <strong style="font-family:var(--font);font-size:13px;display:block;line-height:1.5;">
                                        <?php echo esc_html($superior_name); ?>
                                    </strong>
                                    <span style="font-size:11px;color:#5856D6;font-family:var(--font);background:rgba(88,86,214,0.08);padding:2px 10px;border-radius:50px;display:inline-block;margin-top:3px;">
                                        <?php echo esc_html($superior_position); ?>
                                    </span>
                                </div>
                            </div>
                        <?php elseif ($org_position_name): ?>
                            <div style="display:flex;align-items:center;gap:8px;padding:12px 14px;background:rgba(52,199,89,0.05);border:1.5px solid rgba(52,199,89,0.12);border-radius:var(--glass-radius-xs);">
                                <span class="dashicons dashicons-star-filled" style="color:#34C759;width:18px;height:18px;font-size:18px;"></span>
                                <span style="font-size:13px;font-family:var(--font);color:var(--text-secondary);">بالاترین سطح سازمانی</span>
                            </div>
                        <?php else: ?>
                            <div class="wpoa-input-icon-wrap">
                                <span class="dashicons dashicons-groups"></span>
                                <input type="text" value="—" readonly class="wpoa-readonly-glass">
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <button class="wpoa-btn wpoa-btn-glow wpoa-btn-save-profile" id="wpoa-ue-save">
                <span class="dashicons dashicons-saved"></span>
                <?php echo $is_edit ? 'ذخیره تغییرات' : 'ایجاد کاربر'; ?>
            </button>
        </div>

        <!-- ── LEFT COLUMN ── -->
        <div class="wpoa-profile-col">

            <!-- Avatar -->
            <div class="wpoa-profile-section-glass">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--green-light);color:var(--green);">
                        <span class="dashicons dashicons-format-image"></span>
                    </span>
                    <h3>تصویر پروفایل</h3>
                </div>
                <div class="wpoa-avatar-upload-area">
                    <div class="wpoa-avatar-edit-wrap" id="wpoa-ue-avatar-preview">
                        <?php if ($avatar_url): ?>
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="">
                        <?php else: ?>
                            <span class="dashicons dashicons-camera"></span>
                        <?php endif; ?>
                    </div>
                    <div class="wpoa-avatar-upload-info">
                        <p>تصویر کاربر را انتخاب کنید</p>
                        <small>فرمت‌های JPG, PNG — حداکثر 2MB</small>
                        <input type="file" id="wpoa-ue-avatar-file" accept="image/*" style="display:none;">
                        <button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" id="wpoa-ue-avatar-btn">
                            <span class="dashicons dashicons-upload"></span> بارگذاری تصویر
                        </button>
                    </div>
                </div>
            </div>

            <!-- Signature -->
            <div class="wpoa-profile-section-glass">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--orange-light);color:var(--orange);">
                        <span class="dashicons dashicons-edit-page"></span>
                    </span>
                    <h3>امضا</h3>
                </div>
                <div class="wpoa-profile-fields">
                    <div class="wpoa-pf-field">
                        <label for="wpoa-ue-sig">امضای متنی</label>
                        <textarea id="wpoa-ue-sig" rows="3" placeholder="امضای متنی در انتهای نامه‌ها درج می‌شود..."><?php echo esc_textarea($sig_text); ?></textarea>
                    </div>
                    <div class="wpoa-pf-field">
                        <label>تصویر امضا</label>
                        <div class="wpoa-sig-upload-area">
                            <div id="wpoa-ue-sig-preview" class="wpoa-sig-preview-glass">
                                <?php if ($sig_img_url): ?>
                                    <img src="<?php echo esc_url($sig_img_url); ?>" alt="">
                                <?php else: ?>
                                    <span class="dashicons dashicons-edit-page" style="font-size:28px;color:var(--text-tertiary);"></span>
                                <?php endif; ?>
                            </div>
                            <input type="file" id="wpoa-ue-sig-file" accept="image/*" style="display:none;">
                            <button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" id="wpoa-ue-sig-btn">
                                <span class="dashicons dashicons-upload"></span> بارگذاری امضا
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div class="wpoa-profile-section-glass wpoa-password-section">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--red-light);color:var(--red);">
                        <span class="dashicons dashicons-lock"></span>
                    </span>
                    <h3><?php echo $is_edit ? 'تغییر رمز عبور' : 'رمز عبور'; ?></h3>
                </div>
                <div class="wpoa-profile-fields">
                    <div class="wpoa-pw-row">
                        <div class="wpoa-pf-field">
                            <label for="wpoa-ue-pw"><?php echo $is_edit ? 'رمز عبور جدید' : 'رمز عبور *'; ?></label>
                            <div class="wpoa-input-icon-wrap">
                                <span class="dashicons dashicons-hidden"></span>
                                <input type="password" id="wpoa-ue-pw" placeholder="<?php echo $is_edit ? 'خالی = بدون تغییر' : 'حداقل ۶ کاراکتر'; ?>">
                            </div>
                        </div>
                        <div class="wpoa-pf-field">
                            <label for="wpoa-ue-pw2">تکرار رمز عبور</label>
                            <div class="wpoa-input-icon-wrap">
                                <span class="dashicons dashicons-hidden"></span>
                                <input type="password" id="wpoa-ue-pw2" placeholder="تکرار رمز عبور">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ═══════════ INLINE JS ═══════════ -->
<script>
(function($) {
    var IS_EDIT  = <?php echo $is_edit ? 'true' : 'false'; ?>;
    var USER_ID  = <?php echo $edit_user_id ?: 0; ?>;
    var AJAX_URL = '<?php echo admin_url('admin-ajax.php'); ?>';

    function post(action, data, ok, fail) {
        data.action = action;
        $.ajax({
            url: AJAX_URL, type: 'POST', data: data,
            dataType: 'json', timeout: 15000,
            success: function(r) {
                if (r && r.success) { if (ok) ok(r.data || {}); }
                else { var m = (r && r.data && r.data.message) || 'خطا'; if (fail) fail(m); else alert(m); }
            },
            error: function() { var m = 'خطا در ارتباط با سرور'; if (fail) fail(m); else alert(m); }
        });
    }

    function notice(msg, type) {
        if (typeof showNotice === 'function') showNotice(msg, type);
        else alert(msg);
    }

    // ── Save ──
    $('#wpoa-ue-save').on('click', function() {
        var $btn  = $(this);
        var name  = $.trim($('#wpoa-ue-name').val());
        var email = $.trim($('#wpoa-ue-email').val());
        var pw    = $('#wpoa-ue-pw').val() || '';
        var pw2   = $('#wpoa-ue-pw2').val() || '';

        if (!name || !email) { notice('نام و ایمیل الزامی است.', 'error'); return; }
        if (!IS_EDIT && !pw) { notice('رمز عبور برای کاربر جدید الزامی است.', 'error'); return; }
        if (pw && pw !== pw2) { notice('رمز عبور و تکرار آن مطابقت ندارد.', 'error'); return; }
        if (pw && pw.length < 6) { notice('رمز عبور باید حداقل ۶ کاراکتر باشد.', 'error'); return; }

        $btn.prop('disabled', true).css('opacity', 0.6);

        var data = {
            display_name: name,
            email:        email,
            phone:        $.trim($('#wpoa-ue-phone').val()),
            role:         $('#wpoa-ue-role').val(),
            unit_id:      $('#wpoa-ue-unit').val(),
            password:     pw,
            sig_text:     $('#wpoa-ue-sig').val() || '',
        };

        if (IS_EDIT) {
            data.user_id = USER_ID;
            post('wpoa_admin_update_user', data,
                function(r) {
                    notice(r.message || 'ذخیره شد.', 'success');
                    $btn.prop('disabled', false).css('opacity', 1);
                },
                function(m) { notice(m, 'error'); $btn.prop('disabled', false).css('opacity', 1); }
            );
        } else {
            post('wpoa_admin_create_user', data,
                function(r) {
                    notice(r.message || 'کاربر ایجاد شد.', 'success');
                    if (r.user_id) {
                        window.location.href = '<?php echo admin_url('admin.php?page=wpoa-user-edit&user_id='); ?>' + r.user_id;
                    }
                },
                function(m) { notice(m, 'error'); $btn.prop('disabled', false).css('opacity', 1); }
            );
        }
    });

    // ── Avatar Upload ──
    $('#wpoa-ue-avatar-btn').on('click', function() { $('#wpoa-ue-avatar-file').trigger('click'); });
    $('#wpoa-ue-avatar-file').on('change', function() {
        var file = this.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) { notice('حجم تصویر بیش از 2MB است.', 'error'); return; }

        var fd = new FormData();
        fd.append('action', 'wpoa_admin_upload_avatar');
        fd.append('user_id', IS_EDIT ? USER_ID : 0);
        fd.append('avatar', file);

        $.ajax({
            url: AJAX_URL, type: 'POST', data: fd,
            processData: false, contentType: false, dataType: 'json',
            success: function(r) {
                if (r && r.success && r.data.url) {
                    $('#wpoa-ue-avatar-preview').html('<img src="' + r.data.url + '" alt="">');
                    $('#wpoa-ue-avatar-wrap').html('<img src="' + r.data.url + '" alt="">');
                    notice('تصویر بارگذاری شد.', 'success');
                } else {
                    notice((r && r.data && r.data.message) || 'خطا', 'error');
                }
            },
            error: function() { notice('خطا در بارگذاری', 'error'); }
        });
    });

    // ── Signature Image Upload ──
    $('#wpoa-ue-sig-btn').on('click', function() { $('#wpoa-ue-sig-file').trigger('click'); });
    $('#wpoa-ue-sig-file').on('change', function() {
        var file = this.files[0];
        if (!file) return;

        var fd = new FormData();
        fd.append('action', 'wpoa_admin_upload_signature');
        fd.append('user_id', IS_EDIT ? USER_ID : 0);
        fd.append('signature', file);

        $.ajax({
            url: AJAX_URL, type: 'POST', data: fd,
            processData: false, contentType: false, dataType: 'json',
            success: function(r) {
                if (r && r.success && r.data.url) {
                    $('#wpoa-ue-sig-preview').html('<img src="' + r.data.url + '" alt="">');
                    notice('تصویر امضا بارگذاری شد.', 'success');
                } else {
                    notice((r && r.data && r.data.message) || 'خطا', 'error');
                }
            },
            error: function() { notice('خطا در بارگذاری', 'error'); }
        });
    });

})(jQuery);
</script>

<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-footer.php'; ?>