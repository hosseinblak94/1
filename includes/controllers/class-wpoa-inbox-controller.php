<?php
defined('ABSPATH') || exit;

class WPOA_Inbox_Controller
{
    private WPOA_Message_User_Model $msg_user;
    private WPOA_Message_Model      $message;

    public function __construct()
    {
        $this->msg_user = new WPOA_Message_User_Model();
        $this->message  = new WPOA_Message_Model();
    }

    public function get_folder(int $user_id, string $folder, int $page = 1): array
    {
        $per_page = (int) (new WPOA_Settings_Model())->get('messages_per_page', '20');

        if ($folder === 'starred') {
            $messages = $this->msg_user->get_starred($user_id, $page, $per_page);
            $total    = $this->msg_user->count_starred($user_id);
        } else {
            $messages = $this->msg_user->get_folder($user_id, $folder, $page, $per_page);
            $total    = $this->msg_user->count_folder($user_id, $folder);
        }

        return [
            'success'     => true,
            'messages'    => $this->format_list($messages),
            'total'       => $total,
            'total_pages' => (int) ceil($total / $per_page),
            'page'        => $page,
            'unread'      => $this->msg_user->count_unread($user_id),
        ];
    }

    public function search(int $user_id, string $keyword, int $page = 1): array
    {
        $results = $this->msg_user->search($user_id, $keyword, $page, 20);

        return [
            'success'  => true,
            'messages' => $this->format_list($results),
        ];
    }

    public function toggle_star(int $user_id, int $message_id): array
    {
        $this->msg_user->toggle_star($user_id, $message_id);
        return ['success' => true, 'message' => 'وضعیت ستاره تغییر کرد.'];
    }

    public function toggle_pin(int $user_id, int $message_id): array
    {
        $this->msg_user->toggle_pin($user_id, $message_id);
        return ['success' => true, 'message' => 'وضعیت سنجاق تغییر کرد.'];
    }

    public function archive(int $user_id, int $message_id): array
    {
        $this->msg_user->update_folder($user_id, $message_id, 'archive');
        return ['success' => true, 'message' => 'نامه بایگانی شد.'];
    }

    public function trash(int $user_id, int $message_id): array
    {
        $this->msg_user->update_folder($user_id, $message_id, 'trash');

        WPOA_Activity_Logger::log(
            WPOA_Activity_Model::ACTION_MESSAGE_DELETED,
            'message',
            $message_id
        );

        return ['success' => true, 'message' => 'نامه به سطل زباله منتقل شد.'];
    }

    public function restore(int $user_id, int $message_id): array
    {
        $this->msg_user->restore($user_id, $message_id);

        WPOA_Activity_Logger::log(
            WPOA_Activity_Model::ACTION_MESSAGE_RESTORED,
            'message',
            $message_id
        );

        return ['success' => true, 'message' => 'نامه بازیابی شد.'];
    }

    public function permanent_delete(int $user_id, int $message_id): array
    {
        $this->msg_user->delete_entry($user_id, $message_id);
        return ['success' => true, 'message' => 'نامه حذف شد.'];
    }

    public function mark_unread(int $user_id, int $message_id): array
    {
        $this->msg_user->mark_unread($user_id, $message_id);
        return ['success' => true, 'message' => 'نامه خوانده‌نشده شد.'];
    }

    public function batch_action(int $user_id, array $message_ids, string $action): array
    {
        $count = 0;

        foreach ($message_ids as $mid) {
            $mid = absint($mid);
            if (!$mid) continue;

            switch ($action) {
                case 'read':
                    $this->msg_user->mark_read($user_id, $mid);
                    break;
                case 'unread':
                    $this->msg_user->mark_unread($user_id, $mid);
                    break;
                case 'archive':
                    $this->msg_user->update_folder($user_id, $mid, 'archive');
                    break;
                case 'trash':
                    $this->msg_user->update_folder($user_id, $mid, 'trash');
                    break;
                case 'restore':
                    $this->msg_user->restore($user_id, $mid);
                    break;
                case 'delete':
                    $this->msg_user->delete_entry($user_id, $mid);
                    break;
            }

            $count++;
        }

        return [
            'success' => true,
            'message' => sprintf('عملیات روی %d نامه انجام شد.', $count),
        ];
    }

    public function delete_draft(int $user_id, int $message_id): array
    {
        $result = $this->message->delete_draft($message_id, $user_id);

        if ($result) {
            $this->msg_user->delete_entry($user_id, $message_id);
            return ['success' => true, 'message' => 'پیش‌نویس حذف شد.'];
        }

        return ['success' => false, 'message' => 'خطا در حذف پیش‌نویس.'];
    }

    private function format_list(array $rows): array
    {
        return array_map(fn($row) => [
            'message_id'          => (int) $row->message_id,
            'title'               => $row->title ?: '(بدون عنوان)',
            'priority'            => $row->priority,
            'status'              => $row->status ?? 'sent',
            'system_doc_number'   => $row->system_doc_number ?? '',
            'sent_at_jalali'      => $row->sent_at_jalali ?? '',
            'is_read'             => (int) $row->is_read,
            'is_starred'          => (int) $row->is_starred,
            'is_pinned'           => (int) $row->is_pinned,
            'sender'              => [
                'user_id'      => (int) ($row->sender_id ?? 0),
                'display_name' => $row->sender_display_name ?? '',
                'avatar_url'   => $row->sender_avatar_url ?? '',
            ],
        ], $rows);
    }
}