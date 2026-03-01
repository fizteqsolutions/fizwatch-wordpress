<?php

if (!defined('ABSPATH')) {
    exit;
}

class FizWatch
{
    private static $instance = null;

    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Send events to the FizWatch API.
     *
     * @param array $events Array of event data.
     * @param array $environment Optional environment data.
     * @return bool Whether the request was successful.
     */
    public function send_events($events, $environment = [])
    {
        $url = get_option('fizwatch_url', '');
        $key = get_option('fizwatch_key', '');

        if (empty($url) || empty($key)) {
            return false;
        }

        $payload = ['events' => $events];

        if (!empty($environment)) {
            $payload['environment'] = $environment;
        } else {
            $payload['environment'] = $this->get_environment();
        }

        return $this->post($url . '/api/v1/wordpress/events', $key, $payload);
    }

    /**
     * Send resolve request to auto-resolve completed updates.
     *
     * @param array $fingerprints Array of fingerprint strings.
     * @return bool Whether the request was successful.
     */
    public function send_resolve($fingerprints)
    {
        $url = get_option('fizwatch_url', '');
        $key = get_option('fizwatch_key', '');

        if (empty($url) || empty($key) || empty($fingerprints)) {
            return false;
        }

        return $this->post($url . '/api/v1/wordpress/resolve', $key, [
            'fingerprints' => $fingerprints,
        ]);
    }

    /**
     * Test the connection to FizWatch.
     *
     * @return array{success: bool, message: string}
     */
    public function test_connection()
    {
        $url = get_option('fizwatch_url', '');
        $key = get_option('fizwatch_key', '');

        if (empty($url) || empty($key)) {
            return ['success' => false, 'message' => 'URL and API key are required.'];
        }

        $events = [
            [
                'type' => 'plugin_activated',
                'slug' => FIZWATCH_PLUGIN_BASENAME,
                'name' => 'FizWatch Test Connection',
            ],
        ];

        $success = $this->send_events($events);

        if ($success) {
            return ['success' => true, 'message' => 'Connection successful!'];
        }

        return ['success' => false, 'message' => 'Could not connect to FizWatch. Please check your URL and API key.'];
    }

    /**
     * Get environment information.
     *
     * @return array
     */
    public function get_environment()
    {
        global $wp_version;

        return [
            'php_version' => PHP_VERSION,
            'wordpress_version' => $wp_version,
            'server_os' => PHP_OS . ' ' . php_uname('r'),
        ];
    }

    /**
     * Send an error report to the FizWatch API.
     *
     * @param array $payload Error report data matching the /api/v1/errors format.
     * @return bool Whether the request was successful.
     */
    public function send_error($payload)
    {
        $url = get_option('fizwatch_url', '');
        $key = get_option('fizwatch_key', '');

        if (empty($url) || empty($key)) {
            return false;
        }

        return $this->post($url . '/api/v1/errors', $key, $payload);
    }

    /**
     * Compute fingerprint matching the server-side algorithm.
     *
     * @param string $class
     * @param string $message
     * @param string $file
     * @param int $line
     * @return string
     */
    public function compute_fingerprint($class, $message, $file, $line)
    {
        return hash('sha256', $class . $message . $file . ':' . $line);
    }

    /**
     * @param string $url
     * @param string $key
     * @param array $payload
     * @return bool
     */
    private function post($url, $key, $payload)
    {
        try {
            $json = wp_json_encode($payload);

            if ($json === false) {
                return false;
            }

            $response = wp_remote_post($url, [
                'timeout' => 10,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-FizWatch-Key' => $key,
                ],
                'body' => $json,
            ]);

            if (is_wp_error($response)) {
                return false;
            }

            $code = wp_remote_retrieve_response_code($response);

            return $code >= 200 && $code < 300;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
