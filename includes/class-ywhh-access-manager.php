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
        $repositories = $settings['repositories'] ?? [];
        if (! is_array($repositories)) {
            $repositories = [];
        }

        return [
            'client_name'    => (string) ($settings['client_name'] ?? ''),
            'client_email'   => (string) ($settings['client_email'] ?? ''),
            'access_token'   => (string) ($settings['access_token'] ?? ''),
            'token_expiry'   => (string) ($settings['token_expiry'] ?? ''),
            'repositories'   => array_values(array_filter(array_map('esc_url_raw', $repositories))),
            'last_check'     => (string) ($settings['last_check'] ?? ''),
            'last_status'    => (string) ($settings['last_status'] ?? ''),
            'last_message'   => (string) ($settings['last_message'] ?? ''),
        ];
    }

    public function sanitize_settings(array $input): array
    {
        $existing  = $this->get_settings();
        $new_name  = sanitize_text_field((string) ($input['client_name'] ?? $existing['client_name']));
        $new_email = sanitize_email((string) ($input['client_email'] ?? $existing['client_email']));
        $new_token  = sanitize_text_field((string) ($input['access_token'] ?? ''));
        $new_expiry = sanitize_text_field((string) ($input['token_expiry'] ?? $existing['token_expiry']));
        if ($new_expiry !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $new_expiry)) {
            $new_expiry = '';
        }
        $repositories = $existing['repositories'];
        if (isset($input['repositories']) && is_array($input['repositories'])) {
            $repositories = array_values(array_filter(array_map('esc_url_raw', $input['repositories'])));
        }

        // store_status() calls update_option() directly with the full settings array.
        // Pass those through unchanged so the sanitize filter does not overwrite the
        // freshly-written check result with stale data from $existing.
        $last_check   = isset($input['last_check'])   ? (string) $input['last_check']   : $existing['last_check'];
        $last_status  = isset($input['last_status'])  ? (string) $input['last_status']  : $existing['last_status'];
        $last_message = isset($input['last_message']) ? (string) $input['last_message'] : $existing['last_message'];

        // When token identity changes (user edits the form fields), clear the cached
        // check result so the next page load is forced to re-verify.
        if (
            $new_name !== $existing['client_name']
            || $new_email !== $existing['client_email']
            || $new_token !== $existing['access_token']
            || $new_expiry !== $existing['token_expiry']
        ) {
            $last_check = $last_status = $last_message = '';
            $repositories = [];
        }

        return [
            'client_name'  => $new_name,
            'client_email' => $new_email,
            'access_token' => $new_token,
            'token_expiry' => $new_expiry,
            'repositories' => $repositories,
            'last_check'   => $last_check,
            'last_status'  => $last_status,
            'last_message' => $last_message,
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
            $this->store_status('invalid', __('Missing access token.', 'yuna-wordpress-helper'), []);

            return false;
        }

        $response = wp_remote_post(self::VERIFY_ENDPOINT, [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
            'body' => wp_json_encode([
                'token' => $settings['access_token'],
                'site'  => home_url('/'),
            ]),
        ]);

        if (is_wp_error($response)) {
            $this->store_status('invalid', __('Unable to reach token server.', 'yuna-wordpress-helper'), []);

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

        $repositories = $is_valid && is_array($body) && isset($body['repositories']) && is_array($body['repositories'])
            ? $this->sanitize_repository_urls($body['repositories'])
            : [];

        $this->store_status($is_valid ? 'valid' : 'invalid', $message, $repositories);

        return $is_valid;
    }

    private function store_status(string $status, string $message, array $repositories): void
    {
        $settings = $this->get_settings();
        $settings['last_check'] = gmdate('c');
        $settings['last_status'] = $status;
        $settings['last_message'] = $message;
        $settings['repositories'] = $repositories;

        update_option(self::OPTION_KEY, $settings, false);
    }

    private function sanitize_repository_urls(array $repositories): array
    {
        $urls = [];

        foreach ($repositories as $repository) {
            $url = esc_url_raw((string) $repository);
            if (! preg_match('#^https://github\.com/maximebellefleur/yuna-[a-z0-9._-]+/?$#i', $url)) {
                continue;
            }

            if (preg_match('#/yuna-wordpress-helper/?$#i', $url)) {
                continue;
            }

            $urls[] = untrailingslashit($url);
        }

        return array_values(array_unique($urls));
    }
}
