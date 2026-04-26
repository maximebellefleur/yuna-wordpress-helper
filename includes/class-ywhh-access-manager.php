<?php

if (! defined('ABSPATH')) {
    exit;
}

class YWHH_Access_Manager
{
    public const OPTION_KEY = 'ywhh_access_settings';
    public const CRON_HOOK = 'ywhh_monthly_token_verification';
    private const VERIFY_ENDPOINT = 'https://maximebellefleur.com/yunadesign/helper/api/verify-token';

    public function register_hooks(): void
    {
        add_filter('cron_schedules', [$this, 'register_monthly_schedule']);
        add_action(self::CRON_HOOK, [$this, 'verify_token_via_cron']);
        add_action('upgrader_process_complete', [$this, 'verify_token_after_plugin_change'], 10, 2);
    }

    public static function activate(): void
    {
        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'ywhh_monthly', self::CRON_HOOK);
        }
    }

    public static function deactivate(): void
    {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    public function register_monthly_schedule(array $schedules): array
    {
        if (! isset($schedules['ywhh_monthly'])) {
            $schedules['ywhh_monthly'] = [
                'interval' => 30 * DAY_IN_SECONDS,
                'display'  => __('Once Monthly (Yuna Helper)', 'yuna-wordpress-helper'),
            ];
        }

        return $schedules;
    }

    public function get_settings(): array
    {
        $settings = get_option(self::OPTION_KEY, []);

        return [
            'access_token'   => (string) ($settings['access_token'] ?? ''),
            'last_check'     => (string) ($settings['last_check'] ?? ''),
            'last_status'    => (string) ($settings['last_status'] ?? ''),
            'last_message'   => (string) ($settings['last_message'] ?? ''),
        ];
    }

    public function sanitize_settings(array $input): array
    {
        $existing = $this->get_settings();

        return [
            'access_token'  => sanitize_text_field((string) ($input['access_token'] ?? '')),
            'last_check'    => $existing['last_check'],
            'last_status'   => $existing['last_status'],
            'last_message'  => $existing['last_message'],
        ];
    }

    public function verify_token_via_cron(): void
    {
        $this->perform_token_check();
    }

    public function verify_token_after_plugin_change($upgrader, array $context): void
    {
        if (($context['type'] ?? '') !== 'plugin') {
            return;
        }

        $this->perform_token_check();
    }

    public function perform_token_check(): bool
    {
        $settings = $this->get_settings();
        if ($settings['access_token'] === '') {
            $this->store_status('invalid', __('Missing token.', 'yuna-wordpress-helper'));

            return false;
        }

        $response = wp_remote_post(self::VERIFY_ENDPOINT, [
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'body' => [
                'token'  => $settings['access_token'],
                'site'   => home_url('/'),
            ],
        ]);

        if (is_wp_error($response)) {
            $this->store_status('invalid', __('Unable to reach token server.', 'yuna-wordpress-helper'));

            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        $is_valid = $code === 200 && is_array($body) && ! empty($body['valid']);

        $message = __('Token validation failed.', 'yuna-wordpress-helper');
        if ($is_valid) {
            $message = __('Token validated successfully.', 'yuna-wordpress-helper');
        } elseif (is_array($body) && ! empty($body['reason'])) {
            $message = sanitize_text_field((string) $body['reason']);
        }

        $this->store_status($is_valid ? 'valid' : 'invalid', $message);

        return $is_valid;
    }

    private function store_status(string $status, string $message): void
    {
        $settings = $this->get_settings();
        $settings['last_check'] = gmdate('c');
        $settings['last_status'] = $status;
        $settings['last_message'] = $message;

        update_option(self::OPTION_KEY, $settings, false);
    }
}
