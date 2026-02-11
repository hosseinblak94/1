<?php
defined('ABSPATH') || exit;
$user_id      = get_current_user_id();
$msg_user     = new WPOA_Message_User_Model();
$unread_count = $msg_user->count_unread($user_id);
$recent       = $msg_user->get_folder($user_id, 'inbox', 1, 5);
?>
<div style="font-family:'Vazir',Tahoma,sans-serif; direction:rtl; text-align:right;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; padding:12px 16px; background:linear-gradient(135deg, rgba(0,122,255,0.08), rgba(88,86,214,0.05)); border-radius:12px;">
        <span style="font-size:14px; color:#1C1C1E;">
            <strong style="font-size:20px; color:#007AFF;"><?php echo (int) $unread_count; ?></strong>
            <span style="color:#636366;"> نامه خوانده‌نشده</span>
        </span>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpoa-compose')); ?>"
           style="display:inline-flex; align-items:center; gap:4px; padding:8px 16px; background:linear-gradient(135deg,#007AFF,#3F8FFF); color:#fff; text-decoration:none; border-radius:8px; font-size:12px; font-family:'Vazir',Tahoma,sans-serif; box-shadow:0 4px 16px rgba(0,122,255,0.3);">
            نامه جدید
        </a>
    </div>

    <?php if (empty($recent)) : ?>
        <p style="color:#AEAEB2; text-align:center; padding:20px 0;">نامه‌ای در صندوق دریافت نیست.</p>
    <?php else : ?>
        <?php foreach ($recent as $msg) :
            $is_unread = !(int) $msg->is_read;
            $bg        = $is_unread ? 'rgba(0,122,255,0.05)' : 'transparent';
            $weight    = $is_unread ? '600' : '400';
            $dot       = $is_unread ? '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#007AFF;margin-left:6px;"></span>' : '';
        ?>
        <div style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-radius:8px; margin-bottom:4px; background:<?php echo $bg; ?>; transition:background 0.15s;">
            <?php echo $dot; ?>
            <span style="min-width:80px; max-width:100px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:<?php echo $weight; ?>; color:#1C1C1E; font-size:12px;">
                <?php echo esc_html($msg->sender_display_name ?? ''); ?>
            </span>
            <a href="<?php echo esc_url(admin_url('admin.php?page=wpoa-inbox')); ?>"
               style="flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-decoration:none; color:#636366; font-size:12px; font-weight:<?php echo $weight; ?>;">
                <?php echo esc_html($msg->title ?: '(بدون عنوان)'); ?>
            </a>
            <span style="font-size:10px; color:#AEAEB2; white-space:nowrap; direction:ltr;">
                <?php echo esc_html($msg->sent_at_jalali ?? ''); ?>
            </span>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div style="text-align:center; margin-top:12px;">
        <a href="<?php echo esc_url(admin_url('admin.php?page=wpoa-inbox')); ?>"
           style="color:#007AFF; text-decoration:none; font-size:12px; font-weight:500;">
            مشاهده همه نامه‌ها &larr;
        </a>
    </div>
</div>