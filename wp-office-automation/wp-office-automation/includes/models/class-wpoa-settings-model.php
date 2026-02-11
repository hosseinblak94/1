<?php
defined('ABSPATH') || exit;

class WPOA_Settings_Model extends WPOA_Model
{
    protected string $table_suffix = 'settings';

    public function get(string $key, ?string $default = null): ?string
    {
        $val = $this->query_var(
            "SELECT setting_val FROM {$this->table()} WHERE setting_key = %s LIMIT 1",
            [$key]
        );

        return $val !== null ? $val : $default;
    }

    public function set(string $key, string $value): bool
    {
        $exists = $this->query_var(
            "SELECT id FROM {$this->table()} WHERE setting_key = %s LIMIT 1",
            [$key]
        );

        if ($exists) {
            return $this->update(
                ['setting_val' => $value],
                ['setting_key' => $key]
            ) !== false;
        }

        return $this->insert([
            'setting_key' => sanitize_key($key),
            'setting_val' => $value,
        ]) !== false;
    }

    public function get_all(): array
    {
        $rows   = $this->query("SELECT setting_key, setting_val FROM {$this->table()}");
        $result = [];

        foreach ($rows as $row) {
            $result[$row->setting_key] = $row->setting_val;
        }

        return $result;
    }

    public function save_many(array $data): void
    {
        foreach ($data as $key => $val) {
            $this->set(sanitize_key($key), sanitize_text_field($val));
        }
    }
}