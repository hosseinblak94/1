<?php defined('ABSPATH') || exit; ?>
<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-header.php'; ?>

<div class="wpoa-page-hero">
    <div class="wpoa-hero-title-area">
        <div class="wpoa-hero-icon wpoa-icon-green">
            <span class="dashicons dashicons-edit"></span>
        </div>
        <div>
            <h1>نامه جدید</h1>
            <p>ارسال نامه به کاربران سازمان</p>
        </div>
    </div>
</div>

<div id="wpoa-compose-page">
    <input type="hidden" id="wpoa-compose-msg-id" value="0">
    <input type="hidden" id="wpoa-compose-reply-to" value="0">
    <input type="hidden" id="wpoa-compose-forward-from" value="0">

    <div class="wpoa-grid-2">
        <!-- RIGHT: Main content -->
        <div>
            <div class="wpoa-section-glass">
                <div class="wpoa-section-head">
                    <span class="wpoa-section-icon wpoa-icon-blue"><span class="dashicons dashicons-text-page"></span></span>
                    <h3>محتوای نامه</h3>
                </div>

                <div class="wpoa-pf-field" style="margin-bottom:16px;">
                    <label for="wpoa-compose-title">عنوان نامه</label>
                    <div class="wpoa-input-icon-wrap">
                        <span class="dashicons dashicons-editor-textcolor"></span>
                        <input type="text" id="wpoa-compose-title" placeholder="عنوان نامه را وارد کنید...">
                    </div>
                </div>

                <div class="wpoa-pf-field" style="margin-bottom:16px;">
                    <label>گیرندگان</label>
                    <div class="wpoa-recipient-box-glass" id="wpoa-to-box">
                        <div class="wpoa-recipient-tags" id="wpoa-to-tags"></div>
                        <input type="text" class="wpoa-recipient-input" id="wpoa-to-input" placeholder="نام گیرنده..." autocomplete="off">
                        <div class="wpoa-autocomplete-glass" id="wpoa-to-dropdown" style="display:none;"></div>
                    </div>
                    <input type="hidden" id="wpoa-to-ids" value="[]">
                </div>

                <div class="wpoa-pf-field" style="margin-bottom:16px;">
                    <label>رونوشت</label>
                    <div class="wpoa-recipient-box-glass" id="wpoa-cc-box">
                        <div class="wpoa-recipient-tags" id="wpoa-cc-tags"></div>
                        <input type="text" class="wpoa-recipient-input" id="wpoa-cc-input" placeholder="رونوشت..." autocomplete="off">
                        <div class="wpoa-autocomplete-glass" id="wpoa-cc-dropdown" style="display:none;"></div>
                    </div>
                    <input type="hidden" id="wpoa-cc-ids" value="[]">
                </div>

                <div class="wpoa-pf-field">
                    <label>متن نامه</label>
                    <?php
                    wp_editor('', 'wpoa-compose-body', [
                        'textarea_name' => 'wpoa_compose_body',
                        'textarea_rows' => 15,
                        'media_buttons' => false,
                        'teeny'         => false,
                        'quicktags'     => true,
                        'tinymce'       => ['directionality' => 'rtl', 'language' => 'fa'],
                    ]);
                    ?>
                </div>
            </div>

            <div class="wpoa-section-glass">
                <div class="wpoa-section-head">
                    <span class="wpoa-section-icon wpoa-icon-purple"><span class="dashicons dashicons-paperclip"></span></span>
                    <h3>پیوست‌ها</h3>
                </div>
                <div id="wpoa-attachment-list" class="wpoa-att-list-glass"></div>
                <input type="file" id="wpoa-attachment-input" multiple style="display:none;">
                <button class="wpoa-btn wpoa-btn-outline" id="wpoa-attach-btn">
                    <span class="dashicons dashicons-upload"></span> افزودن پیوست
                </button>
            </div>
        </div>

        <!-- LEFT: Meta -->
        <div>
            <div class="wpoa-section-glass">
                <div class="wpoa-section-head">
                    <span class="wpoa-section-icon wpoa-icon-orange"><span class="dashicons dashicons-admin-generic"></span></span>
                    <h3>تنظیمات نامه</h3>
                </div>
                <div class="wpoa-profile-fields">
                    <div class="wpoa-pf-field">
                        <label for="wpoa-compose-priority">اولویت</label>
                        <select id="wpoa-compose-priority" class="wpoa-select-glass" style="width:100%;max-width:100%;">
                            <option value="low">کم</option><option value="normal" selected>عادی</option>
                            <option value="important">مهم</option><option value="instant">فوری</option>
                        </select>
                    </div>
                    <div class="wpoa-pf-field">
                        <label for="wpoa-compose-internal-doc">شماره نامه داخلی</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-media-text"></span>
                            <input type="text" id="wpoa-compose-internal-doc" placeholder="اختیاری">
                        </div>
                    </div>
                    <div class="wpoa-pf-field">
                        <label for="wpoa-compose-sig">امضا</label>
                        <select id="wpoa-compose-sig" class="wpoa-select-glass" style="width:100%;max-width:100%;">
                            <option value="none">بدون امضا</option><option value="text">متنی</option>
                            <option value="image">تصویر</option><option value="both">متنی + تصویر</option>
                        </select>
                    </div>
                    <div class="wpoa-pf-field">
                        <label for="wpoa-compose-tags">برچسب‌ها</label>
                        <div class="wpoa-input-icon-wrap">
                            <span class="dashicons dashicons-tag"></span>
                            <input type="text" id="wpoa-compose-tags" placeholder="با کاما جدا کنید...">
                        </div>
                    </div>
                    <div class="wpoa-pf-field">
                        <label for="wpoa-compose-note">یادداشت خصوصی</label>
                        <textarea id="wpoa-compose-note" rows="2" placeholder="فقط خودتان می‌بینید..."></textarea>
                    </div>
                </div>
            </div>

            <div class="wpoa-section-glass">
                <div class="wpoa-section-head">
                    <span class="wpoa-section-icon wpoa-icon-teal"><span class="dashicons dashicons-bell"></span></span>
                    <h3>اعلان‌رسانی</h3>
                </div>
                <div class="wpoa-profile-fields">
                    <label class="wpoa-toggle-label"><input type="checkbox" id="wpoa-notify-email-to"> ایمیل به گیرندگان</label>
                    <label class="wpoa-toggle-label"><input type="checkbox" id="wpoa-notify-sms-to"> پیامک به گیرندگان</label>
                    <label class="wpoa-toggle-label"><input type="checkbox" id="wpoa-notify-email-cc"> ایمیل به رونوشت</label>
                    <label class="wpoa-toggle-label"><input type="checkbox" id="wpoa-notify-sms-cc"> پیامک به رونوشت</label>
                </div>
            </div>

            <div style="display:flex;flex-direction:column;gap:8px;">
                <button class="wpoa-btn wpoa-btn-glow" style="width:100%;padding:13px;" id="wpoa-send-btn">
                    <span class="dashicons dashicons-yes-alt"></span> ارسال نامه
                </button>
                <button class="wpoa-btn wpoa-btn-outline" style="width:100%;padding:13px;" id="wpoa-draft-btn">
                    <span class="dashicons dashicons-edit"></span> ذخیره پیش‌نویس
                </button>
            </div>
        </div>
    </div>
</div>

<?php include WPOA_PLUGIN_DIR . 'includes/views/layout-footer.php'; ?>