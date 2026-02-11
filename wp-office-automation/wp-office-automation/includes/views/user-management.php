<?php
defined('ABSPATH') || exit;

if (!current_user_can('list_users')) {
    wp_die('شما دسترسی ندارید.');
}

global $wpdb;

$t_prof  = $wpdb->prefix . 'wpoa_user_profiles';
$t_uo    = $wpdb->prefix . 'wpoa_user_org';
$t_units = $wpdb->prefix . 'wpoa_org_units';

// Fetch users (basic fields)
$wp_users_query = get_users([
    'number'  => 500,
    'orderby' => 'registered',
    'order'   => 'DESC',
    'fields'  => ['ID', 'display_name', 'user_email', 'user_registered'],
]);

// Profiles: avatar + phone
$avatar_map = [];
$phone_map  = [];
if ($wpdb->get_var("SHOW TABLES LIKE '{$t_prof}'")) {
    $profs = $wpdb->get_results("SELECT user_id, avatar_url, phone FROM {$t_prof}");
    foreach ($profs as $p) {
        $uid = (int) $p->user_id;
        $avatar_map[$uid] = $p->avatar_url ?? '';
        $phone_map[$uid]  = $p->phone ?? '';
    }
}

// Org positions
$org_map = [];
if ($wpdb->get_var("SHOW TABLES LIKE '{$t_uo}'")) {
    $orgs = $wpdb->get_results(
        "SELECT uo.user_id, uo.org_unit_id, un.name AS unit_name
         FROM {$t_uo} uo
         LEFT JOIN {$t_units} un ON un.id = uo.org_unit_id"
    );
    foreach ($orgs as $o) {
        $uid = (int) $o->user_id;
        $org_map[$uid] = [
            'unit_id'   => (int) $o->org_unit_id,
            'unit_name' => $o->unit_name ?? '',
        ];
    }
}

// Build data with roles
$all_users   = [];
$total_count = count($wp_users_query);
$admin_count = 0;
$with_pos    = 0;

/** @var stdClass $u */
foreach ($wp_users_query as $u) {
    $uid = (int) $u->ID;

    // Get full user to read roles
    $u_full = get_userdata($uid);
    $roles  = ($u_full && !empty($u_full->roles)) ? $u_full->roles : [];
    $role   = !empty($roles) ? $roles[0] : '';

    if ($role === 'administrator') {
        $admin_count++;
    }
    if (isset($org_map[$uid])) {
        $with_pos++;
    }

    $all_users[] = [
        'id'         => $uid,
        'name'       => $u->display_name,
        'email'      => $u->user_email,
        'avatar'     => $avatar_map[$uid] ?? '',
        'phone'      => $phone_map[$uid] ?? '',
        'role'       => $role,
        'unit'       => $org_map[$uid]['unit_name'] ?? '',
        'registered' => $u->user_registered,
    ];
}

$without_pos = $total_count - $with_pos;

// WP roles for labels
$wp_roles_obj = wp_roles();
$wp_roles     = $wp_roles_obj ? $wp_roles_obj->roles : [];
?>
<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-header.php'; ?>

<!-- ═══════════ HERO ═══════════ -->
<div class="wpoa-profile-hero">
    <div class="wpoa-hero-content">
        <div class="wpoa-hero-avatar" style="background:linear-gradient(135deg,rgba(0,122,255,0.15),rgba(88,86,214,0.1));">
            <span class="dashicons dashicons-admin-users" style="font-size:36px;width:36px;height:36px;color:var(--accent);"></span>
        </div>

        <div class="wpoa-hero-info">
            <h2 class="wpoa-hero-name">مدیریت کاربران</h2>
            <p class="wpoa-hero-email">مشاهده و ویرایش کاربران سیستم اتوماسیون اداری</p>
        </div>

        <div class="wpoa-hero-stats">
            <div class="wpoa-stat-card wpoa-stat-unread">
                <span class="wpoa-stat-number"><?php echo $total_count; ?></span>
                <span class="wpoa-stat-label">کل کاربران</span>
            </div>
            <div class="wpoa-stat-card wpoa-stat-inbox">
                <span class="wpoa-stat-number"><?php echo $admin_count; ?></span>
                <span class="wpoa-stat-label">مدیران</span>
            </div>
            <div class="wpoa-stat-card wpoa-stat-sent">
                <span class="wpoa-stat-number"><?php echo $with_pos; ?></span>
                <span class="wpoa-stat-label">دارای موقعیت</span>
            </div>
            <div class="wpoa-stat-card wpoa-stat-draft">
                <span class="wpoa-stat-number"><?php echo $without_pos; ?></span>
                <span class="wpoa-stat-label">بدون موقعیت</span>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════ BODY ═══════════ -->
