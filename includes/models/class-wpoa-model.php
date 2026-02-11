<?php
defined('ABSPATH') || exit;

abstract class WPOA_Model
{
    protected string $table_suffix = '';

    protected function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpoa_' . $this->table_suffix;
    }

    protected function other_table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'wpoa_' . $suffix;
    }

    protected function insert(array $data): int|false
    {
        global $wpdb;
        $result = $wpdb->insert($this->table(), $data);
        return $result ? (int) $wpdb->insert_id : false;
    }

    protected function update(array $data, array $where): int|false
    {
        global $wpdb;
        return $wpdb->update($this->table(), $data, $where);
    }

    protected function delete(array $where): int|false
    {
        global $wpdb;
        return $wpdb->delete($this->table(), $where);
    }

    protected function find(int $id): ?object
    {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d LIMIT 1", $id)
        );
    }

    protected function query(string $sql, array $params = []): array
    {
        global $wpdb;
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        return $wpdb->get_results($sql) ?: [];
    }

    protected function query_row(string $sql, array $params = []): ?object
    {
        global $wpdb;
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        return $wpdb->get_row($sql);
    }

    protected function query_var(string $sql, array $params = [])
    {
        global $wpdb;
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        return $wpdb->get_var($sql);
    }

    protected function count_where(string $where_sql, array $params = []): int
    {
        global $wpdb;
        $sql = "SELECT COUNT(*) FROM {$this->table()} WHERE {$where_sql}";
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        return (int) $wpdb->get_var($sql);
    }

    protected function validate_enum(string $value, array $allowed, string $default): string
    {
        return in_array($value, $allowed, true) ? $value : $default;
    }
}