<?php
defined('ABSPATH') || exit;

class WPOA_Margin_Note_Model extends WPOA_Model
{
    protected string $table_suffix = 'margin_notes';

    public function add(
        int    $message_id,
        int    $user_id,
        string $note_text,
        bool   $is_private  = false,
        ?int   $referral_id = null
    ): int|false {
        $note_text = sanitize_textarea_field($note_text);

        if (empty(trim($note_text))) {
            return false;
        }

        return $this->insert([
            'message_id'  => absint($message_id),
            'user_id'     => absint($user_id),
            'referral_id' => $referral_id ? absint($referral_id) : null,
            'note_text'   => $note_text,
            'is_private'  => $is_private ? 1 : 0,
        ]);
    }

    public function get_for_message(int $message_id, int $viewer_user_id): array
    {
        $profiles = $this->other_table('user_profiles');

        return $this->query(
            "SELECT mn.*,
                    up.display_name AS author_display_name,
                    up.avatar_url   AS author_avatar_url
             FROM {$this->table()} mn
             LEFT JOIN {$profiles} up ON up.user_id = mn.user_id
             WHERE mn.message_id = %d
               AND (mn.is_private = 0 OR mn.user_id = %d)
             ORDER BY mn.created_at ASC",
            [$message_id, $viewer_user_id]
        );
    }

    public function remove(int $note_id, int $user_id): bool
    {
        $note = $this->find($note_id);

        if (!$note || (int) $note->user_id !== $user_id) {
            return false;
        }

        return $this->delete(['id' => $note_id]) !== false;
    }

    public function count_for_message(int $message_id): int
    {
        return $this->count_where('message_id = %d', [$message_id]);
    }
}