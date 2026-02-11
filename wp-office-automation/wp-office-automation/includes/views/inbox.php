<?php
defined('ABSPATH') || exit;
$current_page_slug = $_GET['page'] ?? 'wpoa-inbox';
$initial_folder    = 'inbox';
if ($current_page_slug === 'wpoa-sent')           $initial_folder = 'sent';
if ($current_page_slug === 'wpoa-referrals')       $initial_folder = 'referrals';
if ($current_page_slug === 'wpoa-referrals-sent')  $initial_folder = 'referrals-sent';

$titles = [
    'inbox'          => 'صندوق دریافت',
    'sent'           => 'ارسال‌شده',
    'referrals'      => 'ارجاعات من',
    'referrals-sent' => 'ارجاعات ارسالی',
];
$icons = [
    'inbox'          => 'dashicons-email-alt',
    'sent'           => 'dashicons-migrate',
    'referrals'      => 'dashicons-randomize',
    'referrals-sent' => 'dashicons-external',
];
$colors = [
    'inbox'          => 'wpoa-icon-blue',
    'sent'           => 'wpoa-icon-green',
    'referrals'      => 'wpoa-icon-orange',
    'referrals-sent' => 'wpoa-icon-purple',
];

$unread = 0;
if (class_exists('WPOA_Message_User_Model')) {
    try { $unread = (int) (new WPOA_Message_User_Model())->count_unread(get_current_user_id()); } catch (Throwable $e) {}
}
?>
<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-header.php'; ?>

<div class="wpoa-page-hero">
    <div class="wpoa-hero-title-area">
        <div class="wpoa-hero-icon <?php echo esc_attr($colors[$initial_folder] ?? 'wpoa-icon-blue'); ?>">
            <span class="dashicons <?php echo esc_attr($icons[$initial_folder] ?? 'dashicons-email-alt'); ?>"></span>
        </div>
        <div>
            <h1><?php echo esc_html($titles[$initial_folder] ?? 'صندوق دریافت'); ?></h1>
            <p>مدیریت نامه‌ها و مکاتبات سازمانی</p>
        </div>
    </div>
    <div class="wpoa-hero-actions">
        <?php if ($unread > 0): ?>
        <div class="wpoa-mini-stat">
            <span class="wpoa-mini-stat-num" style="color:var(--red);"><?php echo $unread; ?></span>
            <span class="wpoa-mini-stat-label">خوانده‌نشده</span>
        </div>
        <?php endif; ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpoa-compose')); ?>" class="wpoa-btn wpoa-btn-glow">
            <span class="dashicons dashicons-plus-alt2"></span> نامه جدید
        </a>
    </div>
</div>

<div class="wpoa-section-glass" id="wpoa-inbox-page" data-initial-folder="<?php echo esc_attr($initial_folder); ?>">

    <?php if ($initial_folder === 'inbox' || $initial_folder === 'sent'): ?>
    <div class="wpoa-folder-tabs">
        <button class="wpoa-folder-tab <?php echo $initial_folder === 'inbox' ? 'active' : ''; ?>" data-folder="inbox">
            <span class="dashicons dashicons-email"></span> دریافتی
        </button>
        <button class="wpoa-folder-tab <?php echo $initial_folder === 'sent' ? 'active' : ''; ?>" data-folder="sent">
            <span class="dashicons dashicons-migrate"></span> ارسال‌شده
        </button>
        <button class="wpoa-folder-tab" data-folder="drafts">
            <span class="dashicons dashicons-edit"></span> پیش‌نویس
        </button>
        <button class="wpoa-folder-tab" data-folder="starred">
            <span class="dashicons dashicons-star-filled"></span> ستاره‌دار
        </button>
        <button class="wpoa-folder-tab" data-folder="archive">
            <span class="dashicons dashicons-archive"></span> بایگانی
        </button>
        <button class="wpoa-folder-tab" data-folder="trash">
            <span class="dashicons dashicons-trash"></span> زباله
        </button>
    </div>
    <?php endif; ?>

    <div class="wpoa-toolbar-glass">
        <div class="wpoa-toolbar-right">
            <label class="wpoa-check-wrap"><input type="checkbox" id="wpoa-select-all"><span class="wpoa-check-custom"></span></label>
            <select id="wpoa-batch-select" class="wpoa-select-glass">
                <option value="">عملیات دسته‌ای...</option>
                <option value="read">خوانده‌شده</option><option value="unread">خوانده‌نشده</option>
                <option value="archive">بایگانی</option><option value="trash">حذف</option><option value="restore">بازیابی</option>
            </select>
            <button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" id="wpoa-batch-apply">اعمال</button>
        </div>
        <div class="wpoa-toolbar-left">
            <div class="wpoa-search-glass"><span class="dashicons dashicons-search"></span><input type="text" id="wpoa-search-input" placeholder="جستجو..."></div>
        </div>
    </div>

    <div id="wpoa-message-list">
        <div class="wpoa-loading-glass"><div class="wpoa-spinner-glass"></div><span>در حال بارگذاری...</span></div>
    </div>
    <div class="wpoa-pagination-glass" id="wpoa-pagination"></div>
    <div id="wpoa-message-detail" style="display:none;"></div>
</div>

<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-footer.php'; ?>