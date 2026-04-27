<?php

if (! defined('ABSPATH')) {
    exit;
}

class YWHH_Plugin_Catalog
{
    private const CACHE_KEY = 'ywhh_catalog_cache';
    private const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    public function get_catalog(bool $force_refresh = false, array $repository_urls = []): array
    {
        $repository_urls = $this->sanitize_repository_urls($repository_urls);
        if (empty($repository_urls)) {
            return [];
        }

        $cache_key = self::CACHE_KEY . '_' . md5(implode('|', $repository_urls));

        if (! $force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $client = new YWHH_GitHub_Client();
        $repos  = [];
        foreach ($repository_urls as $repository_url) {
            $parts = $this->parse_github_repository_url($repository_url);
            if (! $parts) {
                continue;
            }

            $repo = $client->get_repository($parts['owner'], $parts['repo']);
            if (is_array($repo)) {
                $repos[] = $repo;
            } else {
                $repos[] = [
                    'name'        => $parts['repo'],
                    'description' => '',
                    'html_url'    => $repository_url,
                    'owner'       => [
                        'login' => $parts['owner'],
                    ],
                ];
            }
        }

        $installed_map = $this->get_installed_plugin_map();
        $catalog       = [];

        foreach ($repos as $repo) {
            $repo_name = (string) ($repo['name'] ?? '');
            $owner = (string) ($repo['owner']['login'] ?? '');
            if ($repo_name === '' || $owner === '') {
                continue;
            }

            $release          = $client->get_latest_release($owner, $repo_name);
            $tag              = is_array($release) ? (string) ($release['tag_name'] ?? '') : '';
            $normalized_tag   = ltrim($tag, 'vV');
            $repo_html_url    = (string) ($repo['html_url'] ?? '');
            $repo_slug        = $this->normalize_plugin_slug($repo_name);
            $installed_plugin = $installed_map[$repo_html_url]
                ?? $installed_map[untrailingslashit($repo_html_url)]
                ?? $installed_map[$repo_slug]
                ?? null;

            $catalog[] = [
                'name'              => $repo_name,
                'description'       => (string) ($repo['description'] ?? ''),
                'repo_url'          => $repo_html_url,
                'latest_version'    => $normalized_tag,
                'release_date'      => is_array($release) ? (string) ($release['published_at'] ?? '') : '',
                'download_url'      => is_array($release) ? $this->resolve_download_url($release) : '',
                'installed_version' => $installed_plugin['version'] ?? null,
                'installed_file'    => $installed_plugin['file'] ?? null,
                'update_available'  => $this->is_update_available(
                    $installed_plugin['version'] ?? null,
                    $normalized_tag
                ),
            ];
        }

        usort($catalog, static function (array $a, array $b): int {
            return strcmp($a['name'], $b['name']);
        });

        set_transient($cache_key, $catalog, self::CACHE_TTL);

        return $catalog;
    }

    private function resolve_download_url(array $release): string
    {
        if (! empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (! is_array($asset)) {
                    continue;
                }

                $url = (string) ($asset['browser_download_url'] ?? '');
                if ($url !== '' && $this->ends_with(strtolower($url), '.zip')) {
                    return $url;
                }
            }
        }

        return (string) ($release['zipball_url'] ?? '');
    }

    private function get_installed_plugin_map(): array
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $map     = [];

        foreach ($plugins as $file => $plugin) {
            $update_uri = trim((string) ($plugin['UpdateURI'] ?? ''));
            $plugin_slug = $this->normalize_plugin_slug((string) dirname((string) $file));
            $file_slug = $this->normalize_plugin_slug(basename((string) $file, '.php'));
            $name_slug = $this->normalize_plugin_slug((string) ($plugin['Name'] ?? ''));

            if (
                ! preg_match('#^yuna-[a-z0-9._-]+$#', $plugin_slug)
                && ! preg_match('#^yuna-[a-z0-9._-]+$#', $file_slug)
                && ! preg_match('#^yuna-[a-z0-9._-]+$#', $name_slug)
            ) {
                continue;
            }

            $plugin_info = [
                'file'    => $file,
                'version' => (string) ($plugin['Version'] ?? ''),
            ];

            foreach ([$plugin_slug, $file_slug, $name_slug] as $slug) {
                if (preg_match('#^yuna-[a-z0-9._-]+$#', $slug)) {
                    $map[$slug] = $plugin_info;
                }
            }

            if ($update_uri !== '') {
                $normalized_update_uri = untrailingslashit($update_uri);
                $map[$normalized_update_uri] = $plugin_info;

                $update_uri_path = (string) wp_parse_url($normalized_update_uri, PHP_URL_PATH);
                $update_uri_parts = array_values(array_filter(explode('/', trim($update_uri_path, '/'))));
                $update_uri_slug = $this->normalize_plugin_slug((string) end($update_uri_parts));
                if (preg_match('#^yuna-[a-z0-9._-]+$#', $update_uri_slug)) {
                    $map[$update_uri_slug] = $plugin_info;
                }
            }
        }

        return $map;
    }

    private function sanitize_repository_urls(array $repository_urls): array
    {
        $urls = [];

        foreach ($repository_urls as $repository_url) {
            $url = esc_url_raw((string) $repository_url);
            if (! preg_match('#^https://github\.com/maximebellefleur/yuna-[a-z0-9._-]+/?$#i', $url)) {
                continue;
            }

            if (preg_match('#/yuna-wordpress-helper/?$#i', $url)) {
                continue;
            }

            $urls[] = untrailingslashit($url);
        }

        sort($urls);

        return array_values(array_unique($urls));
    }

    private function parse_github_repository_url(string $repository_url): ?array
    {
        $path = (string) wp_parse_url($repository_url, PHP_URL_PATH);
        $parts = array_values(array_filter(explode('/', trim($path, '/'))));

        if (count($parts) !== 2) {
            return null;
        }

        return [
            'owner' => $parts[0],
            'repo'  => $parts[1],
        ];
    }

    private function normalize_plugin_slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('#\.php$#', '', $slug);
        $slug = preg_replace('#[^a-z0-9]+#', '-', $slug);
        $slug = trim((string) $slug, '-');

        if (preg_match('#^(yuna-[a-z0-9-]*?[a-z])(?:[-_.]?(?:v)?\d+(?:[-_.]\d+)*)?$#', $slug, $matches)) {
            return $matches[1];
        }

        return $slug;
    }

    private function ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        return substr($haystack, -strlen($needle)) === $needle;
    }

    private function is_update_available(?string $installed, string $latest): bool
    {
        if (! $installed || $latest === '') {
            return false;
        }

        return version_compare($installed, $latest, '<');
    }
}
