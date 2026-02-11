<?php
defined('ABSPATH') || exit;

class WPOA_Activity_Logger
{
    private static ?WPOA_Activity_Model $model = null;

    private static function model(): WPOA_Activity_Model
    {
        if (self::$model === null) {
            self::$model = new WPOA_Activity_Model();
        }
        return self::$model;
    }

    public static function log(
        string $action,
        string $object_type = '',
        int    $object_id   = 0,
        string $details     = ''
    ): void {
        $user_id = get_current_user_id();
        if ($user_id === 0) {
            return;
        }

        self::model()->log($user_id, $action, $object_type, $object_id, $details);
    }

    public static function log_for_user(
        int    $user_id,
        string $action,
        string $object_type = '',
        int    $object_id   = 0,
        string $details     = ''
    ): void {
        self::model()->log($user_id, $action, $object_type, $object_id, $details);
    }
}