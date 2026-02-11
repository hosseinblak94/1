<?php
defined('ABSPATH') || exit;

class WPOA_Settings_Controller
{
    private WPOA_Settings_Model $settings;

    public function __construct()
    {
        $this->settings = new WPOA_Settings_Model();
    }

    public function get(): array
    {
        return [
            'success'  => true,
            'settings' => $this->settings->get_all(),
        ];
    }

    public function save(array $data): array
    {
        $this->settings->save_many($data);

        WPOA_Activity_Logger::log(WPOA_Activity_Model::ACTION_SETTINGS_SAVED);

        return ['success' => true, 'message' => 'تنظیمات ذخیره شد.'];
    }
}