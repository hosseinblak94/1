<?php
defined('ABSPATH') || exit;

$user_id = get_current_user_id();
$user    = get_userdata($user_id);

$display_name  = $user->display_name ?? '';
$email         = $user->user_email ?? '';
$phone         = '';
$sig_text      = '';
$avatar_url    = '';
$sig_img_url   = '';
$unread_count  = 0;
$inbox_count   = 0;
$sent_count    = 0;
$draft_count   = 0;
$ref_count     = 0;

// ── New org variables ──
$org_position_name = '';
$org_unit_id       = 0;
$superior_name     = '';
$superior_position = '';
$superior_avatar   = '';

// ── Profile ──
if (class_exists('WPOA_User_Profile_Model')) {
    try {
        $pm = new WPOA_User_Profile_Model();
        $p  = $pm->get_by_user_id($user_id);
        if ($p) {
            $phone       = $p->phone ?? '';
            $sig_text    = $p->signature_text ?? '';
            $avatar_url  = $p->avatar_url ?? '';
            $sig_img_url = $p->signature_image_url ?? '';
        }
    } catch (Throwable $e) {}
}

// ── Org (new model — direct DB) ──
try {
    global $wpdb;
    $t_uo    = $wpdb->prefix . 'wpoa_user_org';
    $t_units = $wpdb->prefix . 'wpoa_org_units';
    $t_prof  = $wpdb->prefix . 'wpoa_user_profiles';

    if ($wpdb->get_var("SHOW TABLES LIKE '{$t_uo}'")) {
        $my_unit = $wpdb->get_row($wpdb->prepare(
            "SELECT uo.org_unit_id, un.name AS unit_name, un.parent_id
             FROM {$t_uo} uo
             LEFT JOIN {$t_units} un ON un.id = uo.org_unit_id
             WHERE uo.user_id = %d LIMIT 1",
            $user_id
        ));

        if ($my_unit) {
            $org_position_name = $my_unit->unit_name ?? '';
            $org_unit_id       = (int) $my_unit->org_unit_id;

            if ($my_unit->parent_id) {
                $parent_unit = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, name FROM {$t_units} WHERE id = %d",
                    (int) $my_unit->parent_id
                ));

                if ($parent_unit) {
                    $superior_position = $parent_unit->name;

                    $sup_user = $wpdb->get_row($wpdb->prepare(
                        "SELECT uo.user_id, u.display_name, p.avatar_url
                         FROM {$t_uo} uo
                         LEFT JOIN {$wpdb->users} u ON u.ID = uo.user_id
                         LEFT JOIN {$t_prof} p ON p.user_id = uo.user_id
                         WHERE uo.org_unit_id = %d LIMIT 1",
                        (int) $parent_unit->id
                    ));

                    if ($sup_user) {
                        $superior_name   = $sup_user->display_name ?? '';
                        $superior_avatar = $sup_user->avatar_url ?? '';
                    }
                }
            }
        }
    }
} catch (Throwable $e) {}

// ── Stats ──
if (class_exists('WPOA_Message_User_Model')) {
    try {
        $mu = new WPOA_Message_User_Model();
    } catch (Throwable $e) {
        $mu = null;
    }

    if ($mu) {
        try {
            $unread_count = method_exists($mu, 'count_unread')
                ? (int) $mu->count_unread($user_id)
                : 0;
        } catch (Throwable $e) { $unread_count = 0; }

        if (method_exists($mu, 'count_folder')) {
            try { $inbox_count = (int) $mu->count_folder($user_id, 'inbox'); } catch (Throwable $e) { $inbox_count = 0; }
            try { $sent_count  = (int) $mu->count_folder($user_id, 'sent'); }  catch (Throwable $e) { $sent_count = 0; }
            try { $draft_count = (int) $mu->count_folder($user_id, 'drafts'); } catch (Throwable $e) { $draft_count = 0; }
        }
    }
}

if (class_exists('WPOA_Referral_Model')) {
    try {
        $rm = new WPOA_Referral_Model();
        $ref_count = method_exists($rm, 'count_pending')
            ? (int) $rm->count_pending($user_id)
            : 0;
    } catch (Throwable $e) { $ref_count = 0; }
}
?>
<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-header.php'; ?>



