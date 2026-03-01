<?php

if (!defined('ABSPATH')) {
    exit;
}

class FizWatch_Lifecycle
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('activated_plugin', [$this, 'on_plugin_activated'], 10, 1);
        add_action('deactivated_plugin', [$this, 'on_plugin_deactivated'], 10, 1);
        add_action('upgrader_process_complete', [$this, 'on_upgrader_complete'], 10, 2);
    }

    /**
     * Called when any plugin is activated.
     *
     * @param string $plugin Plugin basename (e.g., "woocommerce/woocommerce.php").
     */
    public function on_plugin_activated($plugin)
    {
        if ($plugin === FIZWATCH_PLUGIN_BASENAME) {
            return;
        }

        $name = $this->get_plugin_name($plugin);

        FizWatch::instance()->send_events([
            array_merge([
                'type' => 'plugin_activated',
                'slug' => $plugin,
                'name' => $name,
            ], self::get_actor_context()),
        ]);
    }

    /**
     * Called when any plugin is deactivated.
     *
     * @param string $plugin Plugin basename.
     */
    public function on_plugin_deactivated($plugin)
    {
        if ($plugin === FIZWATCH_PLUGIN_BASENAME) {
            return;
        }

        $name = $this->get_plugin_name($plugin);

        FizWatch::instance()->send_events([
            array_merge([
                'type' => 'plugin_deactivated',
                'slug' => $plugin,
                'name' => $name,
            ], self::get_actor_context()),
        ]);
    }

    /**
     * Called when WordPress finishes installing/updating plugins or themes.
     *
     * @param \WP_Upgrader $upgrader
     * @param array $hook_extra
     */
    public function on_upgrader_complete($upgrader, $hook_extra)
    {
        if (!isset($hook_extra['action']) || $hook_extra['action'] !== 'install') {
            return;
        }

        if ($hook_extra['type'] !== 'plugin') {
            return;
        }

        $result = $upgrader->result;
        if (empty($result) || is_wp_error($result)) {
            return;
        }

        $slug = isset($result['destination_name']) ? $result['destination_name'] : 'unknown';
        $name = $slug;

        $plugin_file = $upgrader->plugin_info();
        if ($plugin_file) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_file, false, false);
            if (!empty($plugin_data['Name'])) {
                $name = $plugin_data['Name'];
                $slug = $plugin_file;
            }
        }

        FizWatch::instance()->send_events([
            array_merge([
                'type' => 'plugin_installed',
                'slug' => $slug,
                'name' => $name,
            ], self::get_actor_context()),
        ]);
    }

    /**
     * Called when FizWatch itself is activated.
     */
    public static function on_self_activated()
    {
        if (!wp_next_scheduled('fizwatch_daily_update_check')) {
            wp_schedule_event(time(), 'daily', 'fizwatch_daily_update_check');
        }

        FizWatch::instance()->send_events([
            array_merge([
                'type' => 'plugin_activated',
                'slug' => FIZWATCH_PLUGIN_BASENAME,
                'name' => 'FizWatch',
            ], self::get_actor_context()),
        ]);
    }

    /**
     * Called when FizWatch itself is deactivated.
     */
    public static function on_self_deactivated()
    {
        wp_clear_scheduled_hook('fizwatch_daily_update_check');

        FizWatch::instance()->send_events([
            array_merge([
                'type' => 'plugin_deactivated',
                'slug' => FIZWATCH_PLUGIN_BASENAME,
                'name' => 'FizWatch',
            ], self::get_actor_context()),
        ]);
    }

    /**
     * Get the current actor context (username and IP) for event attribution.
     *
     * @return array<string, string>
     */
    private static function get_actor_context()
    {
        $context = [];

        if (function_exists('wp_get_current_user')) {
            $user = wp_get_current_user();
            if ($user->exists()) {
                $context['user'] = $user->user_login;
            }
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $context['ip_address'] = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return $context;
    }

    /**
     * Get the human-readable name of a plugin from its basename.
     *
     * @param string $plugin Plugin basename.
     * @return string
     */
    private function get_plugin_name($plugin)
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_file = WP_PLUGIN_DIR . '/' . $plugin;

        if (file_exists($plugin_file)) {
            $data = get_plugin_data($plugin_file, false, false);
            if (!empty($data['Name'])) {
                return $data['Name'];
            }
        }

        $parts = explode('/', $plugin);
        return ucfirst(str_replace('-', ' ', $parts[0]));
    }
}
