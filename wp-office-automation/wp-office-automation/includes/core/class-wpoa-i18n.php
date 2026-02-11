<?php
defined('ABSPATH') || exit;

class WPOA_i18n
{
    public function load_plugin_textdomain(): void
    {
        load_plugin_textdomain('wpoa', false, dirname(WPOA_PLUGIN_BASENAME) . '/languages/');
    }
}