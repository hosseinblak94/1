<?php defined('ABSPATH') || exit; ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>چاپ نامه — <?php echo esc_html($message->title); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(WPOA_PLUGIN_URL . 'assets/css/print.css'); ?>">
</head>
<body onload="window.print();">

<div class="print-page">
    <!-- Header -->
    <div class="print-header">
        <div class="print-org-name"><?php echo esc_html($org_name); ?></div>
        <div class="print-doc-info">
            <?php if (!empty($message->system_doc_number)) : ?>
                <div class="print-doc-row">
                    <span class="print-label">شماره نامه:</span>
                    <span class="print-value"><?php echo esc_html($message->system_doc_number); ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($message->internal_doc_number)) : ?>
                <div class="print-doc-row">
                    <span class="print-label">شماره داخلی:</span>
                    <span class="print-value"><?php echo esc_html($message->internal_doc_number); ?></span>
                </div>
            <?php endif; ?>
            <div class="print-doc-row">
                <span class="print-label">تاریخ:</span>
                <span class="print-value"><?php echo esc_html($message->sent_at_jalali ?? ''); ?></span>
            </div>
            <div class="print-doc-row">
                <span class="print-label">اولویت:</span>
                <span class="print-value"><?php
                    $priorities = ['low' => 'کم', 'normal' => 'عادی', 'important' => 'مهم', 'instant' => 'فوری'];
                    echo esc_html($priorities[$message->priority] ?? $message->priority);
                ?></span>
            </div>
        </div>
    </div>

    <!-- Addressing -->
    <div class="print-addressing">
        <div class="print-row">
            <span class="print-label">از:</span>
            <span class="print-value"><?php echo esc_html($message->sender_display_name ?? ''); ?></span>
        </div>
        <div class="print-row">
            <span class="print-label">به:</span>
            <span class="print-value">
                <?php
                $to_names = [];
                $cc_names = [];
                foreach ($recipients as $r) {
                    $name = esc_html($r->display_name ?? '');
                    if ($r->type === 'cc') {
                        $cc_names[] = $name;
                    } else {
                        $to_names[] = $name;
                    }
                }
                echo implode('، ', $to_names);
                ?>
            </span>
        </div>
        <?php if (!empty($cc_names)) : ?>
        <div class="print-row">
            <span class="print-label">رونوشت:</span>
            <span class="print-value"><?php echo implode('، ', $cc_names); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Subject -->
    <div class="print-subject">
        <span class="print-label">موضوع:</span>
        <span class="print-value"><?php echo esc_html($message->title); ?></span>
    </div>

    <!-- Tags -->
    <?php if (!empty($tags)) : ?>
    <div class="print-tags">
        <span class="print-label">برچسب‌ها:</span>
        <?php foreach ($tags as $t) : ?>
            <span class="print-tag"><?php echo esc_html($t->name); ?></span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Body -->
    <div class="print-body">
        <?php echo wp_kses_post($message->body); ?>
    </div>

    <!-- Signature -->
    <?php if ($message->signature_type !== 'none') : ?>
    <div class="print-signature">
        <?php if (($message->signature_type === 'text' || $message->signature_type === 'both')
                  && !empty($message->signature_text)) : ?>
            <div class="print-sig-text"><?php echo esc_html($message->signature_text); ?></div>
        <?php endif; ?>
        <?php if (($message->signature_type === 'image' || $message->signature_type === 'both')
                  && !empty($message->signature_image_url)) : ?>
            <img src="<?php echo esc_url($message->signature_image_url); ?>" class="print-sig-img" alt="امضا">
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Attachments -->
    <?php if (!empty($attachments)) : ?>
    <div class="print-attachments">
        <span class="print-label">پیوست‌ها:</span>
        <ul>
            <?php foreach ($attachments as $a) : ?>
                <li><?php echo esc_html($a->file_name); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="print-footer">
        <span>این نامه از سامانه اتوماسیون اداری <?php echo esc_html($org_name); ?> صادر شده است.</span>
    </div>
</div>

</body>
</html>