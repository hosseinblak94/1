/**
 * WP Office Automation — Liquid Glass Admin JS
 * Single-page AJAX app — sidebar removed, uses WP menus + internal folder tabs.
 *
 * @version 2.0.0
 */
(function ($) {
    'use strict';

    if (typeof WPOA === 'undefined') return;

    /* ================================================
     * STATE
     * ================================================ */
    var State = {
        currentFolder:    'inbox',
        currentPage:      1,
        totalPages:       1,
        selectedIds:      [],
        toRecipients:     [],
        ccRecipients:     [],
        composeMessageId: 0,
        replyToId:        0,
        forwardFromId:    0,
    };

    /* ================================================
     * UTILITIES
     * ================================================ */

    function escHtml(str) {
        if (!str) return '';
        return $('<div>').text(str).html();
    }

    function ajax(action, data, onSuccess, onError) {
        data = data || {};
        data.action = action;
        data.nonce  = WPOA.nonce;

        $.ajax({
            url:      WPOA.ajax_url,
            type:     'POST',
            data:     data,
            dataType: 'json',
            success: function (resp) {
                if (resp.success && typeof onSuccess === 'function') {
                    onSuccess(resp.data);
                } else if (!resp.success && typeof onError === 'function') {
                    onError(resp.data);
                } else if (!resp.success) {
                    showNotice((resp.data && resp.data.message) || 'خطایی رخ داد.', 'error');
                }
            },
            error: function () {
                showNotice('خطا در ارتباط با سرور.', 'error');
            }
        });
    }

    function showNotice(message, type) {
        type = type || 'success';
        var $n = $('<div class="wpoa-notice wpoa-notice-' + type + '">' + escHtml(message) + '</div>');
        $('body').append($n);
        setTimeout(function () { $n.fadeOut(300, function () { $n.remove(); }); }, 3500);
    }

    function priorityBadge(p) {
        var labels = { low: 'کم', normal: 'عادی', important: 'مهم', instant: 'فوری' };
        return '<span class="wpoa-priority-badge wpoa-priority-' + p + '">' + (labels[p] || p) + '</span>';
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    /* ================================================
     * MODAL
     * ================================================ */

    function openModal(title, bodyHtml, onConfirm) {
        closeModal();
        var html = '<div class="wpoa-modal-overlay" id="wpoa-modal">';
        html += '<div class="wpoa-modal">';
        html += '<div class="wpoa-modal-header"><span>' + escHtml(title) + '</span>';
        html += '<button class="wpoa-modal-close" id="wpoa-modal-close-btn">&times;</button></div>';
        html += '<div class="wpoa-modal-body" id="wpoa-modal-body">' + bodyHtml + '</div>';
        html += '<div class="wpoa-modal-footer">';
        html += '<button class="wpoa-btn wpoa-btn-glow" id="wpoa-modal-confirm">تأیید</button>';
        html += '<button class="wpoa-btn wpoa-btn-outline" id="wpoa-modal-cancel">انصراف</button>';
        html += '</div></div></div>';

        $('body').append(html);

        $('#wpoa-modal-close-btn, #wpoa-modal-cancel').on('click', closeModal);
        $('#wpoa-modal-confirm').on('click', function () {
            if (typeof onConfirm === 'function') onConfirm();
            
        });

        $('#wpoa-modal').on('click', function (e) {
            if ($(e.target).hasClass('wpoa-modal-overlay')) closeModal();
        });
        
    }

    function closeModal() {
        $('#wpoa-modal').remove();
    }

    /* ================================================
     * INBOX: LOAD FOLDER
     * ================================================ */

    function loadFolder(folder, page) {
        folder = folder || 'inbox';
        page   = page || 1;

        State.currentFolder = folder;
        State.currentPage   = page;

        var $list   = $('#wpoa-message-list');
        var $detail = $('#wpoa-message-detail');

        $detail.hide();
        $list.show().html('<div class="wpoa-loading-glass"><div class="wpoa-spinner-glass"></div><span>در حال بارگذاری...</span></div>');

        // Update active tab
        $('.wpoa-folder-tab').removeClass('active');
        $('.wpoa-folder-tab[data-folder="' + folder + '"]').addClass('active');

        ajax('wpoa_get_folder', { folder: folder, page: page }, function (data) {
            var msgs = data.messages || [];

            if (msgs.length === 0) {
                $list.html(
                    '<div class="wpoa-empty-state">' +
                    '<span class="dashicons dashicons-email-alt"></span>' +
                    '<p>نامه‌ای یافت نشد.</p>' +
                    '</div>'
                );
                $('#wpoa-pagination').html('');
                return;
            }

            renderMessageList($list, msgs);

            State.totalPages = data.total_pages || 1;
            renderPagination($('#wpoa-pagination'), State.currentPage, State.totalPages);
        });
    }

    /* ================================================
     * RENDER MESSAGE LIST
     * ================================================ */

    function renderMessageList($container, msgs) {
        var html = '';

        msgs.forEach(function (m) {
            var unreadClass = m.is_read ? '' : ' unread';
            var starClass   = m.is_starred ? ' starred' : '';
            var pinClass    = m.is_pinned ? ' pinned' : '';

            html += '<div class="wpoa-message-row' + unreadClass + '" data-id="' + m.message_id + '">';
            html += '<input type="checkbox" class="wpoa-msg-checkbox" data-id="' + m.message_id + '">';

            html += '<span class="wpoa-msg-star' + starClass + '" data-id="' + m.message_id + '" title="ستاره">';
            html += '<span class="dashicons dashicons-star-' + (m.is_starred ? 'filled' : 'empty') + '"></span></span>';

            html += '<span class="wpoa-msg-pin' + pinClass + '" data-id="' + m.message_id + '" title="سنجاق">';
            html += '<span class="dashicons dashicons-admin-post"></span></span>';

            html += '<span class="wpoa-sender">';
            if (m.sender && m.sender.avatar_url) {
                html += '<img src="' + escHtml(m.sender.avatar_url) + '" class="wpoa-avatar-xs">';
            }
            html += escHtml(m.sender ? m.sender.display_name : '') + '</span>';

            html += '<span class="wpoa-subject">' + escHtml(m.title) + '</span>';

            if (m.priority && m.priority !== 'normal') {
                html += priorityBadge(m.priority);
            }

            if (m.system_doc_number) {
                html += '<span class="wpoa-doc-number">' + escHtml(m.system_doc_number) + '</span>';
            }

            html += '<span class="wpoa-date">' + escHtml(m.sent_at_jalali || '') + '</span>';
            html += '</div>';
        });

        $container.html(html);

        // Click to view
        $container.find('.wpoa-message-row').on('click', function (e) {
            if ($(e.target).is('input[type="checkbox"]') ||
                $(e.target).closest('.wpoa-msg-star').length ||
                $(e.target).closest('.wpoa-msg-pin').length) return;
            viewMessage($(this).data('id'));
        });

        // Star toggle
        $container.find('.wpoa-msg-star').on('click', function (e) {
            e.stopPropagation();
            var $el = $(this);
            ajax('wpoa_toggle_star', { message_id: $el.data('id') }, function () {
                $el.toggleClass('starred');
                $el.find('.dashicons').toggleClass('dashicons-star-filled dashicons-star-empty');
            });
        });

        // Pin toggle
        $container.find('.wpoa-msg-pin').on('click', function (e) {
            e.stopPropagation();
            ajax('wpoa_toggle_pin', { message_id: $(this).data('id') }, function () {
                loadFolder(State.currentFolder, State.currentPage);
            });
        });
    }

    /* ================================================
     * PAGINATION
     * ================================================ */

    function renderPagination($container, current, total) {
        if (total <= 1) { $container.html(''); return; }

        var html = '';
        if (current > 1) html += '<button class="wpoa-page-btn" data-page="' + (current - 1) + '">قبلی</button>';

        for (var i = 1; i <= total; i++) {
            if (i === current) html += '<span style="padding:8px 12px;font-weight:700;color:var(--accent);">' + i + '</span>';
            else html += '<button class="wpoa-page-btn" data-page="' + i + '">' + i + '</button>';
        }

        if (current < total) html += '<button class="wpoa-page-btn" data-page="' + (current + 1) + '">بعدی</button>';

        $container.html(html);
        $container.find('.wpoa-page-btn').on('click', function () {
            loadFolder(State.currentFolder, $(this).data('page'));
        });
    }

    /* ================================================
     * SEARCH
     * ================================================ */

    function doSearch(keyword) {
        if (!keyword || keyword.length < 2) { loadFolder(State.currentFolder); return; }

        var $list = $('#wpoa-message-list');
        $list.html('<div class="wpoa-loading-glass"><div class="wpoa-spinner-glass"></div></div>');

        ajax('wpoa_search_messages', { keyword: keyword }, function (data) {
            var msgs = data.messages || [];
            if (msgs.length === 0) {
                $list.html('<div class="wpoa-empty-state"><p>نتیجه‌ای یافت نشد.</p></div>');
                return;
            }
            renderMessageList($list, msgs);
        });
    }

    /* ================================================
     * VIEW MESSAGE
     * ================================================ */

    function viewMessage(msgId) {
        var $list   = $('#wpoa-message-list');
        var $detail = $('#wpoa-message-detail');
        var $pag    = $('#wpoa-pagination');

        $list.hide();
        $pag.hide();
        $('.wpoa-folder-tabs, .wpoa-toolbar-glass').hide();
        $detail.show().html('<div class="wpoa-loading-glass"><div class="wpoa-spinner-glass"></div><span>در حال بارگذاری نامه...</span></div>');

        ajax('wpoa_view_message', { message_id: msgId }, function (data) {
            var m    = data.message;
            var recs = data.recipients || [];
            var atts = data.attachments || [];
            var tags = data.tags || [];

            var html = '';

            // Toolbar
            html += '<div class="wpoa-detail-toolbar">';
            html += '<div class="wpoa-detail-actions">';
            html += '<button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" id="wpoa-back-btn"><span class="dashicons dashicons-arrow-right-alt"></span> بازگشت</button>';
            html += '<button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" data-action="reply" data-id="' + m.id + '"><span class="dashicons dashicons-undo"></span> پاسخ</button>';
            html += '<button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" data-action="reply-all" data-id="' + m.id + '"><span class="dashicons dashicons-undo"></span> پاسخ به همه</button>';
            html += '<button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" data-action="forward" data-id="' + m.id + '"><span class="dashicons dashicons-redo"></span> ارسال مجدد</button>';
            html += '<button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" data-action="archive" data-id="' + m.id + '"><span class="dashicons dashicons-archive"></span> بایگانی</button>';
            html += '<button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm" data-action="unread" data-id="' + m.id + '"><span class="dashicons dashicons-email"></span> خوانده‌نشده</button>';

            var printUrl = WPOA.admin_url + '?wpoa_print=' + m.id + '&_wpnonce=' + WPOA.print_nonce;
            html += '<a href="' + printUrl + '" target="_blank" class="wpoa-btn wpoa-btn-outline wpoa-btn-sm"><span class="dashicons dashicons-printer"></span> چاپ</a>';
            html += '<button class="wpoa-btn wpoa-btn-danger wpoa-btn-sm" data-action="trash" data-id="' + m.id + '"><span class="dashicons dashicons-trash"></span> حذف</button>';
            html += '</div></div>';

            // Header
            html += '<div class="wpoa-detail-header">';
            html += '<div class="wpoa-detail-title">' + escHtml(m.title) + '</div>';

            html += '<div class="wpoa-detail-meta">';
            html += '<span class="wpoa-meta-item">' + priorityBadge(m.priority) + '</span>';
            if (m.system_doc_number) html += '<span class="wpoa-meta-item">شماره: ' + escHtml(m.system_doc_number) + '</span>';
            if (m.internal_doc_number) html += '<span class="wpoa-meta-item">داخلی: ' + escHtml(m.internal_doc_number) + '</span>';
            html += '<span class="wpoa-meta-item">تاریخ: ' + escHtml(m.sent_at_jalali) + '</span>';
            html += '</div>';

            html += '<div class="wpoa-detail-sender">';
            if (m.sender.avatar_url) html += '<img src="' + escHtml(m.sender.avatar_url) + '" class="wpoa-avatar-sm">';
            html += '<div><strong>' + escHtml(m.sender.display_name) + '</strong></div>';
            html += '</div>';

            if (recs.length > 0) {
                var toN = [], ccN = [];
                recs.forEach(function (r) { r.type === 'cc' ? ccN.push(r.display_name) : toN.push(r.display_name); });
                html += '<div class="wpoa-detail-recipients">';
                html += '<strong>به:</strong> ' + escHtml(toN.join('، '));
                if (ccN.length) html += '<br><strong>رونوشت:</strong> ' + escHtml(ccN.join('، '));
                html += '</div>';
            }

            html += '</div>';

            // Tags
            if (tags.length) {
                html += '<div class="wpoa-detail-tags">';
                tags.forEach(function (t) {
                    html += '<span class="wpoa-tag-chip" style="background:' + escHtml(t.color) + '">' + escHtml(t.name) + '</span>';
                });
                html += '</div>';
            }

            // Body
            html += '<div class="wpoa-detail-body">' + m.body + '</div>';

            // Signature
            if (m.signature_type !== 'none') {
                html += '<div class="wpoa-detail-signature">';
                if ((m.signature_type === 'text' || m.signature_type === 'both') && m.signature_text) {
                    html += '<div style="font-style:italic;">' + escHtml(m.signature_text) + '</div>';
                }
                if ((m.signature_type === 'image' || m.signature_type === 'both') && m.signature_image_url) {
                    html += '<img src="' + escHtml(m.signature_image_url) + '" style="max-width:150px;max-height:80px;">';
                }
                html += '</div>';
            }

            // Attachments
            if (atts.length) {
                html += '<div class="wpoa-detail-attachments">';
                html += '<strong><span class="dashicons dashicons-paperclip"></span> پیوست‌ها:</strong><br>';
                atts.forEach(function (a) {
                    html += '<a href="' + escHtml(a.file_url) + '" target="_blank" class="wpoa-att-item">';
                    html += '<span class="dashicons dashicons-media-default"></span> ';
                    html += escHtml(a.file_name) + ' (' + formatSize(a.file_size) + ')';
                    html += '</a>';
                });
                html += '</div>';
            }

            // Internal note
            if (m.internal_note && m.sender.user_id === parseInt(WPOA.user_id)) {
                html += '<div class="wpoa-meta-card-glass" style="margin-top:12px;border-color:rgba(255,204,0,0.3);background:rgba(255,204,0,0.06);">';
                html += '<h3 style="color:var(--orange);border:none;margin:0 0 6px;padding:0;">یادداشت داخلی</h3>';
                html += '<p style="margin:0;font-size:13px;color:var(--text-secondary);">' + escHtml(m.internal_note) + '</p></div>';
            }

            $detail.html(html);
            bindDetailActions(m.id);
            loadThread(m.id);
            loadMessageHistory(m.id);
            loadReferrals(m.id);
            loadMarginNotes(m.id);
            loadReadReceipts(m.id);
        });
    }

    function bindDetailActions(msgId) {
        $('#wpoa-back-btn').on('click', function () {
            $('#wpoa-message-detail').hide();
            $('#wpoa-message-list, #wpoa-pagination, .wpoa-folder-tabs, .wpoa-toolbar-glass').show();
        });

        $('[data-action="reply"]').on('click', function () {
            window.location.href = WPOA.compose_url + '&reply_to=' + msgId;
        });
        $('[data-action="reply-all"]').on('click', function () {
            window.location.href = WPOA.compose_url + '&reply_to=' + msgId + '&reply_all=1';
        });
        $('[data-action="forward"]').on('click', function () {
            window.location.href = WPOA.compose_url + '&forward=' + msgId;
        });
        $('[data-action="archive"]').on('click', function () {
            ajax('wpoa_archive_message', { message_id: msgId }, function (r) { showNotice(r.message); goBackToList(); });
        });
        $('[data-action="unread"]').on('click', function () {
            ajax('wpoa_mark_unread', { message_id: msgId }, function (r) { showNotice(r.message); });
        });
        $('[data-action="trash"]').on('click', function () {
            if (!confirm('آیا از حذف این نامه اطمینان دارید؟')) return;
            ajax('wpoa_trash_message', { message_id: msgId }, function (r) { showNotice(r.message); goBackToList(); });
        });
    }

    function goBackToList() {
        $('#wpoa-message-detail').hide();
        $('#wpoa-message-list, #wpoa-pagination, .wpoa-folder-tabs, .wpoa-toolbar-glass').show();
        loadFolder(State.currentFolder, State.currentPage);
    }

    /* ================================================
     * THREAD
     * ================================================ */

    function loadThread(msgId) {
        ajax('wpoa_get_thread', { message_id: msgId }, function (data) {
            var thread = data.thread || [];
            if (thread.length <= 1) return;

            var html = '<div class="wpoa-thread-section">';
            html += '<h3><span class="dashicons dashicons-admin-comments"></span> مکاتبات مرتبط (' + thread.length + ')</h3>';

            thread.forEach(function (t) {
                var cur = (t.id === msgId) ? ' wpoa-thread-current' : '';
                html += '<div class="wpoa-thread-item' + cur + '" data-thread-id="' + t.id + '">';
                html += '<div class="wpoa-thread-header">';
                if (t.sender_avatar_url) html += '<img src="' + escHtml(t.sender_avatar_url) + '" class="wpoa-avatar-xs">';
                html += '<strong>' + escHtml(t.sender_display_name || '') + '</strong>';
                html += '<span style="color:var(--text-tertiary);font-size:12px;margin-right:6px;">' + escHtml(t.title || '') + '</span>';
                html += '<span class="wpoa-thread-date">' + escHtml(t.sent_at_jalali || '') + '</span>';
                html += '</div>';
                html += '<div class="wpoa-thread-body">' + t.body + '</div>';
                html += '</div>';
            });
            html += '</div>';
            $('#wpoa-message-detail').append(html);

            $('.wpoa-thread-item').on('click', function () {
                var tid = $(this).data('thread-id');
                if (tid !== msgId) viewMessage(tid);
            });
        });
    }

    /* ================================================
     * MESSAGE HISTORY
     * ================================================ */

    function loadMessageHistory(msgId) {
        ajax('wpoa_get_message_history', { message_id: msgId }, function (data) {
            var h = data.history || [];
            if (!h.length) return;

            var html = '<div class="wpoa-history-section">';
            html += '<h3><span class="dashicons dashicons-backup"></span> تاریخچه فعالیت</h3>';
            html += '<div class="wpoa-history-timeline">';

            h.forEach(function (i) {
                html += '<div class="wpoa-history-item"><div class="wpoa-history-dot"></div><div class="wpoa-history-content">';
                html += '<strong>' + escHtml(i.user_name || '') + '</strong> — ' + escHtml(i.action_label || '');
                if (i.details) html += '<div class="wpoa-history-details">' + escHtml(i.details) + '</div>';
                html += '<div class="wpoa-history-time" dir="ltr">' + escHtml(i.created_at || '') + '</div>';
                html += '</div></div>';
            });

            html += '</div></div>';
            $('#wpoa-message-detail').append(html);
        });
    }

    /* ================================================
     * REFERRALS
     * ================================================ */

    function loadReferrals(messageId) {
        ajax('wpoa_get_msg_referrals', { message_id: messageId }, function (data) {
            var refs = data.referrals || [];
            if (!refs.length && !WPOA.can_refer) return;

            var html = '<div class="wpoa-referral-section">';
            html += '<h3><span class="dashicons dashicons-randomize"></span> ارجاعات</h3>';

            if (WPOA.can_refer) {
                html += '<button class="wpoa-btn wpoa-btn-glow wpoa-btn-sm" id="wpoa-refer-btn" data-msg="' + messageId + '">';
                html += '<span class="dashicons dashicons-plus-alt"></span> ارجاع جدید</button>';
            }

            if (refs.length) {
                html += '<div class="wpoa-referral-list">';
                refs.forEach(function (r) { html += renderReferralCard(r, messageId); });
                html += '</div>';
            }
            html += '</div>';
            $('#wpoa-message-detail').append(html);

            $('#wpoa-refer-btn').on('click', function () { openReferralModal(messageId); });
            $('.wpoa-ref-respond-btn').on('click', function () { openRespondModal($(this).data('ref-id'), $(this).data('status')); });
            $('.wpoa-ref-rerefer-btn').on('click', function () { openReferralModal($(this).data('msg-id'), $(this).data('ref-id')); });
        });
    }

    function renderReferralCard(r, messageId) {
        var sc = 'wpoa-ref-status-' + r.status;
        var h = '<div class="wpoa-referral-card">';
        h += '<div class="wpoa-ref-header">';
        h += '<span class="wpoa-ref-type">' + escHtml(r.type_label) + '</span>';
        h += '<span class="wpoa-ref-badge ' + sc + '">' + escHtml(r.status_label) + '</span>';
        if (r.deadline_jalali) h += '<span class="wpoa-ref-deadline">مهلت: ' + escHtml(r.deadline_jalali) + '</span>';
        h += '</div>';

        h += '<div class="wpoa-ref-flow">';
        if (r.from_avatar_url) h += '<img src="' + escHtml(r.from_avatar_url) + '" class="wpoa-avatar-xs">';
        h += '<strong>' + escHtml(r.from_display_name) + '</strong>';
        h += ' <span class="wpoa-ref-arrow">&larr;</span> ';
        if (r.to_avatar_url) h += '<img src="' + escHtml(r.to_avatar_url) + '" class="wpoa-avatar-xs">';
        h += '<strong>' + escHtml(r.to_display_name) + '</strong></div>';

        if (r.instruction) h += '<div class="wpoa-ref-instruction"><strong>دستور:</strong> ' + escHtml(r.instruction) + '</div>';
        if (r.response) h += '<div class="wpoa-ref-response"><strong>پاسخ:</strong> ' + escHtml(r.response) + '</div>';

        if (r.can_respond) {
            h += '<div class="wpoa-ref-actions">';
            h += '<button class="wpoa-btn wpoa-btn-glow wpoa-btn-sm wpoa-ref-respond-btn" data-ref-id="' + r.id + '" data-status="completed">اقدام شد</button>';
            if (r.type === 'approval') {
                h += '<button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm wpoa-ref-respond-btn" data-ref-id="' + r.id + '" data-status="accepted" style="color:var(--green);border-color:var(--green);">تأیید</button>';
                h += '<button class="wpoa-btn wpoa-btn-danger wpoa-btn-sm wpoa-ref-respond-btn" data-ref-id="' + r.id + '" data-status="rejected">رد</button>';
            }
            h += '<button class="wpoa-btn wpoa-btn-outline wpoa-btn-sm wpoa-ref-rerefer-btn" data-ref-id="' + r.id + '" data-msg-id="' + messageId + '">ارجاع مجدد</button>';
            h += '</div>';
        }
        h += '</div>';
        return h;
    }

    function openReferralModal(messageId, parentRefId) {
        var h = '<div class="wpoa-field"><label>گیرنده ارجاع:</label>';
        h += '<input type="text" id="wpoa-ref-to-search" placeholder="نام کاربر..." autocomplete="off">';
        h += '<input type="hidden" id="wpoa-ref-to-id">';
        h += '<div id="wpoa-ref-dropdown" class="wpoa-autocomplete-glass" style="display:none;position:relative;"></div></div>';
        h += '<div class="wpoa-field"><label>نوع ارجاع:</label><select id="wpoa-ref-type">';
        h += '<option value="referral">ارجاع</option><option value="approval">درخواست تأیید</option>';
        h += '<option value="action">جهت اقدام</option><option value="info">جهت اطلاع</option></select></div>';
        h += '<div class="wpoa-field"><label>دستور / توضیحات:</label><textarea id="wpoa-ref-instruction" rows="3"></textarea></div>';
        h += '<div class="wpoa-field"><label>مهلت:</label><input type="date" id="wpoa-ref-deadline" dir="ltr"></div>';

        openModal('ارجاع نامه', h, function () {
            var toId = $('#wpoa-ref-to-id').val();
            if (!toId) { showNotice('لطفاً گیرنده ارجاع را انتخاب کنید.', 'error'); return; }

            var act = parentRefId ? 'wpoa_re_refer' : 'wpoa_create_referral';
            var p = { message_id: messageId, to_user_id: toId, type: $('#wpoa-ref-type').val(),
                      instruction: $('#wpoa-ref-instruction').val(), deadline: $('#wpoa-ref-deadline').val() };
            if (parentRefId) p.parent_ref_id = parentRefId;

            ajax(act, p, function (res) {
                showNotice(res.message); closeModal();
                $('.wpoa-referral-section').remove(); loadReferrals(messageId);
            });
        });

        var rt;
        $('#wpoa-ref-to-search').on('input', function () {
            var kw = $(this).val().trim(); clearTimeout(rt);
            if (kw.length < 2) { $('#wpoa-ref-dropdown').hide(); return; }
            rt = setTimeout(function () {
                ajax('wpoa_search_users', { keyword: kw }, function (d) {
                    var $dd = $('#wpoa-ref-dropdown').empty();
                    (d.users || []).forEach(function (u) {
                        var $i = $('<div class="wpoa-ac-item">' +
                            (u.avatar_url ? '<img src="' + escHtml(u.avatar_url) + '" class="wpoa-avatar-xs">' : '') +
                            escHtml(u.display_name) +
                            (u.org_role_name ? ' <small>(' + escHtml(u.org_role_name) + ')</small>' : '') + '</div>');
                        $i.on('click', function () { $('#wpoa-ref-to-search').val(u.display_name); $('#wpoa-ref-to-id').val(u.user_id); $dd.hide(); });
                        $dd.append($i);
                    });
                    $dd.show();
                });
            }, 300);
        });
    }

    function openRespondModal(referralId, presetStatus) {
        var h = '<div class="wpoa-field"><label>پاسخ / توضیحات:</label><textarea id="wpoa-ref-response" rows="3"></textarea></div>';
        h += '<div class="wpoa-field"><label>وضعیت:</label><select id="wpoa-ref-status">';
        h += '<option value="completed"' + (presetStatus === 'completed' ? ' selected' : '') + '>اقدام شد</option>';
        h += '<option value="accepted"' + (presetStatus === 'accepted' ? ' selected' : '') + '>تأیید</option>';
        h += '<option value="rejected"' + (presetStatus === 'rejected' ? ' selected' : '') + '>رد</option></select></div>';

        openModal('پاسخ به ارجاع', h, function () {
            ajax('wpoa_respond_referral', {
                referral_id: referralId, status: $('#wpoa-ref-status').val(), response: $('#wpoa-ref-response').val()
            }, function (res) { showNotice(res.message); closeModal(); goBackToList(); });
        });
    }

    /* ================================================
     * REFERRAL QUEUE & SENT
     * ================================================ */

    function loadReferralQueue(page) {
        page = page || 1;
        var $list = $('#wpoa-message-list');
        $list.html('<div class="wpoa-loading-glass"><div class="wpoa-spinner-glass"></div><span>در حال بارگذاری ارجاعات...</span></div>');
        $('#wpoa-message-detail').hide();
        $list.show();

        ajax('wpoa_get_referral_queue', { page: page }, function (data) {
            var refs = data.referrals || [];
            if (!refs.length) { $list.html('<div class="wpoa-empty-state"><span class="dashicons dashicons-randomize"></span><p>ارجاع در انتظاری ندارید.</p></div>'); return; }

            var h = '';
            refs.forEach(function (r) {
                h += '<div class="wpoa-message-row wpoa-referral-row" data-msg-id="' + r.message_id + '">';
                h += '<span class="wpoa-ref-type-badge wpoa-ref-type-' + r.type + '">' + escHtml(r.type_label) + '</span>';
                h += '<span class="wpoa-sender">';
                if (r.from_avatar_url) h += '<img src="' + escHtml(r.from_avatar_url) + '" class="wpoa-avatar-xs">';
                h += escHtml(r.from_display_name) + '</span>';
                h += '<span class="wpoa-subject">' + escHtml(r.message_title) + '</span>';
                if (r.priority && r.priority !== 'normal') h += priorityBadge(r.priority);
                if (r.deadline_jalali) h += '<span class="wpoa-ref-deadline-inline">مهلت: ' + escHtml(r.deadline_jalali) + '</span>';
                h += '<span class="wpoa-date">' + escHtml(r.created_at || '') + '</span></div>';
            });

            $list.html(h);
            $list.find('.wpoa-referral-row').on('click', function () { viewMessage($(this).data('msg-id')); });
        });
    }

    function loadReferralSent(page) {
        page = page || 1;
        var $list = $('#wpoa-message-list');
        $list.html('<div class="wpoa-loading-glass"><div class="wpoa-spinner-glass"></div></div>');
        $('#wpoa-message-detail').hide();
        $list.show();

        ajax('wpoa_get_referral_sent', { page: page }, function (data) {
            var refs = data.referrals || [];
            if (!refs.length) { $list.html('<div class="wpoa-empty-state"><span class="dashicons dashicons-external"></span><p>ارجاعی ارسال نکرده‌اید.</p></div>'); return; }

            var h = '';
            refs.forEach(function (r) {
                h += '<div class="wpoa-message-row wpoa-referral-row" data-msg-id="' + r.message_id + '">';
                h += '<span class="wpoa-ref-type-badge">' + escHtml(r.type_label) + '</span>';
                h += '<span class="wpoa-sender">' + escHtml(r.to_display_name) + '</span>';
                h += '<span class="wpoa-subject">' + escHtml(r.message_title) + '</span>';
                h += '<span class="wpoa-ref-badge-inline wpoa-ref-status-' + r.status + '">' + escHtml(r.status_label) + '</span>';
                h += '<span class="wpoa-date">' + escHtml(r.created_at || '') + '</span></div>';
            });

            $list.html(h);
            $list.find('.wpoa-referral-row').on('click', function () { viewMessage($(this).data('msg-id')); });
        });
    }

    /* ================================================
     * MARGIN NOTES
     * ================================================ */

    function loadMarginNotes(messageId) {
        ajax('wpoa_get_margin_notes', { message_id: messageId }, function (data) {
            var notes = data.notes || [];

            var h = '<div class="wpoa-margin-section">';
            h += '<h3><span class="dashicons dashicons-edit"></span> حاشیه‌نویسی</h3>';
            h += '<div class="wpoa-margin-form">';
            h += '<textarea id="wpoa-note-text" class="wpoa-margin-input" rows="2" placeholder="حاشیه خود را بنویسید..."></textarea>';
            h += '<div class="wpoa-margin-form-actions">';
            h += '<label class="wpoa-margin-private"><input type="checkbox" id="wpoa-note-private"> خصوصی</label>';
            h += '<button class="wpoa-btn wpoa-btn-glow wpoa-btn-sm" id="wpoa-add-note-btn" data-msg="' + messageId + '">ثبت حاشیه</button>';
            h += '</div></div>';

            if (notes.length) {
                h += '<div class="wpoa-margin-list">';
                notes.forEach(function (n) {
                    h += '<div class="wpoa-margin-note' + (n.is_private ? ' wpoa-margin-private-note' : '') + '">';
                    h += '<div class="wpoa-margin-note-header">';
                    if (n.avatar_url) h += '<img src="' + escHtml(n.avatar_url) + '" class="wpoa-avatar-xs">';
                    h += '<strong>' + escHtml(n.author_name) + '</strong>';
                    if (n.is_private) h += '<span class="wpoa-margin-private-label">خصوصی</span>';
                    h += '<span class="wpoa-margin-time" dir="ltr">' + escHtml(n.created_at) + '</span>';
                    if (n.is_mine) h += '<button class="wpoa-note-delete" data-note-id="' + n.id + '">&times;</button>';
                    h += '</div>';
                    h += '<div class="wpoa-margin-note-body">' + escHtml(n.note_text) + '</div></div>';
                });
                h += '</div>';
            }
            h += '</div>';
            $('#wpoa-message-detail').append(h);

            $('#wpoa-add-note-btn').on('click', function () {
                var text = $('#wpoa-note-text').val().trim();
                if (!text) { showNotice('متن حاشیه خالی است.', 'error'); return; }
                ajax('wpoa_add_margin_note', { message_id: messageId, note_text: text, is_private: $('#wpoa-note-private').is(':checked') ? '1' : '0' }, function (r) {
                    showNotice(r.message); $('.wpoa-margin-section').remove(); loadMarginNotes(messageId);
                });
            });

            $('.wpoa-note-delete').on('click', function () {
                var nid = $(this).data('note-id');
                if (!confirm('حذف این حاشیه؟')) return;
                ajax('wpoa_delete_margin_note', { note_id: nid }, function (r) {
                    showNotice(r.message); $('.wpoa-margin-section').remove(); loadMarginNotes(messageId);
                });
            });
        });
    }

    /* ================================================
     * READ RECEIPTS
     * ================================================ */

    function loadReadReceipts(messageId) {
        ajax('wpoa_get_read_receipts', { message_id: messageId }, function (data) {
            var rr = data.receipts || [];
            if (!rr.length) return;

            var h = '<div class="wpoa-receipts-section">';
            h += '<h3><span class="dashicons dashicons-visibility"></span> وضعیت خوانده‌شدن</h3>';
            h += '<div class="wpoa-receipts-list">';

            rr.forEach(function (r) {
                var cls = r.is_read ? 'wpoa-receipt-read' : 'wpoa-receipt-unread';
                h += '<div class="wpoa-receipt-item ' + cls + '">';
                if (r.avatar_url) h += '<img src="' + escHtml(r.avatar_url) + '" class="wpoa-avatar-xs">';
                h += '<span class="wpoa-receipt-name">' + escHtml(r.display_name) + '</span>';
                h += '<span class="wpoa-receipt-icon">' + (r.is_read ? '&#10003;' : '&#9711;') + '</span>';
                h += '<span class="wpoa-receipt-label">' + (r.is_read ? 'خوانده‌شده' : 'خوانده‌نشده') + '</span>';
                if (r.read_at) h += '<span class="wpoa-receipt-time" dir="ltr">' + escHtml(r.read_at) + '</span>';
                h += '</div>';
            });

            h += '</div></div>';
            $('#wpoa-message-detail').append(h);
        });
    }

    /* ================================================
     * COMPOSE
     * ================================================ */

    function initCompose() {
        if (!$('#wpoa-compose-page').length) return;

        function setupAutocomplete(inputId, dropdownId, tagsId, hiddenId) {
            var t;
            $('#' + inputId).on('input', function () {
                var kw = $(this).val().trim(); clearTimeout(t);
                if (kw.length < 2) { $('#' + dropdownId).hide(); return; }
                t = setTimeout(function () {
                    ajax('wpoa_search_users', { keyword: kw }, function (d) {
                        var $dd = $('#' + dropdownId).empty();
                        (d.users || []).forEach(function (u) {
                            var $i = $('<div class="wpoa-ac-item">' +
                                (u.avatar_url ? '<img src="' + escHtml(u.avatar_url) + '" class="wpoa-avatar-xs">' : '') +
                                escHtml(u.display_name) +
                                (u.org_role_name ? ' <small>(' + escHtml(u.org_role_name) + ')</small>' : '') + '</div>');
                            $i.on('click', function () { addRecipient(u, tagsId, hiddenId); $('#' + inputId).val(''); $dd.hide(); });
                            $dd.append($i);
                        });
                        $dd.show();
                    });
                }, 300);
            });
            $(document).on('click', function (e) { if (!$(e.target).closest('#' + inputId + ', #' + dropdownId).length) $('#' + dropdownId).hide(); });
        }

        function addRecipient(user, tagsId, hiddenId) {
            var arr = hiddenId === 'wpoa-to-ids' ? State.toRecipients : State.ccRecipients;
            for (var i = 0; i < arr.length; i++) { if (arr[i].user_id === user.user_id) return; }
            arr.push({ user_id: user.user_id, display_name: user.display_name });

            var $chip = $('<span class="wpoa-recipient-chip">' + escHtml(user.display_name) + ' <span class="remove" data-uid="' + user.user_id + '">&times;</span></span>');
            $chip.find('.remove').on('click', function () {
                var uid = parseInt($(this).data('uid'));
                var list = hiddenId === 'wpoa-to-ids' ? State.toRecipients : State.ccRecipients;
                for (var j = list.length - 1; j >= 0; j--) { if (list[j].user_id === uid) list.splice(j, 1); }
                $(this).parent().remove();
                $('#' + hiddenId).val(JSON.stringify(list.map(function (r) { return r.user_id; })));
            });

            $('#' + tagsId).append($chip);
            $('#' + hiddenId).val(JSON.stringify(arr.map(function (r) { return r.user_id; })));
        }

        setupAutocomplete('wpoa-to-input', 'wpoa-to-dropdown', 'wpoa-to-tags', 'wpoa-to-ids');
        setupAutocomplete('wpoa-cc-input', 'wpoa-cc-dropdown', 'wpoa-cc-tags', 'wpoa-cc-ids');

        // Attachments
        $('#wpoa-attach-btn').on('click', function () { $('#wpoa-attachment-input').click(); });
        $('#wpoa-attachment-input').on('change', function () {
            var files = this.files; if (!files.length) return;
            var msgId = parseInt($('#wpoa-compose-msg-id').val()) || 0;

            function upload(file) {
                var fd = new FormData();
                fd.append('action', 'wpoa_upload_attachment'); fd.append('nonce', WPOA.nonce);
                fd.append('message_id', msgId); fd.append('file', file);
                $.ajax({ url: WPOA.ajax_url, type: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
                    success: function (r) {
                        if (r.success) {
                            var d = r.data;
                            $('#wpoa-attachment-list').append(
                                '<div class="wpoa-att-item" data-att-id="' + d.attachment_id + '">' +
                                '<span class="dashicons dashicons-paperclip"></span>' +
                                '<span>' + escHtml(d.file_name) + ' (' + formatSize(d.file_size) + ')</span>' +
                                '<button class="wpoa-att-remove" data-att-id="' + d.attachment_id + '">&times;</button></div>');
                        } else { showNotice(r.data.message, 'error'); }
                    }
                });
            }

            if (msgId === 0) {
                saveDraft(function (newId) { msgId = newId; for (var i = 0; i < files.length; i++) upload(files[i]); });
            } else {
                for (var i = 0; i < files.length; i++) upload(files[i]);
            }
            $(this).val('');
        });

        $(document).on('click', '.wpoa-att-remove', function () {
            var $el = $(this).closest('.wpoa-att-item');
            ajax('wpoa_delete_attachment', { attachment_id: $(this).data('att-id') }, function () { $el.remove(); });
        });

        function getBody() {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wpoa-compose-body')) return tinyMCE.get('wpoa-compose-body').getContent();
            return $('#wpoa-compose-body').val();
        }

        function saveDraft(cb) {
            ajax('wpoa_save_draft', {
                message_id: $('#wpoa-compose-msg-id').val() || 0,
                title: $('#wpoa-compose-title').val(), body: getBody(),
                priority: $('#wpoa-compose-priority').val(), internal_doc_number: $('#wpoa-compose-internal-doc').val(),
                signature_type: $('#wpoa-compose-sig').val(), internal_note: $('#wpoa-compose-note').val(),
                tags: $('#wpoa-compose-tags').val()
            }, function (r) {
                if (r.message_id) { $('#wpoa-compose-msg-id').val(r.message_id); State.composeMessageId = r.message_id; }
                if (typeof cb === 'function') cb(r.message_id); else showNotice(r.message);
            });
        }

        function sendMessage() {
            var toIds = State.toRecipients.map(function (r) { return r.user_id; });
            var ccIds = State.ccRecipients.map(function (r) { return r.user_id; });

            ajax('wpoa_send_message', {
                message_id: $('#wpoa-compose-msg-id').val() || 0,
                title: $('#wpoa-compose-title').val(), body: getBody(),
                priority: $('#wpoa-compose-priority').val(), internal_doc_number: $('#wpoa-compose-internal-doc').val(),
                signature_type: $('#wpoa-compose-sig').val(), internal_note: $('#wpoa-compose-note').val(),
                tags: $('#wpoa-compose-tags').val(),
                recipients: JSON.stringify(toIds), cc: JSON.stringify(ccIds),
                to_notify_email: JSON.stringify($('#wpoa-notify-email-to').is(':checked') ? toIds : []),
                to_notify_sms: JSON.stringify($('#wpoa-notify-sms-to').is(':checked') ? toIds : []),
                cc_notify_email: JSON.stringify($('#wpoa-notify-email-cc').is(':checked') ? ccIds : []),
                cc_notify_sms: JSON.stringify($('#wpoa-notify-sms-cc').is(':checked') ? ccIds : []),
                reply_to_id: State.replyToId, forward_from_id: State.forwardFromId
            }, function (r) {
                showNotice(r.message);
                if (r.system_doc_number) showNotice('شماره نامه: ' + r.system_doc_number);
                setTimeout(function () { window.location.href = WPOA.inbox_url; }, 1500);
            });
        }

        $('#wpoa-draft-btn').on('click', function () { saveDraft(); });
        $('#wpoa-send-btn').on('click', function () { sendMessage(); });

        // Reply / forward from URL
        var params = new URLSearchParams(window.location.search);
        var replyId = parseInt(params.get('reply_to') || 0), replyAll = params.get('reply_all') === '1', forwardId = parseInt(params.get('forward') || 0);

        if (replyId) {
            ajax(replyAll ? 'wpoa_prepare_reply_all' : 'wpoa_prepare_reply', { message_id: replyId }, function (d) {
                $('#wpoa-compose-title').val(d.title || ''); $('#wpoa-compose-priority').val(d.priority || 'normal');
                State.replyToId = d.reply_to_id || replyId;
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wpoa-compose-body')) tinyMCE.get('wpoa-compose-body').setContent(d.body || '');
                (d.recipients || []).forEach(function (u) { addRecipient(u, 'wpoa-to-tags', 'wpoa-to-ids'); });
                (d.cc || []).forEach(function (u) { addRecipient(u, 'wpoa-cc-tags', 'wpoa-cc-ids'); });
            });
        }

        if (forwardId) {
            ajax('wpoa_prepare_forward', { message_id: forwardId }, function (d) {
                $('#wpoa-compose-title').val(d.title || ''); $('#wpoa-compose-priority').val(d.priority || 'normal');
                $('#wpoa-compose-internal-doc').val(d.internal_doc_number || '');
                State.forwardFromId = d.forward_from_id || forwardId;
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('wpoa-compose-body')) tinyMCE.get('wpoa-compose-body').setContent(d.body || '');
                (d.original_attachments || []).forEach(function (a) {
                    $('#wpoa-attachment-list').append('<div class="wpoa-att-item"><span class="dashicons dashicons-paperclip"></span><a href="' + escHtml(a.file_url) + '" target="_blank">' + escHtml(a.file_name) + '</a></div>');
                });
            });
        }
    }

       /* ================================================
     * PROFILE PAGE
     * ================================================ */

    function initProfile() {
        if (!$('#wpoa-profile-page').length) return;

        // ── Save profile ──
        $('#wpoa-profile-save').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true).css('opacity', 0.7);

            ajax('wpoa_update_profile', {
                display_name:   $('#wpoa-prof-name').val(),
                phone:          $('#wpoa-prof-phone').val(),
                signature_text: $('#wpoa-prof-sig').val()
            }, function (r) {
                showNotice(r.message || 'تغییرات ذخیره شد.');
                $btn.prop('disabled', false).css('opacity', 1);
                // Update hero name live
                $('.wpoa-hero-name').text($('#wpoa-prof-name').val());
            }, function (r) {
                showNotice((r && r.message) || 'خطا در ذخیره اطلاعات.', 'error');
                $btn.prop('disabled', false).css('opacity', 1);
            });
        });

        // ── Upload avatar ──
        $('#wpoa-avatar-upload-btn').on('click', function () {
            $('#wpoa-avatar-file').click();
        });

        $('#wpoa-avatar-file').on('change', function () {
            if (!this.files.length) return;

            var fd = new FormData();
            fd.append('action', 'wpoa_upload_avatar');
            fd.append('nonce', WPOA.nonce);
            fd.append('avatar', this.files[0]);

            $.ajax({
                url: WPOA.ajax_url,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (resp) {
                    if (resp.success && resp.data) {
                        showNotice(resp.data.message || 'تصویر آپلود شد.');
                        var url = resp.data.file_url;
                        $('#wpoa-avatar-preview').html('<img src="' + escHtml(url) + '" alt="">');
                        $('.wpoa-hero-avatar').html('<img src="' + escHtml(url) + '" alt="">');
                    } else {
                        showNotice((resp.data && resp.data.message) || 'خطا در آپلود تصویر.', 'error');
                    }
                },
                error: function () {
                    showNotice('خطا در ارتباط با سرور.', 'error');
                }
            });
        });

        // ── Upload signature image ──
        $('#wpoa-sig-img-btn').on('click', function () {
            $('#wpoa-sig-img-file').click();
        });

        $('#wpoa-sig-img-file').on('change', function () {
            if (!this.files.length) return;

            var fd = new FormData();
            fd.append('action', 'wpoa_upload_signature');
            fd.append('nonce', WPOA.nonce);
            fd.append('signature_image', this.files[0]);

            $.ajax({
                url: WPOA.ajax_url,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function (resp) {
                    if (resp.success && resp.data) {
                        showNotice(resp.data.message || 'تصویر امضا آپلود شد.');
                        $('#wpoa-sig-img-preview').html('<img src="' + escHtml(resp.data.file_url) + '" alt="">');
                    } else {
                        showNotice((resp.data && resp.data.message) || 'خطا در آپلود.', 'error');
                    }
                },
                error: function () {
                    showNotice('خطا در ارتباط با سرور.', 'error');
                }
            });
        });

        // ── Change password ──
        $('#wpoa-pw-change').on('click', function () {
            var $btn      = $(this);
            var curPw     = $('#wpoa-pw-current').val();
            var newPw     = $('#wpoa-pw-new').val();
            var confirmPw = $('#wpoa-pw-confirm').val();

            // Client-side validation
            if (!curPw) {
                showNotice('رمز فعلی را وارد کنید.', 'error');
                return;
            }
            if (!newPw || newPw.length < 6) {
                showNotice('رمز جدید باید حداقل ۶ کاراکتر باشد.', 'error');
                return;
            }
            if (newPw !== confirmPw) {
                showNotice('رمز جدید و تکرار آن مطابقت ندارد.', 'error');
                return;
            }

            $btn.prop('disabled', true).css('opacity', 0.7);

            ajax('wpoa_change_password', {
                current_password: curPw,
                new_password:     newPw,
                confirm_password: confirmPw
            }, function (r) {
                showNotice(r.message || 'رمز عبور تغییر کرد.');
                $('#wpoa-pw-current, #wpoa-pw-new, #wpoa-pw-confirm').val('');
                $btn.prop('disabled', false).css('opacity', 1);
            }, function (r) {
                showNotice((r && r.message) || 'خطا در تغییر رمز عبور.', 'error');
                $btn.prop('disabled', false).css('opacity', 1);
            });
        });
    }
    
    /* ================================================
     * ORG PAGE
     * ================================================ */

    var _orgV = 'tree';

    function _p(action, data, ok, fail) {
        data = data || {}; data.action = action; data.nonce = WPOA.nonce;
        $.ajax({ url: WPOA.ajax_url, type: 'POST', data: data, dataType: 'json', timeout: 12000,
            success: function (r) { if (r && r.success) { if (ok) ok(r.data || {}); } else { var m = (r && r.data && r.data.message) || 'عملیات ناموفق'; if (fail) fail(m); else showNotice(m, 'error'); } },
            error: function () { var m = 'خطا در ارتباط با سرور'; if (fail) fail(m); else showNotice(m, 'error'); }
        });
    }

    function _val(id) { return ($('#' + id).val() || '').trim(); }
    function _fi(lbl, html) { return '<div style="margin-bottom:14px;"><label style="display:block;font-size:12px;font-weight:600;color:#636366;margin-bottom:5px;font-family:var(--font);">' + lbl + '</label>' + html + '</div>'; }
    function _inp(id, ph, val) { return '<input type="text" id="' + id + '" value="' + escHtml(val || '') + '" placeholder="' + escHtml(ph || '') + '" style="width:100%;padding:10px 14px;border:1.5px solid rgba(200,210,230,0.5);border-radius:8px;font-family:var(--font);font-size:13px;background:rgba(255,255,255,0.65);">'; }
    function _ta(id, val) { return '<textarea id="' + id + '" rows="2" style="width:100%;padding:10px 14px;border:1.5px solid rgba(200,210,230,0.5);border-radius:8px;font-family:var(--font);font-size:13px;resize:vertical;background:rgba(255,255,255,0.65);">' + escHtml(val || '') + '</textarea>'; }

    function _parentSel(id) {
        var h = '<select id="' + id + '" style="width:100%;padding:10px 14px;border:1.5px solid rgba(200,210,230,0.5);border-radius:8px;font-family:var(--font);font-size:13px;">';
        h += '<option value="">— ریشه —</option>';
        (function add(n, pre) { n.forEach(function (x) { h += '<option value="' + x.id + '">' + pre + escHtml(x.name) + '</option>'; if (x.children) add(x.children, pre + '── '); }); })(window.WPOA_TREE || [], '');
        return h + '</select>';
    }

    /* ── LOCAL DATA HELPERS ── */
    function _wpUser(uid) {
        var users = window.WPOA_WP_USERS || [];
        for (var i = 0; i < users.length; i++) { if (users[i].id === uid) return users[i]; }
        return null;
    }

    function _removeUserFromAllNodes(tree, userId) {
        tree.forEach(function (n) {
            n.users = (n.users || []).filter(function (u) { return u.user_id !== userId; });
            if (n.children) _removeUserFromAllNodes(n.children, userId);
        });
    }

    function _addUserToNode(tree, unitId, userObj) {
        for (var i = 0; i < tree.length; i++) {
            if (tree[i].id === unitId) { tree[i].users = tree[i].users || []; tree[i].users.push(userObj); return true; }
            if (tree[i].children && _addUserToNode(tree[i].children, unitId, userObj)) return true;
        }
        return false;
    }

    function _removeNodeFromTree(tree, unitId) {
        for (var i = 0; i < tree.length; i++) {
            if (tree[i].id === unitId) { tree.splice(i, 1); return true; }
            if (tree[i].children && _removeNodeFromTree(tree[i].children, unitId)) return true;
        }
        return false;
    }

    function _addNodeToTree(tree, parentId, node) {
        if (!parentId) { tree.push(node); return true; }
        for (var i = 0; i < tree.length; i++) {
            if (tree[i].id === parentId) { tree[i].children = tree[i].children || []; tree[i].children.push(node); return true; }
            if (tree[i].children && _addNodeToTree(tree[i].children, parentId, node)) return true;
        }
        return false;
    }

    function _updateNodeInTree(tree, unitId, name, desc) {
        for (var i = 0; i < tree.length; i++) {
            if (tree[i].id === unitId) { tree[i].name = name; tree[i].desc = desc; return true; }
            if (tree[i].children && _updateNodeInTree(tree[i].children, unitId, name, desc)) return true;
        }
        return false;
    }

    function _syncAssigned() {
        // Rebuild WPOA_ASSIGNED from tree
        var list = [];
        (function walk(nodes) {
            nodes.forEach(function (n) {
                (n.users || []).forEach(function (u) {
                    var wp = _wpUser(u.user_id);
                    list.push({
                        user_id: u.user_id,
                        unit_id: n.id,
                        name: u.display_name || (wp ? wp.name : ''),
                        email: u.email || (wp ? wp.email : ''),
                        unit: n.name,
                        avatar: u.avatar_url || (wp ? wp.avatar : ''),
                    });
                });
                if (n.children) walk(n.children);
            });
        })(window.WPOA_TREE || []);
        window.WPOA_ASSIGNED = list;
    }

    function _syncPositions() {
        var list = [];
        (function walk(nodes) {
            nodes.forEach(function (n) {
                list.push({ id: n.id, name: n.name });
                if (n.children) walk(n.children);
            });
        })(window.WPOA_TREE || []);
        window.WPOA_POSITIONS = list;
    }

    function _refresh() {
        _syncAssigned();
        _syncPositions();
        drawChart();
        drawUL();
    }

    /* ── MULTI-USER PICKER ── */
    function _multiPicker(prefix) {
        return '<div class="wpoa-user-picker">' +
            '<input type="text" id="' + prefix + '-search" placeholder="جستجوی نام کاربر..." autocomplete="off">' +
            '<div class="wpoa-user-picker-dd" id="' + prefix + '-dd"></div>' +
            '</div>' +
            '<div id="' + prefix + '-chips" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;"></div>';
    }

    function _bindMulti(prefix, excludeIds) {
        var users = window.WPOA_WP_USERS || [];
        var selected = [];
        var excluded = excludeIds || [];
        var $inp = $('#' + prefix + '-search'), $dd = $('#' + prefix + '-dd'), $chips = $('#' + prefix + '-chips');

        function render() {
            var h = '';
            selected.forEach(function (s, i) { h += '<div class="wpoa-user-chip">' + escHtml(s.name) + ' <span class="wpoa-user-chip-x" data-i="' + i + '">&times;</span></div>'; });
            $chips.html(h);
            $chips.find('.wpoa-user-chip-x').on('click', function () { selected.splice($(this).data('i'), 1); render(); });
        }

        function showDD() {
            var kw = $inp.val().trim().toLowerCase();
            var selIds = selected.map(function (s) { return s.id; });
            var all = selIds.concat(excluded);
            var f = users.filter(function (u) {
                if (all.indexOf(u.id) !== -1) return false;
                if (!kw) return true;
                return (u.name && u.name.toLowerCase().indexOf(kw) !== -1) || (u.email && u.email.toLowerCase().indexOf(kw) !== -1);
            }).slice(0, 20);
            $dd.empty();
            if (!f.length) { $dd.html('<div style="padding:10px;font-size:12px;color:var(--text-tertiary);font-family:var(--font);">کاربری یافت نشد</div>'); }
            else { f.forEach(function (u) {
                var $it = $('<div class="wpoa-user-picker-item"><span>' + escHtml(u.name) + '</span><small>' + escHtml(u.email) + '</small></div>');
                $it.on('click', function () { selected.push({ id: u.id, name: u.name }); render(); $inp.val(''); showDD(); });
                $dd.append($it);
            }); }
            $dd.show();
        }

        $inp.on('focus input', showDD);
        $(document).on('click.mp_' + prefix, function (e) { if (!$(e.target).closest('#' + prefix + '-search, #' + prefix + '-dd').length) $dd.hide(); });
        window['_getSel_' + prefix] = function () { return selected.map(function (s) { return s.id; }); };
    }

    /* ══════════════════════════════════
     * INIT
     * ══════════════════════════════════ */
    function initOrg() {
        if (!$('#wpoa-org-page').length) return;
        $('.wpoa-tab-glass').on('click', function () {
            var t = $(this).data('tab');
            $('.wpoa-tab-glass').removeClass('active').filter('[data-tab="' + t + '"]').addClass('active');
            $('.wpoa-org-tab').hide(); $('#wpoa-tab-' + t).show();
        });
        $(document).on('click', '.wpoa-view-btn', function () { _orgV = $(this).data('v'); $('.wpoa-view-btn').removeClass('active'); $(this).addClass('active'); drawChart(); });
        $('#wpoa-btn-new-pos').on('click', function () { mNew(null); });
        drawChart();
        drawUL();
    }

    /* ══════════════════════════════════
     * DRAW CHART
     * ══════════════════════════════════ */
    function drawChart() {
        var $b = $('#wpoa-chart-box'), tree = window.WPOA_TREE || [];
        if (!tree.length) {
            $b.html('<div style="text-align:center;padding:50px 20px;"><div class="wpoa-chart-add-root" id="wpoa-root-btn"><span class="dashicons dashicons-plus-alt2"></span><span>ایجاد اولین موقعیت سازمانی</span></div></div>');
            $('#wpoa-root-btn').on('click', function () { mNew(null); });
            return;
        }
        if (_orgV === 'tree') $b.html('<div class="wpoa-chart-container"><div class="wpoa-chart-tree"><ul>' + bTree(tree, 0) + '</ul></div></div>');
        else $b.html(bList(tree, 0));
        bindN($b);
    }

    function bTree(nodes, lvl) {
        var lv = Math.min(lvl, 5), h = '';
        nodes.forEach(function (n) {
            h += '<li data-level="' + lv + '">';
            h += '<div class="wpoa-chart-node" data-level="' + lv + '" data-nid="' + n.id + '">';
            h += '<div class="wpoa-chart-position">' + escHtml(n.name) + '</div>';
            if (n.users && n.users.length) {
                h += '<div class="wpoa-node-users">';
                n.users.slice(0, 4).forEach(function (u) {
                    h += '<div class="wpoa-node-user-row"><div class="wpoa-node-user-avatar">';
                    if (u.avatar_url) h += '<img src="' + escHtml(u.avatar_url) + '">';
                    else h += '<span class="dashicons dashicons-admin-users"></span>';
                    h += '</div><span class="wpoa-node-user-name">' + escHtml(u.display_name) + '</span></div>';
                });
                if (n.users.length > 4) h += '<div class="wpoa-node-more">+' + (n.users.length - 4) + ' نفر دیگر</div>';
                h += '</div>';
            } else { h += '<div class="wpoa-chart-empty">بدون کاربر</div>'; }
            h += nAct(n);
            h += '</div>';
            if (n.children && n.children.length) h += '<ul>' + bTree(n.children, lvl + 1) + '</ul>';
            h += '</li>';
        });
        return h;
    }

    function bList(nodes, lvl) {
        var c = ['var(--lvl0)','var(--lvl1)','var(--lvl2)','var(--lvl3)','var(--lvl4)','var(--lvl5)'];
        var lv = Math.min(lvl, 5), h = '';
        nodes.forEach(function (n) {
            h += '<div class="wpoa-org-node" data-nid="' + n.id + '" style="border-right:3px solid ' + c[lv] + ';">';
            h += '<div style="display:flex;align-items:center;gap:12px;flex:1;"><div>';
            h += '<strong style="color:' + c[lv] + ';">' + escHtml(n.name) + '</strong>';
            if (n.users && n.users.length) h += '<div style="font-size:11px;color:var(--text-secondary);">' + n.users.map(function (u) { return escHtml(u.display_name); }).join('، ') + '</div>';
            h += '</div></div>' + nAct(n) + '</div>';
            if (n.children && n.children.length) h += '<div class="wpoa-org-children">' + bList(n.children, lvl + 1) + '</div>';
        });
        return h;
    }

    function nAct(n) {
        return '<div class="wpoa-chart-actions">' +
            '<button class="wpoa-chart-action-btn wpoa-act-add wpoa-n-a" data-nid="' + n.id + '" title="زیرمجموعه"><span class="dashicons dashicons-plus"></span></button>' +
            '<button class="wpoa-chart-action-btn wpoa-act-edit wpoa-n-e" data-nid="' + n.id + '" data-nn="' + escHtml(n.name) + '" data-nd="' + escHtml(n.desc || '') + '" title="ویرایش"><span class="dashicons dashicons-edit"></span></button>' +
            '<button class="wpoa-chart-action-btn wpoa-act-del wpoa-n-d" data-nid="' + n.id + '" title="حذف"><span class="dashicons dashicons-trash"></span></button>' +
            '</div>';
    }

    function bindN($b) {
        $b.find('.wpoa-n-a').off('click').on('click', function (e) { e.stopPropagation(); mNew($(this).data('nid')); });
        $b.find('.wpoa-n-e').off('click').on('click', function (e) { e.stopPropagation(); mEditPos($(this).data('nid'), $(this).data('nn'), $(this).data('nd')); });
        $b.find('.wpoa-n-d').off('click').on('click', function (e) {
            e.stopPropagation();
            if (!confirm('حذف این موقعیت و تمامی زیرمجموعه‌ها؟')) return;
            var nid = $(this).data('nid');
            _p('wpoa_delete_org_unit', { unit_id: nid }, function () {
                _removeNodeFromTree(window.WPOA_TREE, nid);
                showNotice('حذف شد.');
                _refresh();
            });
        });
        $b.find('.wpoa-chart-node, .wpoa-org-node').off('click').on('click', function () { mManageUsers($(this).data('nid')); });
    }

    /* ══════════════════════════════════
     * MODAL: NEW POSITION
     * ══════════════════════════════════ */
    function mNew(parentId) {
        var h = _fi('عنوان موقعیت *', _inp('mn-name', 'مثلاً: مدیرعامل'));
        h += _fi('توضیحات', _ta('mn-desc'));
        if (!parentId) h += _fi('واحد بالادستی', _parentSel('mn-par'));
        h += '<div style="border-top:1px solid var(--divider);margin:16px 0;padding-top:14px;">';
        h += '<p style="font-size:12px;font-weight:600;color:var(--text-secondary);margin:0 0 12px;font-family:var(--font);">اختصاص کاربران (اختیاری)</p>';
        h += _fi('کاربران', _multiPicker('mn-u'));
        h += '</div>';

        openModal(parentId ? 'افزودن زیرمجموعه' : 'موقعیت جدید', h, function () {
            var name = _val('mn-name');
            if (!name) { showNotice('عنوان الزامی است.', 'error'); return; }
            var pid = parentId || parseInt(_val('mn-par')) || null;

            _p('wpoa_create_org_unit', { name: name, parent_id: pid || '', description: _val('mn-desc') },
                function (res) {
                    var nid = res.unit_id || res.id || null;
                    var newNode = { id: nid, name: name, desc: _val('mn-desc'), users: [], children: [] };
                    _addNodeToTree(window.WPOA_TREE, pid, newNode);

                    var uids = window['_getSel_mn-u'] ? window['_getSel_mn-u']() : [];
                    if (uids.length && nid) {
                        _p('wpoa_assign_user', { user_ids: JSON.stringify(uids), org_unit_id: nid },
                            function (r) {
                                uids.forEach(function (uid) {
                                    var wp = _wpUser(uid);
                                    if (wp) {
                                        _removeUserFromAllNodes(window.WPOA_TREE, uid);
                                        _addUserToNode(window.WPOA_TREE, nid, { user_id: uid, display_name: wp.name, email: wp.email, avatar_url: wp.avatar || '', role_name: '' });
                                    }
                                });
                                showNotice(r.message || 'ایجاد و اختصاص انجام شد.');
                                closeModal(); _refresh();
                            },
                            function (m) { showNotice('ایجاد شد. ' + m, 'error'); closeModal(); _refresh(); });
                    } else { showNotice('موقعیت ایجاد شد.'); closeModal(); _refresh(); }
                });
        });
        _bindMulti('mn-u');
    }

    /* ══════════════════════════════════
     * MODAL: EDIT POSITION
     * ══════════════════════════════════ */
    function mEditPos(id, name, desc) {
        var h = _fi('عنوان موقعیت *', _inp('me-name', '', name));
        h += _fi('توضیحات', _ta('me-desc', desc));
        openModal('ویرایش موقعیت', h, function () {
            var n = _val('me-name');
            if (!n) { showNotice('عنوان الزامی است.', 'error'); return; }
            _p('wpoa_update_org_unit', { unit_id: id, name: n, description: _val('me-desc') },
                function () {
                    _updateNodeInTree(window.WPOA_TREE, id, n, _val('me-desc'));
                    showNotice('ذخیره شد.');
                    closeModal(); _refresh();
                },
                function (m) { showNotice(m, 'error'); });
        });
    }

    /* ══════════════════════════════════
     * MODAL: MANAGE USERS
     * ══════════════════════════════════ */
    function mManageUsers(nodeId) {
        var nd = _fnd(window.WPOA_TREE || [], nodeId);
        if (!nd) return;

        var h = '<div style="margin-bottom:20px;">';
        h += '<p style="font-size:13px;font-weight:600;color:var(--text-primary);margin:0 0 12px;font-family:var(--font);">کاربران فعلی</p>';
        if (!nd.users || !nd.users.length) {
            h += '<p style="text-align:center;padding:14px;color:var(--text-tertiary);font-family:var(--font);background:rgba(0,0,0,0.02);border-radius:8px;">بدون کاربر</p>';
        } else {
            nd.users.forEach(function (u) {
                h += '<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--divider);">';
                h += '<div style="width:38px;height:38px;border-radius:50%;overflow:hidden;border:2px solid var(--glass-border);flex-shrink:0;background:var(--accent-light);display:flex;align-items:center;justify-content:center;">';
                if (u.avatar_url) h += '<img src="' + escHtml(u.avatar_url) + '" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
                else h += '<span class="dashicons dashicons-admin-users" style="color:var(--accent);"></span>';
                h += '</div><div style="flex:1;"><strong style="font-family:var(--font);font-size:13px;">' + escHtml(u.display_name) + '</strong>';
                h += '<div style="font-size:11px;color:var(--text-tertiary);">' + escHtml(u.email) + '</div></div>';
                h += '<button class="wpoa-remove-user-btn wpoa-mu-del" data-uid="' + u.user_id + '" data-unit="' + nodeId + '"><span class="dashicons dashicons-no-alt"></span></button>';
                h += '</div>';
            });
        }
        h += '</div>';

        h += '<div style="border-top:1px solid var(--divider);padding-top:16px;">';
        h += '<p style="font-size:13px;font-weight:600;color:var(--text-primary);margin:0 0 12px;font-family:var(--font);">افزودن کاربر جدید</p>';
        h += _multiPicker('mu-add');
        h += '<button class="wpoa-btn wpoa-btn-glow wpoa-btn-sm" id="wpoa-mu-assign" style="margin-top:12px;width:100%;padding:10px;">';
        h += '<span class="dashicons dashicons-plus-alt2"></span> اختصاص کاربران انتخابی</button>';
        h += '</div>';

        openModal('مدیریت کاربران: ' + escHtml(nd.name), h, null);
        setTimeout(function () { $('#wpoa-modal-confirm, #wpoa-modal-cancel').attr('style', 'display:none!important'); }, 50);

        var existingIds = (nd.users || []).map(function (u) { return u.user_id; });
        _bindMulti('mu-add', existingIds);

        // Remove
        $('#wpoa-modal-body').find('.wpoa-mu-del').on('click', function () {
            var $btn = $(this), uid = $btn.data('uid'), unit = $btn.data('unit');
            if (!confirm('حذف این کاربر از موقعیت؟')) return;
            $btn.prop('disabled', true).css('opacity', 0.5);
            _p('wpoa_remove_assignment', { user_id: uid, org_unit_id: unit },
                function () {
                    _removeUserFromAllNodes(window.WPOA_TREE, uid);
                    showNotice('کاربر حذف شد.');
                    closeModal(); _refresh();
                },
                function (m) { showNotice(m, 'error'); $btn.prop('disabled', false).css('opacity', 1); });
        });

        // Add
        $('#wpoa-mu-assign').on('click', function () {
            var uids = window['_getSel_mu-add'] ? window['_getSel_mu-add']() : [];
            if (!uids.length) { showNotice('کاربری انتخاب نشده.', 'error'); return; }
            var $btn = $(this);
            $btn.prop('disabled', true).css('opacity', 0.5);
            _p('wpoa_assign_user', { user_ids: JSON.stringify(uids), org_unit_id: nodeId },
                function (r) {
                    uids.forEach(function (uid) {
                        var wp = _wpUser(uid);
                        if (wp) {
                            _removeUserFromAllNodes(window.WPOA_TREE, uid);
                            _addUserToNode(window.WPOA_TREE, nodeId, { user_id: uid, display_name: wp.name, email: wp.email, avatar_url: wp.avatar || '', role_name: '' });
                        }
                    });
                    showNotice(r.message || 'اختصاص انجام شد.');
                    closeModal(); _refresh();
                },
                function (m) { showNotice(m, 'error'); $btn.prop('disabled', false).css('opacity', 1); });
        });
    }

    function _fnd(tree, id) {
        for (var i = 0; i < tree.length; i++) {
            if (tree[i].id === id) return tree[i];
            if (tree[i].children) { var f = _fnd(tree[i].children, id); if (f) return f; }
        }
        return null;
    }

    /* ══════════════════════════════════
     * USERS TAB (all users + search + edit)
     * ══════════════════════════════════ */
    function drawUL() {
        var $b = $('#wpoa-users-box');
        var allUsers  = window.WPOA_WP_USERS || [];
        var assigned  = window.WPOA_ASSIGNED || [];
        var positions = window.WPOA_POSITIONS || [];

        // Build assignment map
        var assignMap = {};
        assigned.forEach(function (a) { assignMap[a.user_id] = a; });

        // Merge all users
        var merged = allUsers.map(function (u) {
            var a = assignMap[u.id];
            return {
                user_id: u.id,
                name:    u.name,
                email:   u.email,
                avatar:  u.avatar || (a ? a.avatar : ''),
                unit:    a ? a.unit : '',
                unit_id: a ? a.unit_id : 0,
            };
        });

        // Sort: assigned first, then by name
        merged.sort(function (a, b) {
            if (a.unit && !b.unit) return -1;
            if (!a.unit && b.unit) return 1;
            return (a.name || '').localeCompare(b.name || '');
        });

        // Search bar
        var top = '<div class="wpoa-users-search">';
        top += '<div class="wpoa-users-search-wrap"><span class="dashicons dashicons-search"></span>';
        top += '<input type="text" id="wpoa-ul-search" placeholder="جستجوی نام کاربر..."></div>';
        top += '<span class="wpoa-users-count" id="wpoa-ul-count">' + merged.length + ' کاربر</span>';
        top += '</div>';

        if (!merged.length) {
            $b.html(top + '<div class="wpoa-empty-state"><p>کاربری یافت نشد.</p></div>');
            return;
        }

        // Table
        var h = '<table class="wpoa-table-glass" id="wpoa-ul-table"><thead><tr>';
        h += '<th style="width:50px;"></th>';
        h += '<th>نام کاربر</th>';
        h += '<th>ایمیل</th>';
        h += '<th>موقعیت سازمانی</th>';
        h += '<th style="width:60px;"></th>';
        h += '</tr></thead><tbody>';

        merged.forEach(function (u) {
            h += '<tr class="wpoa-ul-row" data-name="' + escHtml(u.name).toLowerCase() + '" data-email="' + escHtml(u.email).toLowerCase() + '">';

            // Avatar
            h += '<td style="text-align:center;"><div class="wpoa-user-row-avatar" style="margin:0 auto;">';
            if (u.avatar) h += '<img src="' + escHtml(u.avatar) + '">';
            else h += '<span class="dashicons dashicons-admin-users"></span>';
            h += '</div></td>';

            // Name
            h += '<td><strong style="font-family:var(--font);font-size:13px;">' + escHtml(u.name) + '</strong></td>';

            // Email
            h += '<td style="color:var(--text-secondary);font-size:12px;text-align:right;">' + escHtml(u.email) + '</td>';

            // Position
            if (u.unit) {
                h += '<td style="font-family:var(--font);">' + escHtml(u.unit) + '</td>';
            } else {
                h += '<td style="font-family:var(--font);color:var(--text-tertiary);font-style:italic;font-size:12px;">بدون موقعیت</td>';
            }

            // Edit button
            h += '<td style="text-align:center;">';
            h += '<button class="wpoa-chart-action-btn wpoa-act-edit wpoa-ul-edit" data-uid="' + u.user_id + '" data-uname="' + escHtml(u.name) + '" data-unit="' + u.unit_id + '" title="ویرایش موقعیت">';
            h += '<span class="dashicons dashicons-edit"></span></button></td>';

            h += '</tr>';
        });

        h += '</tbody></table>';
        $b.html(top + h);

        // Search
        $('#wpoa-ul-search').on('input', function () {
            var kw = $(this).val().trim().toLowerCase();
            var shown = 0;
            $('.wpoa-ul-row').each(function () {
                var match = !kw || ($(this).data('name') || '').indexOf(kw) !== -1 || ($(this).data('email') || '').indexOf(kw) !== -1;
                $(this).toggle(match);
                if (match) shown++;
            });
            $('#wpoa-ul-count').text(shown + ' کاربر');
        });

        // Edit position
        $('.wpoa-ul-edit').on('click', function () {
            var uid     = $(this).data('uid');
            var uname   = $(this).data('uname');
            var curUnit = $(this).data('unit');

            var posOpts = '<option value="">— بدون موقعیت —</option>';
            positions.forEach(function (p) {
                posOpts += '<option value="' + p.id + '"' + (p.id === curUnit ? ' selected' : '') + '>' + escHtml(p.name) + '</option>';
            });

            var mh = '<p style="font-size:13px;font-family:var(--font);color:var(--text-secondary);margin:0 0 16px;">تغییر موقعیت سازمانی برای <strong>' + escHtml(uname) + '</strong></p>';
            mh += _fi('موقعیت جدید', '<select id="wpoa-ue-pos" style="width:100%;padding:10px 14px;border:1.5px solid rgba(200,210,230,0.5);border-radius:8px;font-family:var(--font);font-size:13px;">' + posOpts + '</select>');

            openModal('ویرایش موقعیت', mh, function () {
                var newUnit = parseInt($('#wpoa-ue-pos').val());

                if (!newUnit && curUnit) {
                    // Remove
                    _p('wpoa_remove_assignment', { user_id: uid, org_unit_id: curUnit },
                        function () {
                            _removeUserFromAllNodes(window.WPOA_TREE, uid);
                            showNotice('کاربر از موقعیت حذف شد.');
                            closeModal(); _refresh();
                        },
                        function (m) { showNotice(m, 'error'); });
                } else if (newUnit && newUnit !== curUnit) {
                    // Assign
                    _p('wpoa_assign_user', { user_ids: JSON.stringify([uid]), org_unit_id: newUnit },
                        function (r) {
                            var wp = _wpUser(uid);
                            _removeUserFromAllNodes(window.WPOA_TREE, uid);
                            if (wp) _addUserToNode(window.WPOA_TREE, newUnit, { user_id: uid, display_name: wp.name, email: wp.email, avatar_url: wp.avatar || '', role_name: '' });
                            showNotice(r.message || 'موقعیت تغییر کرد.');
                            closeModal(); _refresh();
                        },
                        function (m) { showNotice(m, 'error'); });
                } else {
                    closeModal();
                }
            });
        });
    }
    /* ================================================
     * PERMISSIONS MODAL
     * ================================================ */

    function openPermissionsModal(roleId, roleName) {
        openModal('مجوزهای نقش: ' + escHtml(roleName),
            '<div class="wpoa-loading-glass"><div class="wpoa-spinner-glass"></div></div>',
            function () {
                var perms = {};
                $('#wpoa-modal-body input[type="checkbox"]').each(function () {
                    perms[$(this).data('perm')] = $(this).is(':checked') ? '1' : '0';
                });
                ajax('wpoa_save_role_perms', { role_id: roleId, permissions: JSON.stringify(perms) },
                    function (r) { showNotice(r.message); closeModal(); });
            }
        );

        ajax('wpoa_get_role_perms', { role_id: roleId }, function (d) {
            var perms = d.permissions || {};
            var h = '<div class="wpoa-perms-grid">';
            Object.keys(perms).forEach(function (key) {
                var p = perms[key];
                h += '<label class="wpoa-perm-item">';
                h += '<input type="checkbox" data-perm="' + escHtml(key) + '"' + (p.granted ? ' checked' : '') + '>';
                h += ' ' + escHtml(p.label);
                h += '</label>';
            });
            h += '</div>';
            $('#wpoa-modal-body').html(h);
        });
    }

    /* ================================================
     * SETTINGS
     * ================================================ */

        function initSettings() {
        if (!$('#wpoa-settings-page').length) return;

        ajax('wpoa_get_settings', {}, function (d) {
            var s = d.settings || {};
            var h = '';

            h += '<div class="wpoa-section-glass"><div class="wpoa-section-head">';
            h += '<span class="wpoa-section-icon wpoa-icon-blue"><span class="dashicons dashicons-admin-settings"></span></span>';
            h += '<h3>تنظیمات عمومی</h3></div>';
            h += '<div class="wpoa-profile-fields">';
            h += '<div class="wpoa-pf-field"><label>نام سازمان</label><div class="wpoa-input-icon-wrap"><span class="dashicons dashicons-building"></span>';
            h += '<input type="text" id="wpoa-s-org" value="' + escHtml(s.org_name || '') + '"></div></div>';
            h += '<div class="wpoa-pf-field"><label>تعداد نامه در هر صفحه</label><div class="wpoa-input-icon-wrap"><span class="dashicons dashicons-editor-ol"></span>';
            h += '<input type="number" id="wpoa-s-perpage" value="' + escHtml(s.messages_per_page || '20') + '" min="5" max="100" style="max-width:160px;"></div></div>';
            h += '</div></div>';

            h += '<div class="wpoa-section-glass"><div class="wpoa-section-head">';
            h += '<span class="wpoa-section-icon wpoa-icon-orange"><span class="dashicons dashicons-paperclip"></span></span>';
            h += '<h3>پیوست‌ها</h3></div>';
            h += '<div class="wpoa-profile-fields">';
            h += '<div class="wpoa-pf-field"><label>حداکثر حجم فایل (MB)</label><div class="wpoa-input-icon-wrap"><span class="dashicons dashicons-upload"></span>';
            h += '<input type="number" id="wpoa-s-maxsize" value="' + escHtml(s.max_attachment_size_mb || '10') + '" min="1" max="100" style="max-width:160px;"></div></div>';
            h += '<div class="wpoa-pf-field"><label>فرمت‌های مجاز</label><div class="wpoa-input-icon-wrap"><span class="dashicons dashicons-media-default"></span>';
            h += '<input type="text" id="wpoa-s-types" value="' + escHtml(s.allowed_attachment_types || '') + '" dir="ltr"></div></div>';
            h += '</div></div>';

            h += '<div class="wpoa-section-glass"><div class="wpoa-section-head">';
            h += '<span class="wpoa-section-icon wpoa-icon-green"><span class="dashicons dashicons-email"></span></span>';
            h += '<h3>اعلان ایمیلی</h3></div>';
            h += '<label class="wpoa-toggle-label"><input type="checkbox" id="wpoa-s-email"' + (s.email_notifications_enabled === '1' ? ' checked' : '') + '> فعال‌سازی اعلان ایمیلی</label></div>';

            h += '<div class="wpoa-section-glass"><div class="wpoa-section-head">';
            h += '<span class="wpoa-section-icon wpoa-icon-purple"><span class="dashicons dashicons-smartphone"></span></span>';
            h += '<h3>اعلان پیامکی</h3></div>';
            h += '<div class="wpoa-profile-fields">';
            h += '<label class="wpoa-toggle-label"><input type="checkbox" id="wpoa-s-sms"' + (s.sms_notifications_enabled === '1' ? ' checked' : '') + '> فعال‌سازی اعلان پیامکی</label>';
            h += '<div class="wpoa-pf-field"><label>ارائه‌دهنده</label><div class="wpoa-input-icon-wrap"><span class="dashicons dashicons-cloud"></span>';
            h += '<input type="text" id="wpoa-s-sms-prov" value="' + escHtml(s.sms_api_provider || '') + '" dir="ltr"></div></div>';
            h += '<div class="wpoa-pf-field"><label>کلید API</label><div class="wpoa-input-icon-wrap"><span class="dashicons dashicons-admin-network"></span>';
            h += '<input type="text" id="wpoa-s-sms-key" value="' + escHtml(s.sms_api_key || '') + '" dir="ltr"></div></div>';
            h += '<div class="wpoa-pf-field"><label>شماره ارسال‌کننده</label><div class="wpoa-input-icon-wrap"><span class="dashicons dashicons-phone"></span>';
            h += '<input type="text" id="wpoa-s-sms-sender" value="' + escHtml(s.sms_sender_number || '') + '" dir="ltr"></div></div>';
            h += '</div></div>';

            h += '<button class="wpoa-btn wpoa-btn-glow" id="wpoa-settings-save" style="padding:13px 28px;">';
            h += '<span class="dashicons dashicons-saved"></span> ذخیره تنظیمات</button>';

            $('#wpoa-settings-form').html(h);

            $('#wpoa-settings-save').on('click', function () {
                var $btn = $(this);
                $btn.prop('disabled', true).css('opacity', 0.7);
                ajax('wpoa_save_settings', {
                    org_name: $('#wpoa-s-org').val(), messages_per_page: $('#wpoa-s-perpage').val(),
                    max_attachment_size_mb: $('#wpoa-s-maxsize').val(), allowed_attachment_types: $('#wpoa-s-types').val(),
                    email_notifications_enabled: $('#wpoa-s-email').is(':checked') ? '1' : '0',
                    sms_notifications_enabled: $('#wpoa-s-sms').is(':checked') ? '1' : '0',
                    sms_api_provider: $('#wpoa-s-sms-prov').val(), sms_api_key: $('#wpoa-s-sms-key').val(),
                    sms_sender_number: $('#wpoa-s-sms-sender').val()
                }, function (r) {
                    showNotice(r.message || 'تنظیمات ذخیره شد.');
                    $btn.prop('disabled', false).css('opacity', 1);
                });
            });
        });
    }

    /* ================================================
     * ACTIVITY LOG
     * ================================================ */

    function initActivityLog() {
        if (!$('#wpoa-activity-page').length) return;

        var actionLabels = {
            'message_sent': 'ارسال نامه', 'message_read': 'مشاهده نامه',
            'message_replied': 'پاسخ به نامه', 'message_forwarded': 'ارسال مجدد',
            'message_deleted': 'حذف نامه', 'message_restored': 'بازیابی نامه',
            'message_print': 'چاپ نامه', 'draft_saved': 'ذخیره پیش‌نویس',
            'profile_updated': 'بروزرسانی پروفایل', 'password_changed': 'تغییر رمز',
            'settings_saved': 'ذخیره تنظیمات', 'referral_created': 'ایجاد ارجاع',
            'referral_responded': 'پاسخ ارجاع', 'margin_note_added': 'حاشیه‌نویسی',
            'permissions_updated': 'بروزرسانی مجوزها'
        };

        var $sel = $('#wpoa-act-action');
        Object.keys(actionLabels).forEach(function (k) {
            $sel.append('<option value="' + k + '">' + escHtml(actionLabels[k]) + '</option>');
        });

        function loadLog(page) {
            page = page || 1;
            var $list = $('#wpoa-activity-list');
            $list.html('<div class="wpoa-loading-glass"><div class="wpoa-spinner-glass"></div></div>');

            ajax('wpoa_get_activity_log', {
                action_filter: $('#wpoa-act-action').val(),
                date_from:     $('#wpoa-act-date-from').val(),
                date_to:       $('#wpoa-act-date-to').val(),
                page:          page
            }, function (d) {
                var logs = d.logs || [];
                if (!logs.length) {
                    $list.html('<div class="wpoa-empty-state"><p>فعالیتی یافت نشد.</p></div>');
                    $('#wpoa-act-pagination').html('');
                    return;
                }

                var h = '<table class="wpoa-table-glass"><thead><tr><th>کاربر</th><th>عملیات</th><th>جزئیات</th><th>IP</th><th>زمان</th></tr></thead><tbody>';
                logs.forEach(function (l) {
                    h += '<tr>';
                    h += '<td style="white-space:nowrap;">';
                    if (l.avatar_url) h += '<img src="' + escHtml(l.avatar_url) + '" class="wpoa-avatar-xs" style="vertical-align:middle;margin-left:6px;">';
                    h += escHtml(l.user_name) + '</td>';
                    h += '<td><span class="wpoa-priority-badge wpoa-priority-normal" style="font-size:11px;">' + escHtml(l.action_label) + '</span></td>';
                    h += '<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;color:var(--text-secondary);">' + escHtml(l.details || '—') + '</td>';
                    h += '<td dir="ltr" style="font-size:11px;color:var(--text-tertiary);">' + escHtml(l.ip_address || '') + '</td>';
                    h += '<td dir="ltr" style="font-size:11px;color:var(--text-tertiary);white-space:nowrap;">' + escHtml(l.created_at || '') + '</td>';
                    h += '</tr>';
                });
                h += '</tbody></table>';
                $list.html(h);

                var tp = d.total_pages || 1;
                var $pag = $('#wpoa-act-pagination');
                if (tp > 1) {
                    var ph = '';
                    if (page > 1) ph += '<button class="wpoa-page-btn wpoa-act-p" data-p="' + (page - 1) + '">قبلی</button>';
                    for (var i = 1; i <= tp; i++) {
                        if (i === page) ph += '<span style="padding:8px 12px;font-weight:700;color:var(--accent);">' + i + '</span>';
                        else ph += '<button class="wpoa-page-btn wpoa-act-p" data-p="' + i + '">' + i + '</button>';
                    }
                    if (page < tp) ph += '<button class="wpoa-page-btn wpoa-act-p" data-p="' + (page + 1) + '">بعدی</button>';
                    $pag.html(ph);
                    $pag.find('.wpoa-act-p').on('click', function () { loadLog($(this).data('p')); });
                } else {
                    $pag.html('');
                }
            });
        }

        $('#wpoa-act-filter-btn').on('click', function () { loadLog(1); });
        loadLog(1);
    }

    /* ================================================
     * INITIALIZATION
     * ================================================ */

    $(document).ready(function () {

        // ── INBOX / REFERRAL PAGES ──
        if ($('#wpoa-inbox-page').length) {
            var initialFolder = $('#wpoa-inbox-page').data('initial-folder') || 'inbox';

            // Folder tab clicks
            $(document).on('click', '.wpoa-folder-tab', function () {
                var folder = $(this).data('folder');
                $('.wpoa-folder-tab').removeClass('active');
                $(this).addClass('active');
                State.currentFolder = folder;
                State.currentPage = 1;
                loadFolder(folder);
            });

            // Search
            $('#wpoa-search-btn, .wpoa-search-glass .dashicons').on('click', function () {
                doSearch($('#wpoa-search-input').val());
            });
            $('#wpoa-search-input').on('keypress', function (e) {
                if (e.which === 13) doSearch($(this).val());
            });

            // Batch
            $('#wpoa-select-all').on('change', function () {
                $('.wpoa-msg-checkbox').prop('checked', $(this).is(':checked'));
            });

            $('#wpoa-batch-apply').on('click', function () {
                var action = $('#wpoa-batch-select').val();
                if (!action) return;
                var ids = [];
                $('.wpoa-msg-checkbox:checked').each(function () { ids.push($(this).data('id')); });
                if (!ids.length) { showNotice('نامه‌ای انتخاب نشده.', 'error'); return; }
                ajax('wpoa_batch_action', { message_ids: JSON.stringify(ids), batch_action: action },
                    function (r) { showNotice(r.message); loadFolder(State.currentFolder, State.currentPage); });
            });

            // Load initial content
            if (initialFolder === 'referrals') {
                loadReferralQueue(1);
            } else if (initialFolder === 'referrals-sent') {
                loadReferralSent(1);
            } else {
                State.currentFolder = initialFolder;
                loadFolder(initialFolder);
            }
        }

        // ── COMPOSE ──
        initCompose();

        // ── PROFILE ──
        initProfile();

        // ── ORG ──
        initOrg();

        // ── SETTINGS ──
        initSettings();

        // ── ACTIVITY LOG ──
        initActivityLog();
    });

})(jQuery);