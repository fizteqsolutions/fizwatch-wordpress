<?php

if (! defined('ABSPATH')) {
    exit;
}

class FizWatch_Settings
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
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('wp_ajax_fizwatch_test_connection', [$this, 'ajax_test_connection']);
    }

    public function add_settings_page()
    {
        add_options_page(
            'FizWatch Settings',
            'FizWatch',
            'manage_options',
            'fizwatch',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings()
    {
        register_setting('fizwatch_settings', 'fizwatch_url', [
            'type' => 'string',
            'sanitize_callback' => function ($value) {
                return rtrim(esc_url_raw($value), '/');
            },
        ]);

        register_setting('fizwatch_settings', 'fizwatch_key', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ]);

        add_settings_section(
            'fizwatch_main',
            'Connection Settings',
            null,
            'fizwatch'
        );

        add_settings_field(
            'fizwatch_url',
            'FizWatch URL',
            [$this, 'render_url_field'],
            'fizwatch',
            'fizwatch_main'
        );

        add_settings_field(
            'fizwatch_key',
            'API Key',
            [$this, 'render_key_field'],
            'fizwatch',
            'fizwatch_main'
        );

        register_setting('fizwatch_settings', 'fizwatch_error_reporting', [
            'type' => 'boolean',
            'sanitize_callback' => function ($value) {
                return (bool) $value;
            },
            'default' => false,
        ]);

        add_settings_section(
            'fizwatch_error_reporting',
            'Error Reporting',
            null,
            'fizwatch'
        );

        add_settings_field(
            'fizwatch_error_reporting',
            'Enable PHP Error Reporting',
            [$this, 'render_error_reporting_field'],
            'fizwatch',
            'fizwatch_error_reporting'
        );
    }

    public function render_url_field()
    {
        $value = get_option('fizwatch_url', '');
        echo '<input type="url" name="fizwatch_url" value="'.esc_attr($value).'" class="regular-text" placeholder="https://fizwatch.example.com" />';
        echo '<p class="description">The URL of your FizWatch instance.</p>';
    }

    public function render_key_field()
    {
        $value = get_option('fizwatch_key', '');
        echo '<input type="password" name="fizwatch_key" value="'.esc_attr($value).'" class="regular-text" placeholder="fiz_..." />';
        echo '<p class="description">The API key for your project in FizWatch.</p>';
    }

    public function render_error_reporting_field()
    {
        $value = get_option('fizwatch_error_reporting', false);
        echo '<label>';
        echo '<input type="checkbox" name="fizwatch_error_reporting" value="1" '.checked($value, true, false).' />';
        echo ' Send uncaught exceptions and fatal PHP errors to FizWatch';
        echo '</label>';
        echo '<p class="description">When enabled, uncaught exceptions and fatal PHP errors will be sent to FizWatch.</p>';
    }

    public function render_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('fizwatch_settings');
        do_settings_sections('fizwatch');
        submit_button('Save Settings');
        ?>
            </form>

            <hr />
            <h2>Test Connection</h2>
            <p>
                <button type="button" id="fizwatch-test-btn" class="button button-secondary">
                    Test Connection
                </button>
                <span id="fizwatch-test-result" style="margin-left: 10px;"></span>
            </p>
        </div>

        <script>
        document.getElementById('fizwatch-test-btn').addEventListener('click', function() {
            var btn = this;
            var result = document.getElementById('fizwatch-test-result');

            btn.disabled = true;
            result.textContent = 'Testing...';
            result.style.color = '#666';

            fetch(ajaxurl + '?action=fizwatch_test_connection&_wpnonce=<?php echo wp_create_nonce('fizwatch_test'); ?>', {
                method: 'POST',
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                result.textContent = data.data.message;
                result.style.color = data.success ? 'green' : 'red';
                btn.disabled = false;
            })
            .catch(function() {
                result.textContent = 'Request failed.';
                result.style.color = 'red';
                btn.disabled = false;
            });
        });
        </script>
        <?php
    }

    public function ajax_test_connection()
    {
        check_ajax_referer('fizwatch_test');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $result = FizWatch::instance()->test_connection();

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }
}
