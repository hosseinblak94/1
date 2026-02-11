<?php
defined('ABSPATH') || exit;

class WPOA_Tag_Model extends WPOA_Model
{
    protected string $table_suffix = 'tags';

    public function get_all(): array
    {
        return $this->query("SELECT * FROM {$this->table()} ORDER BY name ASC");
    }

    public function create(string $name, string $color = '#0073aa'): int|false
    {
        $slug = sanitize_title($name);

        $exists = $this->query_var(
            "SELECT id FROM {$this->table()} WHERE slug = %s LIMIT 1",
            [$slug]
        );

        if ($exists) {
            return false;
        }

        return $this->insert([
            'name'  => sanitize_text_field($name),
            'slug'  => $slug,
            'color' => sanitize_hex_color($color) ?: '#0073aa',
        ]);
    }

    public function remove(int $tag_id): bool
    {
        $pivot = $this->other_table('message_tags');
        global $wpdb;
        $wpdb->delete($pivot, ['tag_id' => $tag_id]);

        return $this->delete(['id' => $tag_id]) !== false;
    }

    public function sync_message_tags(int $message_id, string $tags_csv): void
    {
        $pivot = $this->other_table('message_tags');
        global $wpdb;

        $wpdb->delete($pivot, ['message_id' => $message_id]);

        if (empty(trim($tags_csv))) {
            return;
        }

        $names = array_filter(array_map('trim', explode(',', $tags_csv)));

        foreach ($names as $name) {
            $slug = sanitize_title($name);
            $tag_id = $this->query_var(
                "SELECT id FROM {$this->table()} WHERE slug = %s LIMIT 1",
                [$slug]
            );

            if (!$tag_id) {
                $tag_id = $this->create($name);
            }

            if ($tag_id) {
                $wpdb->insert($pivot, [
                    'message_id' => $message_id,
                    'tag_id'     => (int) $tag_id,
                ]);
            }
        }
    }

    public function get_message_tags(int $message_id): array
    {
        $pivot = $this->other_table('message_tags');

        return $this->query(
            "SELECT t.*
             FROM {$this->table()} t
             INNER JOIN {$pivot} mt ON mt.tag_id = t.id
             WHERE mt.message_id = %d
             ORDER BY t.name ASC",
            [$message_id]
        );
    }
}