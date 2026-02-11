<?php
defined('ABSPATH') || exit;
global $wpdb;

$t_units = $wpdb->prefix . 'wpoa_org_units';
$t_roles = $wpdb->prefix . 'wpoa_org_roles';
$t_uo    = $wpdb->prefix . 'wpoa_user_org';
$t_prof  = $wpdb->prefix . 'wpoa_user_profiles';

$org_tree       = [];
$org_roles      = [];
$org_users      = [];
$wp_users       = [];
$flat_positions = [];

try {
    // ── All WP users (for picker dropdowns) ──
        // ── All WP users with avatars ──
    $all_wp = get_users(['fields' => ['ID', 'display_name', 'user_email'], 'number' => 500]);

    $avatar_map = [];
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t_prof}'")) {
        $avs = $wpdb->get_results("SELECT user_id, avatar_url FROM {$t_prof}");
        foreach ($avs as $a) {
            $avatar_map[(int) $a->user_id] = $a->avatar_url ?? '';
        }
    }

    foreach ($all_wp as $u) {
        $wp_users[] = [
            'id'     => (int) $u->ID,
            'name'   => $u->display_name,
            'email'  => $u->user_email,
            'avatar' => $avatar_map[(int) $u->ID] ?? '',
        ];
    }

    // ── Roles (kept for data, no UI tab) ──
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t_roles}'")) {
        $rr = $wpdb->get_results("SELECT id, name FROM {$t_roles} ORDER BY id ASC");
        foreach ($rr as $r) {
            $org_roles[] = ['id' => (int) $r->id, 'name' => $r->name];
        }
    }

    // ── Org tree ──
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t_units}'")) {
        $units_raw = $wpdb->get_results("SELECT * FROM {$t_units} ORDER BY id ASC");

        // Flat positions for dropdowns
        foreach ($units_raw as $u) {
            $flat_positions[] = ['id' => (int) $u->id, 'name' => $u->name];
        }

        // User assignments per unit
        $unit_users_map = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t_uo}'")) {
            $rows = $wpdb->get_results(
                "SELECT uo.org_unit_id, uo.user_id, uo.org_role_id,
                        u.display_name, u.user_email,
                        r.name AS role_name,
                        p.avatar_url
                 FROM {$t_uo} uo
                 LEFT JOIN {$wpdb->users} u ON u.ID = uo.user_id
                 LEFT JOIN {$t_roles} r ON r.id = uo.org_role_id
                 LEFT JOIN {$t_prof} p ON p.user_id = uo.user_id"
            );
            foreach ($rows as $r) {
                $uid = (int) $r->org_unit_id;
                $unit_users_map[$uid][] = [
                    'user_id'      => (int) $r->user_id,
                    'display_name' => $r->display_name ?? '',
                    'email'        => $r->user_email ?? '',
                    'role_name'    => $r->role_name ?? '',
                    'avatar_url'   => $r->avatar_url ?? '',
                ];
            }
        }

        // Build tree
        function wpoa_tree_build($units, $map, $pid = null) {
            $out = [];
            foreach ($units as $u) {
                $p = $u->parent_id ? (int) $u->parent_id : null;
                if ($p !== $pid) continue;
                $out[] = [
                    'id'       => (int) $u->id,
                    'name'     => $u->name,
                    'desc'     => $u->description ?? '',
                    'users'    => $map[(int) $u->id] ?? [],
                    'children' => wpoa_tree_build($units, $map, (int) $u->id),
                ];
            }
            return $out;
        }
        $org_tree = wpoa_tree_build($units_raw, $unit_users_map);
    }

    // ── Assigned users list (with avatars) ──
    if ($wpdb->get_var("SHOW TABLES LIKE '{$t_uo}'")) {
        $uu = $wpdb->get_results(
            "SELECT uo.id AS assign_id, uo.user_id, uo.org_unit_id,
                    u.display_name, u.user_email,
                    un.name AS unit_name,
                    p.avatar_url
             FROM {$t_uo} uo
             LEFT JOIN {$wpdb->users} u ON u.ID = uo.user_id
             LEFT JOIN {$t_units} un ON un.id = uo.org_unit_id
             LEFT JOIN {$t_prof} p ON p.user_id = uo.user_id
             ORDER BY uo.id DESC"
        );
        foreach ($uu as $u) {
            $org_users[] = [
                'assign_id' => (int) $u->assign_id,
                'user_id'   => (int) $u->user_id,
                'unit_id'   => (int) $u->org_unit_id,
                'name'      => $u->display_name ?? '',
                'email'     => $u->user_email ?? '',
                'unit'      => $u->unit_name ?? '',
                'avatar'    => $u->avatar_url ?? '',
            ];
        }
    }

} catch (Throwable $e) {}
?>
<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-header.php'; ?>

<script>
var WPOA_TREE      = <?php echo json_encode($org_tree, JSON_UNESCAPED_UNICODE); ?>;
var WPOA_ROLES     = <?php echo json_encode($org_roles, JSON_UNESCAPED_UNICODE); ?>;
var WPOA_WP_USERS  = <?php echo json_encode($wp_users, JSON_UNESCAPED_UNICODE); ?>;
var WPOA_ASSIGNED  = <?php echo json_encode($org_users, JSON_UNESCAPED_UNICODE); ?>;
var WPOA_POSITIONS = <?php echo json_encode($flat_positions, JSON_UNESCAPED_UNICODE); ?>;
</script>

<div class="wpoa-page-hero">
    <div class="wpoa-hero-title-area">
        <div class="wpoa-hero-icon wpoa-icon-teal">
            <span class="dashicons dashicons-networking"></span>
        </div>
        <div>
            <h1>مدیریت ساختار سازمانی</h1>
            <p>واحدها و کاربران سازمان</p>
        </div>
    </div>
</div>

<div class="wpoa-section-glass" id="wpoa-org-page">
    <div class="wpoa-tabs-glass">
        <button class="wpoa-tab-glass active" data-tab="org-chart">
            <span class="dashicons dashicons-networking"></span> چارت سازمانی
        </button>
        <button class="wpoa-tab-glass" data-tab="org-users">
            <span class="dashicons dashicons-admin-users"></span> کاربران
        </button>
    </div>

    <!-- TAB: Chart -->
    <div class="wpoa-org-tab" id="wpoa-tab-org-chart">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-wrap:wrap;gap:8px;">
            <button class="wpoa-btn wpoa-btn-glow" id="wpoa-btn-new-pos">
                <span class="dashicons dashicons-plus-alt2"></span> موقعیت جدید
            </button>
            <div class="wpoa-chart-view-toggle">
                <button class="wpoa-view-btn active" data-v="tree"><span class="dashicons dashicons-networking"></span> درختی</button>
                <button class="wpoa-view-btn" data-v="list"><span class="dashicons dashicons-list-view"></span> لیستی</button>
            </div>
        </div>
        <div id="wpoa-chart-box"></div>
    </div>

    <!-- TAB: Users -->
    <div class="wpoa-org-tab" id="wpoa-tab-org-users" style="display:none;">
        <div id="wpoa-users-box"></div>
    </div>
</div>

<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-footer.php'; ?>