<!-- ═══════════ HERO ═══════════ -->
<div class="wpoa-profile-hero">
    <div class="wpoa-hero-content">
        <div class="wpoa-hero-avatar">
            <?php if ($avatar_url): ?>
                <img src="<?php echo esc_url($avatar_url); ?>" alt="">
            <?php else: ?>
                <span class="dashicons dashicons-admin-users"></span>
            <?php endif; ?>
        </div>

        <div class="wpoa-hero-info">
            <h2 class="wpoa-hero-name"><?php echo esc_html($display_name); ?></h2>
            <p class="wpoa-hero-email"><?php echo esc_html($email); ?></p>
            <?php if ($org_position_name): ?>
                <span class="wpoa-hero-role">
                    <span class="dashicons dashicons-businessman"></span>
                    <?php echo esc_html($org_position_name); ?>
                </span>
            <?php endif; ?>
        </div>

        <div class="wpoa-hero-stats">
            <div class="wpoa-stat-card wpoa-stat-unread">
                <span class="wpoa-stat-number"><?php echo $unread_count; ?></span>
                <span class="wpoa-stat-label">خوانده‌نشده</span>
            </div>
            <div class="wpoa-stat-card wpoa-stat-inbox">
                <span class="wpoa-stat-number"><?php echo $inbox_count; ?></span>
                <span class="wpoa-stat-label">دریافتی</span>
            </div>
            <div class="wpoa-stat-card wpoa-stat-sent">
                <span class="wpoa-stat-number"><?php echo $sent_count; ?></span>
                <span class="wpoa-stat-label">ارسال‌شده</span>
            </div>
            <div class="wpoa-stat-card wpoa-stat-draft">
                <span class="wpoa-stat-number"><?php echo $draft_count; ?></span>
                <span class="wpoa-stat-label">پیش‌نویس</span>
            </div>
            <div class="wpoa-stat-card wpoa-stat-ref">
                <span class="wpoa-stat-number"><?php echo $ref_count; ?></span>
                <span class="wpoa-stat-label">ارجاعات</span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ BODY ═══════════ -->