<div class="wpoa-profile-body">
    <div class="wpoa-profile-section-glass">
        <div class="wpoa-section-header-glass" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="wpoa-section-icon" style="background:var(--accent-light);color:var(--accent);">
                    <span class="dashicons dashicons-admin-users"></span>
                </span>
                <h3 style="margin:0;">لیست کاربران</h3>
            </div>
            <?php if (current_user_can('create_users')): ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=wpoa-user-edit')); ?>" class="wpoa-btn wpoa-btn-glow wpoa-btn-sm">
                    <span class="dashicons dashicons-plus-alt2"></span> کاربر جدید
                </a>
            <?php endif; ?>
        </div>

        <!-- Search + Filter -->
        <div style="display:flex;gap:10px;margin:16px 0;flex-wrap:wrap;align-items:center;">
            <div style="position:relative;flex:1;max-width:320px;">
                <span class="dashicons dashicons-search" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--text-tertiary);pointer-events:none;"></span>
                <input type="text" id="wpoa-um-search" placeholder="جستجوی نام یا ایمیل..." style="width:100%;padding:10px 14px 10px 14px;padding-right:38px;border:1.5px solid rgba(200,210,230,0.5);border-radius:8px;font-family:var(--font);font-size:13px;background:rgba(255,255,255,0.65);">
            </div>
            <select id="wpoa-um-role-filter" style="padding:10px 14px;border:1.5px solid rgba(200,210,230,0.5);border-radius:8px;font-family:var(--font);font-size:13px;min-width:140px;">
                <option value="">همه نقش‌ها</option>
                <?php foreach ($wp_roles as $key => $r): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($r['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <span id="wpoa-um-count" style="font-size:12px;color:var(--text-tertiary);font-family:var(--font);"><?php echo $total_count; ?> کاربر</span>
        </div>

        <!-- Users Table -->
        <?php if (empty($all_users)): ?>
            <div class="wpoa-empty-state">
                <p>کاربری یافت نشد.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="wpoa-table-glass" id="wpoa-um-table">
                    <thead>
                        <tr>
                            <th style="width:50px;"></th>
                            <th>نام</th>
                            <th>ایمیل</th>
                            <th>تلفن</th>
                            <th>نقش</th>
                            <th>موقعیت سازمانی</th>
                            <th>تاریخ عضویت</th>
                            <th style="width:80px;">ویرایش</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_users as $u): ?>
                            <?php
                            // Role label
                            $role_label = $u['role'];
                            if ($u['role'] && isset($wp_roles[$u['role']]['name'])) {
                                $role_label = $wp_roles[$u['role']]['name'];
                            }

                            // Membership date -> Jalali if possible
                            if (!empty($u['registered'])) {
                                $ts = strtotime($u['registered']);
                                if (function_exists('parsidate')) {
                                    $date = parsidate('Y/m/d', $ts);
                                } elseif (function_exists('jdate')) {
                                    $date = jdate('Y/m/d', $ts);
                                } else {
                                    $date = date('Y-m-d', $ts);
                                }
                            } else {
                                $date = '—';
                            }

                            // Role badge colours
                            $colors = [
                                'administrator' => ['bg' => 'rgba(255,59,48,0.08)', 'color' => '#FF3B30', 'border' => 'rgba(255,59,48,0.15)'],
                                'editor'        => ['bg' => 'rgba(88,86,214,0.08)', 'color' => '#5856D6', 'border' => 'rgba(88,86,214,0.15)'],
                                'author'        => ['bg' => 'rgba(0,122,255,0.08)', 'color' => '#007AFF', 'border' => 'rgba(0,122,255,0.15)'],
                                'contributor'   => ['bg' => 'rgba(255,149,0,0.08)', 'color' => '#FF9500', 'border' => 'rgba(255,149,0,0.15)'],
                                'subscriber'    => ['bg' => 'rgba(142,142,147,0.08)', 'color' => '#8E8E93', 'border' => 'rgba(142,142,147,0.15)'],
                            ];
                            $c = $colors[$u['role']] ?? $colors['subscriber'];
                            ?>
                            <tr class="wpoa-um-row"
                                data-name="<?php echo esc_attr(mb_strtolower($u['name'])); ?>"
                                data-email="<?php echo esc_attr(mb_strtolower($u['email'])); ?>"
                                data-role="<?php echo esc_attr($u['role']); ?>">
                                <td style="text-align:center;">
                                    <div class="wpoa-user-row-avatar" style="margin:0 auto;">
                                        <?php if (!empty($u['avatar'])): ?>
                                            <img src="<?php echo esc_url($u['avatar']); ?>" alt="">
                                        <?php else: ?>
                                            <span class="dashicons dashicons-admin-users"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong style="font-family:var(--font);font-size:13px;">
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=wpoa-user-edit&user_id=' . $u['id'])); ?>" style="text-decoration:none;color:inherit;">
                                            <?php echo esc_html($u['name']); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td style="color:var(--text-secondary);font-size:12px;text-align:right;">
                                    <?php echo esc_html($u['email']); ?>
                                </td>
                                <td dir="ltr" style="color:var(--text-secondary);font-size:12px;text-align:right;">
                                    <?php echo $u['phone'] ? esc_html($u['phone']) : '—'; ?>
                                </td>
                                <td>
                                    <span style="display:inline-block;font-size:11px;font-weight:500;font-family:var(--font);padding:3px 10px;border-radius:50px;background:<?php echo esc_attr($c['bg']); ?>;color:<?php echo esc_attr($c['color']); ?>;border:1px solid <?php echo esc_attr($c['border']); ?>;">
                                        <?php echo esc_html($role_label ?: 'بدون نقش'); ?>
                                    </span>
                                </td>
                                <td style="font-family:var(--font);font-size:12px;">
                                    <?php echo $u['unit'] ? esc_html($u['unit']) : '—'; ?>
                                </td>
                                <td dir="ltr" style="color:var(--text-tertiary);font-size:11px;text-align:center;">
                                    <?php echo esc_html($date); ?>
                                </td>
                                <td style="text-align:center;">
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=wpoa-user-edit&user_id=' . $u['id'])); ?>" class="wpoa-btn wpoa-btn-outline wpoa-btn-sm">
                                        <span class="dashicons dashicons-edit"></span> ویرایش
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Simple search + role filter (client-side only) -->
            <script>
                (function($){
                    function filterUM() {
                        var kw   = ($('#wpoa-um-search').val() || '').trim().toLowerCase();
                        var role = $('#wpoa-um-role-filter').val();
                        var shown = 0;

                        $('#wpoa-um-table .wpoa-um-row').each(function () {
                            var $r = $(this);
                            var name  = ($r.data('name')  || '');
                            var email = ($r.data('email') || '');
                            var rRole = ($r.data('role')  || '');

                            var matchText = !kw || name.indexOf(kw) !== -1 || email.indexOf(kw) !== -1;
                            var matchRole = !role || rRole === role;

                            var vis = matchText && matchRole;
                            $r.toggle(vis);
                            if (vis) shown++;
                        });

                        $('#wpoa-um-count').text(shown + ' کاربر');
                    }

                    $('#wpoa-um-search').on('input', filterUM);
                    $('#wpoa-um-role-filter').on('change', filterUM);
                })(jQuery);
            </script>
        <?php endif; ?>
    </div>
</div>

<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-footer.php'; ?>