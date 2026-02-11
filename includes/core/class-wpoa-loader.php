<?php
defined('ABSPATH') || exit;

class WPOA_Loader
{
    protected array $actions = [];
    protected array $filters = [];

    public function add_action(
        string $hook, object $component, string $callback,
        int $priority = 10, int $accepted_args = 1
    ): void {
        $this->actions[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    public function add_filter(
        string $hook, object $component, string $callback,
        int $priority = 10, int $accepted_args = 1
    ): void {
        $this->filters[] = compact('hook', 'component', 'callback', 'priority', 'accepted_args');
    }

    public function run(): void
    {
        foreach ($this->actions as $h) {
            add_action($h['hook'], [$h['component'], $h['callback']], $h['priority'], $h['accepted_args']);
        }
        foreach ($this->filters as $h) {
            add_filter($h['hook'], [$h['component'], $h['callback']], $h['priority'], $h['accepted_args']);
        }
    }
}