<div class="wpoa-profile-body" id="wpoa-profile-page">
    <div class="wpoa-profile-columns">

        <!-- ── RIGHT: Personal + Org ── -->
        <div class="wpoa-profile-col">
            <div class="wpoa-profile-section-glass">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--accent-light);color:var(--accent);">
                        <span class="dashicons dashicons-admin-users"></span>
                    </span>
                    <h3>اطلاعات شخصی</h3>
                </div>
                <div class="wpoa-profile-fields">
                    <div class="wpoa-pf-field">
                        <label for="wpoa-prof-name">نام نمایشی</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-nametag"></span>
                            <input type="text" id="wpoa-prof-name" value="<?php echo esc_attr($display_name); ?>" placeholder="نام و نام خانوادگی">
                        </div>
                    </div>
                    <div class="wpoa-pf-field">
                        <label for="wpoa-prof-email">ایمیل</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-email-alt2"></span>
                            <input type="text" id="wpoa-prof-email" value="<?php echo esc_attr($email); ?>" readonly class="wpoa-readonly-glass">
                        </div>
                    </div>
                    <div class="wpoa-pf-field">
                        <label for="wpoa-prof-phone">شماره تلفن</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-phone"></span>
                            <input type="text" id="wpoa-prof-phone" value="<?php echo esc_attr($phone); ?>" dir="ltr" placeholder="09XXXXXXXXX">
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════════ ORGANIZATIONAL INFO (NEW) ═══════════ -->
            <div class="wpoa-profile-section-glass">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--purple-light);color:var(--purple);">
                        <span class="dashicons dashicons-networking"></span>
                    </span>
                    <h3>اطلاعات سازمانی</h3>
                </div>
                <div class="wpoa-profile-fields">

                    <!-- Position -->
                    <div class="wpoa-pf-field">
                        <label>موقعیت سازمانی</label>
                        <?php if ($org_position_name): ?>
                            <div style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:rgba(90,200,250,0.06);border:1.5px solid rgba(90,200,250,0.15);border-radius:var(--glass-radius-xs);">
                                <span class="dashicons dashicons-businessman" style="color:#5AC8FA;width:20px;height:20px;font-size:20px;"></span>
                                <strong style="font-family:var(--font);font-size:13px;color:var(--text-primary);"><?php echo esc_html($org_position_name); ?></strong>
                            </div>
                        <?php else: ?>
                            <div class="wpoa-input-icon-wrap">
                                <span class="dashicons dashicons-businessman"></span>
                                <input type="text" value="تعیین نشده" readonly class="wpoa-readonly-glass" style="font-style:italic;color:var(--text-tertiary);">
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Superior -->
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
                                    <strong style="font-family:var(--font);font-size:13px;color:var(--text-primary);display:block;line-height:1.5;">
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

                </div>
            </div>
            <!-- ═══════════ END ORG INFO ═══════════ -->

            <button class="wpoa-btn wpoa-btn-glow wpoa-btn-save-profile" id="wpoa-profile-save">
                <span class="dashicons dashicons-saved"></span> ذخیره تغییرات
            </button>
        </div>

        <!-- ── LEFT: Avatar + Signature + Password ── -->
        <div class="wpoa-profile-col">
            <div class="wpoa-profile-section-glass">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--green-light);color:var(--green);">
                        <span class="dashicons dashicons-format-image"></span>
                    </span>
                    <h3>تصویر پروفایل</h3>
                </div>
                <div class="wpoa-avatar-upload-area">
                    <div class="wpoa-avatar-edit-wrap" id="wpoa-avatar-preview">
                        <?php if ($avatar_url): ?>
                            <img src="<?php echo esc_url($avatar_url); ?>" alt="">
                        <?php else: ?>
                            <span class="dashicons dashicons-camera"></span>
                        <?php endif; ?>
                    </div>
                    <div class="wpoa-avatar-upload-info">
                        <p>تصویر خود را انتخاب کنید</p>
                        <small>فرمت‌های JPG, PNG — حداکثر 2MB</small>
                        <input type="file" id="wpoa-avatar-file" accept="image/*" style="display:none;">
                        <button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" id="wpoa-avatar-upload-btn">
                            <span class="dashicons dashicons-upload"></span> بارگذاری تصویر
                        </button>
                    </div>
                </div>
            </div>

            <div class="wpoa-profile-section-glass">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--orange-light);color:var(--orange);">
                        <span class="dashicons dashicons-edit-page"></span>
                    </span>
                    <h3>امضا</h3>
                </div>
                <div class="wpoa-profile-fields">
                    <div class="wpoa-pf-field">
                        <label for="wpoa-prof-sig">امضای متنی</label>
                        <textarea id="wpoa-prof-sig" rows="3" placeholder="امضای متنی شما در انتهای نامه‌ها درج می‌شود..."><?php echo esc_textarea($sig_text); ?></textarea>
                    </div>
                    <div class="wpoa-pf-field">
                        <label>تصویر امضا</label>
                        <div class="wpoa-sig-upload-area">
                            <div id="wpoa-sig-img-preview" class="wpoa-sig-preview-glass">
                                <?php if ($sig_img_url): ?>
                                    <img src="<?php echo esc_url($sig_img_url); ?>" alt="">
                                <?php else: ?>
                                    <span class="dashicons dashicons-edit-page" style="font-size:28px;color:var(--text-tertiary);"></span>
                                <?php endif; ?>
                            </div>
                            <input type="file" id="wpoa-sig-img-file" accept="image/*" style="display:none;">
                            <button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" id="wpoa-sig-img-btn">
                                <span class="dashicons dashicons-upload"></span> بارگذاری امضا
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wpoa-profile-section-glass wpoa-password-section">
                <div class="wpoa-section-header-glass">
                    <span class="wpoa-section-icon" style="background:var(--red-light);color:var(--red);">
                        <span class="dashicons dashicons-lock"></span>
                    </span>
                    <h3>تغییر رمز عبور</h3>
                </div>
                <div class="wpoa-profile-fields">
                    <div class="wpoa-pf-field">
                        <label for="wpoa-pw-current">رمز عبور فعلی</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-lock"></span>
                            <input type="password" id="wpoa-pw-current" placeholder="رمز فعلی را وارد کنید">
                        </div>
                    </div>
                    <div class="wpoa-pw-row">
                        <div class="wpoa-pf-field">
                            <label for="wpoa-pw-new">رمز جدید</label>
                            <div class="wpoa-input-icon-wrap">
                                <span class="dashicons dashicons-hidden"></span>
                                <input type="password" id="wpoa-pw-new" placeholder="حداقل ۶ کاراکتر">
                            </div>
                        </div>
                        <div class="wpoa-pf-field">
                            <label for="wpoa-pw-confirm">تکرار رمز جدید</label>
                            <div class="wpoa-input-icon-wrap">
                                <span class="dashicons dashicons-hidden"></span>
                                <input type="password" id="wpoa-pw-confirm" placeholder="تکرار رمز جدید">
                            </div>
                        </div>
                    </div>
                    <button class="wpoa-btn wpoa-btn-danger wpoa-btn-pw" id="wpoa-pw-change">
                        <span class="dashicons dashicons-update"></span> تغییر رمز عبور
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-footer.php'; ?>