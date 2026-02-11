<?php
defined('ABSPATH') || exit;

class WPOA_Attachment_Model extends WPOA_Model
{
    protected string $table_suffix = 'attachments';

    public function add(int $message_id, int $user_id, array $file_data): int|false
    {
        return $this->insert([
            'message_id' => $message_id,
            'user_id'    => $user_id,
            'file_name'  => sanitize_file_name($file_data['file_name']),
            'file_url'   => esc_url_raw($file_data['file_url']),
            'file_path'  => sanitize_text_field($file_data['file_path'] ?? ''),
            'file_size'  => absint($file_data['file_size'] ?? 0),
            'file_type'  => sanitize_mime_type($file_data['file_type'] ?? ''),
        ]);
    }

    public function get_for_message(int $message_id): array
    {
        return $this->query(
            "SELECT * FROM {$this->table()} WHERE message_id = %d ORDER BY id ASC",
            [$message_id]
        );
    }

    public function remove(int $attachment_id, int $user_id): bool
    {
        $att = $this->find($attachment_id);

        if (!$att || (int) $att->user_id !== $user_id) {
            return false;
        }

        if (!empty($att->file_path) && file_exists($att->file_path)) {
            wp_delete_file($att->file_path);
        }

        return $this->delete(['id' => $attachment_id]) !== false;
    }

    public function validate_upload(array $file, int $max_mb, string $allowed_types): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'خطا در آپلود فایل.'];
        }

        $max_bytes = $max_mb * 1024 * 1024;
        if ($file['size'] > $max_bytes) {
            return ['valid' => false, 'error' => sprintf('حداکثر حجم فایل %d مگابایت است.', $max_mb)];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = array_map('trim', explode(',', strtolower($allowed_types)));

        if (!in_array($ext, $allowed, true)) {
            return ['valid' => false, 'error' => 'فرمت فایل مجاز نیست. فرمت‌های مجاز: ' . $allowed_types];
        }

        return ['valid' => true];
    }
}