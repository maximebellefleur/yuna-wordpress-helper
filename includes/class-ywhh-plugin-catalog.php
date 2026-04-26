<?php

if (! defined('ABSPATH')) {
    exit;
}

class YWHH_Plugin_Catalog
{
    private const CACHE_KEY = 'ywhh_catalog_cache';
    private const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    public function get_catalog(string $token, bool $force_refresh = false): array
    {
        $cache_key = self::CACHE_KEY . '_' . md5($token);

        if (! $force_refresh) {
            $cached = get_transient($cache_key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $client = new YWHH_GitHub_Client($token);
        $repos  = $client->get_repositories();

        $installed_map = $this->get_installed_plugin_map();
        $catalog       = [];

        foreach ($repos as $repo) {
            $repo_name = (string) ($repo['name'] ?? '');
            $owner = (string) ($repo['owner']['login'] ?? '');
            if ($repo_name === '' || $owner === '') {
                continue;
            }

            $release = $client->get_latest_release($owner, $repo_name);
            if (! $release) {
                continue;
            }

            $tag              = (string) ($release['tag_name'] ?? '');
            $normalized_tag   = ltrim($tag, 'vV');
            $repo_html_url    = (string) ($repo['html_url'] ?? '');
            $installed_plugin = $installed_map[$repo_html_url] ?? null;

            $catalog[] = [
                'name'              => $repo_name,
                'description'       => (string) ($repo['description'] ?? ''),
                'repo_url'          => $repo_html_url,
                'latest_version'    => $normalized_tag,
                'release_date'      => (string) ($release['published_at'] ?? ''),
                'download_url'      => $this->resolve_download_url($release),
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
            if ($update_uri === '' || ! preg_match('#^https://github\.com/maximebellefleur/yuna-[a-z0-9._-]+/?$#i', $update_uri)) {
                continue;
            }

            $map[$update_uri] = [
                'file'    => $file,
                'version' => (string) ($plugin['Version'] ?? ''),
            ];
        }

        return $map;
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
