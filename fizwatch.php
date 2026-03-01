<?php
/**
 * Plugin Name: FizWatch
 * Plugin URI: https://github.com/fizteqsolutions/fizwatch-wordpress
 * Description: Monitors WordPress plugin lifecycle events, available updates, and PHP errors, reporting them to your FizWatch instance.
 * Version: 1.2.0
 * Author: FizTeq Solutions
 * Author URI: https://fizteq.com
 * License: MIT
 * Requires PHP: 7.4
 * Requires at least: 5.9
 */

if (!defined('ABSPATH')) {
    exit;
}

define('FIZWATCH_VERSION', '1.2.0');
define('FIZWATCH_PLUGIN_FILE', __FILE__);
define('FIZWATCH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FIZWATCH_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once FIZWATCH_PLUGIN_DIR . 'includes/class-fizwatch.php';
require_once FIZWATCH_PLUGIN_DIR . 'includes/class-fizwatch-settings.php';
require_once FIZWATCH_PLUGIN_DIR . 'includes/class-fizwatch-lifecycle.php';
require_once FIZWATCH_PLUGIN_DIR . 'includes/class-fizwatch-updates.php';
require_once FIZWATCH_PLUGIN_DIR . 'includes/class-fizwatch-error-reporter.php';

add_action('plugins_loaded', function () {
    FizWatch::instance();
    FizWatch_Settings::instance();
    FizWatch_Lifecycle::instance();
    FizWatch_Updates::instance();
    FizWatch_Error_Reporter::instance();
});

register_activation_hook(__FILE__, ['FizWatch_Lifecycle', 'on_self_activated']);
register_deactivation_hook(__FILE__, ['FizWatch_Lifecycle', 'on_self_deactivated']);
