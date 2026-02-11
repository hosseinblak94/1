<?php
defined('ABSPATH') || exit;

class WPOA_Recipient_Model extends WPOA_Model
{
    protected string $table_suffix = 'recipients';

    public function add(int $message_id, int $user_id, string $type = 'to', bool $email = false, bool $sms = false): int|false
    {
        return $this->insert([
            'message_id'   => $message_id,
            'user_id'      => $user_id,
            'type'         => $this->validate_enum($type, ['to', 'cc'], 'to'),
            'notify_email' => $email ? 1 : 0,
            'notify_sms'   => $sms ? 1 : 0,
        ]);
    }

    public function get_for_message(int $message_id): array
    {
        $profiles = $this->other_table('user_profiles');

        return $this->query(
            "SELECT r.*,
                    up.display_name,
                    up.avatar_url
             FROM {$this->table()} r
             LEFT JOIN {$profiles} up ON up.user_id = r.user_id
             WHERE r.message_id = %d
             ORDER BY r.type ASC, r.id ASC",
            [$message_id]
        );
    }

    public function delete_for_message(int $message_id): void
    {
        $this->delete(['message_id' => $message_id]);
    }
}