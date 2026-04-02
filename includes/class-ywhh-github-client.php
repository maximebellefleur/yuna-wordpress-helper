<?php

if (! defined('ABSPATH')) {
    exit;
}

class YWHH_GitHub_Client
{
    private string $owner;

    public function __construct(string $owner)
    {
        $this->owner = trim($owner);
    }

    public function get_repositories(): array
    {
        if ($this->owner === '') {
            return [];
        }

        $url = sprintf(
            'https://api.github.com/users/%s/repos?per_page=100&sort=updated',
            rawurlencode($this->owner)
        );

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Yuna-WordPress-Helper/' . YWHH_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return [];
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);
        if (! is_array($body)) {
            return [];
        }

        return array_values(array_filter($body, static function ($repo): bool {
            return is_array($repo)
                && ! empty($repo['name'])
                && empty($repo['fork']);
        }));
    }

    public function get_latest_release(string $repo): ?array
    {
        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            rawurlencode($this->owner),
            rawurlencode($repo)
        );

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'Yuna-WordPress-Helper/' . YWHH_VERSION,
            ],
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status !== 200) {
            return null;
        }

        $body = json_decode((string) wp_remote_retrieve_body($response), true);

        return is_array($body) ? $body : null;
    }
}
