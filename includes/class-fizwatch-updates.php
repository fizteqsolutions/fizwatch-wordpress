<?php

if (! defined('ABSPATH')) {
    exit;
}

class FizWatch_Updates
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('fizwatch_daily_update_check', [$this, 'check_updates']);
    }

    /**
     * Check for available updates and report to FizWatch.
     */
    public function check_updates()
    {
        if (! function_exists('get_plugin_updates')) {
            require_once ABSPATH.'wp-admin/includes/plugin.php';
            require_once ABSPATH.'wp-admin/includes/update.php';
        }

        wp_update_plugins();
        wp_update_themes();
        wp_version_check();

        $current_updates = $this->get_pending_updates();
        $previous_updates = get_option('fizwatch_pending_updates', []);

        $new_events = [];
        $resolved_fingerprints = [];

        $fizwatch = FizWatch::instance();

        foreach ($current_updates as $key => $update) {
            if (! isset($previous_updates[$key])) {
                $new_events[] = $update;
            }
        }

        foreach ($previous_updates as $key => $update) {
            if (! isset($current_updates[$key])) {
                $class = $this->resolve_exception_class($update);
                $message = $this->resolve_message($update);
                $fingerprint = $fizwatch->compute_fingerprint($class, $message, $update['slug'], 0);
                $resolved_fingerprints[] = $fingerprint;
            }
        }

        $send_success = true;

        if (! empty($new_events)) {
            $events = [];
            foreach ($new_events as $update) {
                $events[] = [
                    'type' => 'update_available',
                    'slug' => $update['slug'],
                    'name' => $update['name'],
                    'current_version' => $update['current_version'],
                    'new_version' => $update['new_version'],
                    'category' => $update['category'],
                ];
            }
            $send_success = $fizwatch->send_events($events);
        }

        if (! empty($resolved_fingerprints)) {
            $fizwatch->send_resolve($resolved_fingerprints);
        }

        if ($send_success) {
            update_option('fizwatch_pending_updates', $current_updates, false);
        }
    }

    /**
     * Get all pending updates keyed by a unique identifier.
     *
     * @return array<string, array>
     */
    private function get_pending_updates()
    {
        $updates = [];

        $plugin_updates = get_plugin_updates();
        if (! empty($plugin_updates)) {
            foreach ($plugin_updates as $file => $plugin) {
                if (! isset($plugin->update->new_version)) {
                    continue;
                }
                $key = 'plugin:'.$file;
                $updates[$key] = [
                    'slug' => $file,
                    'name' => isset($plugin->Name) ? $plugin->Name : $file,
                    'current_version' => isset($plugin->Version) ? $plugin->Version : 'unknown',
                    'new_version' => $plugin->update->new_version,
                    'category' => 'plugin',
                ];
            }
        }

        $core_updates = get_core_updates();
        if (! empty($core_updates) && is_array($core_updates)) {
            foreach ($core_updates as $core) {
                if (isset($core->response) && $core->response === 'upgrade') {
                    global $wp_version;
                    $key = 'core:wordpress';
                    $updates[$key] = [
                        'slug' => 'wordpress',
                        'name' => 'WordPress',
                        'current_version' => $wp_version,
                        'new_version' => $core->current,
                        'category' => 'core',
                    ];
                }
            }
        }

        $theme_updates = get_theme_updates();
        if (! empty($theme_updates)) {
            foreach ($theme_updates as $slug => $theme) {
                if (! isset($theme->update['new_version'])) {
                    continue;
                }
                $key = 'theme:'.$slug;
                $updates[$key] = [
                    'slug' => $slug,
                    'name' => $theme->display('Name'),
                    'current_version' => $theme->display('Version'),
                    'new_version' => $theme->update['new_version'],
                    'category' => 'theme',
                ];
            }
        }

        $translation_updates = wp_get_translation_updates();
        if (! empty($translation_updates)) {
            foreach ($translation_updates as $translation) {
                $key = 'translation:'.$translation->slug.':'.$translation->language;
                $name = $translation->slug === 'default' ? 'WordPress' : ucfirst($translation->slug);
                $updates[$key] = [
                    'slug' => $translation->slug,
                    'name' => $name.' '.$translation->language,
                    'current_version' => isset($translation->version) ? $translation->version : '0',
                    'new_version' => isset($translation->version) ? $translation->version : '0',
                    'category' => 'translation',
                ];
            }
        }

        return $updates;
    }

    /**
     * Resolve exception class for fingerprint computation.
     *
     * @param  array  $update
     * @return string
     */
    private function resolve_exception_class($update)
    {
        $map = [
            'plugin' => 'PluginUpdateAvailable',
            'core' => 'CoreUpdateAvailable',
            'theme' => 'ThemeUpdateAvailable',
            'translation' => 'TranslationUpdateAvailable',
        ];

        return isset($map[$update['category']]) ? $map[$update['category']] : 'UnknownUpdateAvailable';
    }

    /**
     * Resolve message for fingerprint computation (must match server-side logic).
     *
     * @param  array  $update
     * @return string
     */
    private function resolve_message($update)
    {
        if ($update['category'] === 'translation') {
            return $update['name'];
        }

        return $update['name'].' '.$update['current_version']." \xe2\x86\x92 ".$update['new_version'];
    }
}
