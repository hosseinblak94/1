<?php
defined('ABSPATH') || exit;
if (!current_user_can('manage_options')) { wp_die('شما دسترسی ندارید.'); }
?>
<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-header.php'; ?>

<div class="wpoa-page-hero">
    <div class="wpoa-hero-title-area">
        <div class="wpoa-hero-icon wpoa-icon-red">
            <span class="dashicons dashicons-chart-area"></span>
        </div>
        <div>
            <h1>گزارش فعالیت‌ها</h1>
            <p>مشاهده و بررسی تمامی عملیات‌های انجام‌شده در سیستم</p>
        </div>
    </div>
</div>

<div id="wpoa-activity-page">
    <div class="wpoa-filters-bar">
        <div class="wpoa-filter-field">
            <label for="wpoa-act-action">نوع عملیات</label>
            <select id="wpoa-act-action"><option value="">همه</option></select>
        </div>
        <div class="wpoa-filter-field">
            <label for="wpoa-act-date-from">از تاریخ</label>
            <input type="date" id="wpoa-act-date-from" dir="ltr">
        </div>
        <div class="wpoa-filter-field">
            <label for="wpoa-act-date-to">تا تاریخ</label>
            <input type="date" id="wpoa-act-date-to" dir="ltr">
        </div>
        <button class="wpoa-btn wpoa-btn-glow" id="wpoa-act-filter-btn" style="align-self:flex-end;">
            <span class="dashicons dashicons-filter"></span> اعمال فیلتر
        </button>
    </div>

    <div class="wpoa-section-glass">
        <div class="wpoa-section-head">
            <span class="wpoa-section-icon wpoa-icon-red"><span class="dashicons dashicons-list-view"></span></span>
            <h3>لیست فعالیت‌ها</h3>
        </div>
        <div id="wpoa-activity-list">
            <div class="wpoa-loading-glass"><div class="wpoa-spinner-glass"></div></div>
        </div>
        <div class="wpoa-pagination-glass" id="wpoa-act-pagination"></div>
    </div>
</div>

<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-footer.php'; ?>