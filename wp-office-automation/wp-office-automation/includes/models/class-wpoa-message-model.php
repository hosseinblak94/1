<?php
defined('ABSPATH') || exit;

class WPOA_Message_Model extends WPOA_Model
{
    protected string $table_suffix = 'messages';

    public function create_draft(int $sender_id, array $data = []): int|false
    {
        return $this->insert([
            'sender_id'           => $sender_id,
            'title'               => sanitize_text_field($data['title'] ?? ''),
            'body'                => wp_kses_post($data['body'] ?? ''),
            'priority'            => $this->validate_enum(
                $data['priority'] ?? 'normal',
                ['low', 'normal', 'important', 'instant'],
                'normal'
            ),
            'status'              => 'draft',
            'internal_doc_number' => sanitize_text_field($data['internal_doc_number'] ?? ''),
            'signature_type'      => $this->validate_enum(
                $data['signature_type'] ?? 'none',
                ['none', 'text', 'image', 'both'],
                'none'
            ),
            'internal_note'       => sanitize_textarea_field($data['internal_note'] ?? ''),
        ]);
    }

    public function update_draft(int $message_id, array $data): bool
    {
        $update = [];

        if (isset($data['title']))               $update['title']               = sanitize_text_field($data['title']);
        if (isset($data['body']))                 $update['body']                = wp_kses_post($data['body']);
        if (isset($data['priority']))             $update['priority']            = $this->validate_enum($data['priority'], ['low','normal','important','instant'], 'normal');
        if (isset($data['internal_doc_number']))  $update['internal_doc_number'] = sanitize_text_field($data['internal_doc_number']);
        if (isset($data['signature_type']))       $update['signature_type']      = $this->validate_enum($data['signature_type'], ['none','text','image','both'], 'none');
        if (isset($data['internal_note']))        $update['internal_note']       = sanitize_textarea_field($data['internal_note']);

        if (empty($update)) {
            return true;
        }

        return $this->update($update, ['id' => $message_id]) !== false;
    }

    public function send(int $message_id, int $sender_id): ?string
    {
        $msg = $this->find($message_id);

        if (!$msg || (int) $msg->sender_id !== $sender_id || $msg->status !== 'draft') {
            return null;
        }

        $profile = new WPOA_User_Profile_Model();
        $p       = $profile->get_by_user_id($sender_id);

        $sig_text = '';
        $sig_img  = '';
        if ($msg->signature_type !== 'none' && $p) {
            $sig_text = $p->signature_text ?? '';
            $sig_img  = $p->signature_image_url ?? '';
        }

        $doc_number = WPOA_Jalali_Helper::generate_doc_number();
        $now        = current_time('mysql');
        $jalali     = WPOA_Jalali_Helper::now();

        $this->update(
            [
                'status'              => 'sent',
                'system_doc_number'   => $doc_number,
                'sent_at'             => $now,
                'sent_at_jalali'      => $jalali,
                'signature_text'      => $sig_text,
                'signature_image_url' => $sig_img,
            ],
            ['id' => $message_id]
        );

        return $doc_number;
    }

    public function get_by_id(int $id): ?object
    {
        return $this->find($id);
    }

    public function delete_draft(int $message_id, int $sender_id): bool
    {
        $msg = $this->find($message_id);

        if (!$msg || (int) $msg->sender_id !== $sender_id || $msg->status !== 'draft') {
            return false;
        }

        return $this->update(['status' => 'deleted'], ['id' => $message_id]) !== false;
    }

    public function set_thread(int $message_id, int $parent_id): void
    {
        $parent = $this->find($parent_id);

        if (!$parent) {
            return;
        }

        $thread_id = $parent->thread_id ?: $parent->id;

        $this->update(
            [
                'parent_id' => $parent_id,
                'thread_id' => $thread_id,
            ],
            ['id' => $message_id]
        );
    }

    public function get_thread(int $message_id): array
    {
        $msg = $this->find($message_id);
        if (!$msg) {
            return [];
        }

        $thread_id      = $msg->thread_id ?: $msg->id;
        $profiles_table = $this->other_table('user_profiles');

        return $this->query(
            "SELECT m.*,
                    up.display_name AS sender_display_name,
                    up.avatar_url   AS sender_avatar_url
             FROM {$this->table()} m
             LEFT JOIN {$profiles_table} up ON up.user_id = m.sender_id
             WHERE (m.thread_id = %d OR m.id = %d)
               AND m.status = 'sent'
             ORDER BY m.sent_at ASC",
            [$thread_id, $thread_id]
        );
    }

    public function count_thread(int $thread_id): int
    {
        return $this->count_where(
            '(thread_id = %d OR id = %d) AND status = %s',
            [$thread_id, $thread_id, 'sent']
        );
    }
}