<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$url = get_option('fizwatch_url', '');
$key = get_option('fizwatch_key', '');

if (!empty($url) && !empty($key)) {
    wp_remote_post($url . '/api/v1/wordpress/events', [
        'timeout' => 5,
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-FizWatch-Key' => $key,
        ],
        'body' => wp_json_encode([
            'events' => [
                [
                    'type' => 'plugin_uninstalled',
                    'slug' => 'fizwatch/fizwatch.php',
                    'name' => 'FizWatch',
                ],
            ],
            'environment' => [
                'php_version' => PHP_VERSION,
                'wordpress_version' => get_bloginfo('version'),
                'server_os' => PHP_OS . ' ' . php_uname('r'),
            ],
        ]),
    ]);
}

delete_option('fizwatch_url');
delete_option('fizwatch_key');
delete_option('fizwatch_pending_updates');
delete_option('fizwatch_error_reporting');

wp_clear_scheduled_hook('fizwatch_daily_update_check');
