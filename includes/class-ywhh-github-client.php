<?php

if (! defined('ABSPATH')) {
    exit;
}

class YWHH_GitHub_Client
{
    private const OWNER = 'maximebellefleur';

    public function __construct()
    {
    }

    public function get_repositories(): array
    {
        $response = wp_remote_get(
            sprintf('https://api.github.com/users/%s/repos?per_page=100&sort=updated', rawurlencode(self::OWNER)),
            [
            'timeout' => 20,
            'headers' => $this->headers(),
            ]
        );

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($body)) {
            return [];
        }

        return array_values(array_filter($body, static function ($repo): bool {
            if (! is_array($repo) || empty($repo['name']) || ! empty($repo['fork'])) {
                return false;
            }

            $name = strtolower((string) $repo['name']);
            $full_name = strtolower((string) ($repo['full_name'] ?? ''));

            return strpos($name, 'yuna-') === 0
                && strpos($full_name, 'maximebellefleur/yuna-') === 0
                && $name !== 'yuna-wordpress-helper';
        }));
    }

    public function get_latest_release(string $owner, string $repo): ?array
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            rawurlencode($owner),
            rawurlencode($repo)
        );

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => $this->headers(),
        ]);

        if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($body) ? $body : null;
    }

    private function headers(): array
    {
        return [
            'Accept'     => 'application/vnd.github+json',
            'User-Agent' => 'Yuna-WordPress-Helper/' . YWHH_VERSION,
        ];
    }
}
