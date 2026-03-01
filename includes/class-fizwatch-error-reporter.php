<?php

if (! defined('ABSPATH')) {
    exit;
}

class FizWatch_Error_Reporter
{
    private static $instance = null;

    /** @var callable|null */
    private $previous_exception_handler = null;

    /** @var callable|null */
    private $previous_error_handler = null;

    /** @var bool */
    private $is_handling = false;

    /** @var int */
    private $errors_reported = 0;

    /** @var int */
    private $max_errors_per_request = 5;

    /** @var int */
    private $max_frames = 50;

    /** @var array */
    private $sensitive_body_keys = [
        '_token',
        '_wpnonce',
        'password',
        'password_confirmation',
        'credit_card',
        'ssn',
        'secret',
        'user_pass',
        'pwd',
        'pass1',
        'pass2',
        'token',
        'api_key',
        'secret_key',
        'access_token',
        'refresh_token',
        'client_secret',
    ];

    /** @var array */
    private $sensitive_header_keys = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-csrf-token',
        'x-xsrf-token',
        'x-wp-nonce',
        'proxy-authorization',
    ];

    /**
     * @return self
     */
    public static function instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
        if (! $this->is_enabled()) {
            return;
        }

        $this->sensitive_body_keys = apply_filters('fizwatch_sensitive_body_keys', $this->sensitive_body_keys);
        $this->sensitive_header_keys = apply_filters('fizwatch_sensitive_header_keys', $this->sensitive_header_keys);

        $this->previous_exception_handler = set_exception_handler([$this, 'handle_exception']);
        $this->previous_error_handler = set_error_handler([$this, 'handle_error'], E_USER_ERROR);
        register_shutdown_function([$this, 'handle_shutdown']);
    }

    /**
     * @return bool
     */
    private function is_enabled()
    {
        if (! get_option('fizwatch_error_reporting', false)) {
            return false;
        }

        $url = get_option('fizwatch_url', '');
        $key = get_option('fizwatch_key', '');

        return ! empty($url) && ! empty($key);
    }

    /**
     * Handle uncaught exceptions.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function handle_exception($exception)
    {
        try {
            if (! $this->is_handling && $this->errors_reported < $this->max_errors_per_request) {
                $this->is_handling = true;
                $this->report_exception($exception);
                $this->is_handling = false;
            }
        } catch (\Throwable $e) {
            $this->is_handling = false;
        }

        if ($this->previous_exception_handler !== null) {
            try {
                call_user_func($this->previous_exception_handler, $exception);
            } catch (\Throwable $e) {
                // Previous handler failed; nothing we can do safely.
            }
        }
    }

    /**
     * Handle PHP errors (E_USER_ERROR).
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return bool
     */
    public function handle_error($errno, $errstr, $errfile, $errline)
    {
        try {
            if (! $this->is_handling && $this->errors_reported < $this->max_errors_per_request) {
                $this->is_handling = true;
                $this->report_error($errno, $errstr, $errfile, $errline);
                $this->is_handling = false;
            }
        } catch (\Throwable $e) {
            $this->is_handling = false;
        }

        if ($this->previous_error_handler !== null) {
            return call_user_func($this->previous_error_handler, $errno, $errstr, $errfile, $errline);
        }

        return false;
    }

    /**
     * Handle fatal errors on shutdown.
     *
     * @return void
     */
    public function handle_shutdown()
    {
        try {
            $error = error_get_last();

            if ($error === null) {
                return;
            }

            $fatal_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

            if (! in_array($error['type'], $fatal_types, true)) {
                return;
            }

            if (! function_exists('wp_remote_post') || ! function_exists('get_option')) {
                return;
            }

            if (! $this->is_handling && $this->errors_reported < $this->max_errors_per_request) {
                $this->is_handling = true;
                $this->report_error($error['type'], $error['message'], $error['file'], $error['line']);
                $this->is_handling = false;
            }
        } catch (\Throwable $e) {
            $this->is_handling = false;
        }
    }

    /**
     * Report an exception to FizWatch.
     *
     * @param \Throwable $exception
     * @return void
     */
    private function report_exception($exception)
    {
        $payload = [
            'exception' => [
                'class' => $this->truncate(get_class($exception), 512),
                'message' => $this->truncate($exception->getMessage(), 10000),
                'file' => $this->truncate($exception->getFile(), 1024),
                'line' => $exception->getLine(),
            ],
            'stacktrace' => $this->format_stacktrace($exception->getTrace()),
            'environment' => $this->get_environment(),
            'request' => $this->get_request(),
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        FizWatch::instance()->send_error($payload);
        $this->errors_reported++;
    }

    /**
     * Report a PHP error to FizWatch.
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     * @return void
     */
    private function report_error($errno, $errstr, $errfile, $errline)
    {
        $class = $this->error_type_string($errno);

        $stacktrace = [];
        if (function_exists('debug_backtrace')) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $this->max_frames + 3);
            // Remove our own handler frames
            $trace = array_values(array_slice($trace, 3));
            $stacktrace = $this->format_stacktrace($trace);
        }

        $payload = [
            'exception' => [
                'class' => $this->truncate($class, 512),
                'message' => $this->truncate($errstr, 10000),
                'file' => $this->truncate($errfile, 1024),
                'line' => $errline,
            ],
            'stacktrace' => $stacktrace,
            'environment' => $this->get_environment(),
            'request' => $this->get_request(),
            'occurred_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        FizWatch::instance()->send_error($payload);
        $this->errors_reported++;
    }

    /**
     * Format a raw backtrace into the stacktrace format expected by the API.
     *
     * @param array $trace
     * @return array
     */
    private function format_stacktrace($trace)
    {
        $frames = [];

        $count = min(count($trace), $this->max_frames);

        for ($i = 0; $i < $count; $i++) {
            $frame = $trace[$i];
            $formatted = [];

            if (isset($frame['file'])) {
                $formatted['file'] = $this->truncate($frame['file'], 1024);
            }

            if (isset($frame['line'])) {
                $formatted['line'] = (int) $frame['line'];
            }

            if (isset($frame['function'])) {
                $formatted['function'] = $this->truncate($frame['function'], 512);
            }

            if (isset($frame['class'])) {
                $formatted['class'] = $this->truncate($frame['class'], 512);
            }

            $frames[] = $formatted;
        }

        return $frames;
    }

    /**
     * Get environment information.
     *
     * @return array
     */
    private function get_environment()
    {
        return [
            'php_version' => PHP_VERSION,
            'server_os' => PHP_OS . ' ' . php_uname('r'),
            'laravel_environment' => null,
        ];
    }

    /**
     * Get current HTTP request information, or null if running in CLI.
     *
     * @return array|null
     */
    private function get_request()
    {
        if (php_sapi_name() === 'cli' || defined('WP_CLI')) {
            return null;
        }

        $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';

        $url = '';
        if (isset($_SERVER['HTTP_HOST'])) {
            $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

            // Strip query string from URL since query_params are sent separately (and filtered)
            $query_pos = strpos($path, '?');
            if ($query_pos !== false) {
                $path = substr($path, 0, $query_pos);
            }

            $url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $path;
        }

        return [
            'method' => $method,
            'url' => $this->truncate($url, 2048),
            'headers' => $this->get_filtered_headers(),
            'body' => $this->filter_sensitive(array_slice($_POST, 0, 100, true), $this->sensitive_body_keys),
            'query_params' => $this->filter_sensitive(array_slice($_GET, 0, 100, true), $this->sensitive_body_keys),
        ];
    }

    /**
     * Get request headers with sensitive values filtered.
     *
     * @return array
     */
    private function get_filtered_headers()
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                $key = strtolower($name);
                if (in_array($key, $this->sensitive_header_keys, true)) {
                    $headers[$name] = '[Filtered]';
                } else {
                    $headers[$name] = $this->truncate($value, 4096);
                }
            }
        } else {
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, 'HTTP_') === 0) {
                    $name = str_replace('_', '-', substr($key, 5));
                    $lower = strtolower($name);
                    if (in_array($lower, $this->sensitive_header_keys, true)) {
                        $headers[$name] = '[Filtered]';
                    } else {
                        $headers[$name] = $this->truncate($value, 4096);
                    }
                }
            }
        }

        return $headers;
    }

    /**
     * Filter sensitive fields from an array, recursively.
     *
     * @param array $data
     * @param array $keys
     * @return array
     */
    private function filter_sensitive($data, $keys)
    {
        if (! is_array($data)) {
            return [];
        }

        $filtered = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $keys, true)) {
                $filtered[$key] = '[Filtered]';
            } elseif (is_array($value)) {
                $filtered[$key] = $this->filter_sensitive($value, $keys);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Convert PHP error constant to a readable string.
     *
     * @param int $type
     * @return string
     */
    private function error_type_string($type)
    {
        $map = [
            E_ERROR => 'E_ERROR',
            E_PARSE => 'E_PARSE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_USER_ERROR => 'E_USER_ERROR',
        ];

        return isset($map[$type]) ? $map[$type] : 'E_UNKNOWN';
    }

    /**
     * Truncate a string to a maximum length.
     *
     * @param string $value
     * @param int $max
     * @return string
     */
    private function truncate($value, $max)
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max);
    }
}
