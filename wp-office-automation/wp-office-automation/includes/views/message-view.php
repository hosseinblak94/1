<?php
defined('ABSPATH') || exit;
// This is a fallback shell. The actual message detail is rendered via AJAX in admin.js.
?>
<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-header.php'; ?>
<?php include WPOA_PLUGIN_DIR . 'includes/views/sidebar.php'; ?>

<main class="wpoa-main-content">
    <div id="wpoa-message-detail">
        <div class="wpoa-loading"><div class="wpoa-spinner"></div><span>در حال بارگذاری نامه...</span></div>
    </div>
</main>

<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-footer.php'; ?>