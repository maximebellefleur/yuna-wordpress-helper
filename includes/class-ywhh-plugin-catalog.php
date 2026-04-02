<?php

if (! defined('ABSPATH')) {
    exit;
}

class YWHH_Plugin_Catalog
{
    private const CACHE_KEY = 'ywhh_catalog_cache';
    private const CACHE_TTL = 10 * MINUTE_IN_SECONDS;

    public function get_catalog(string $owner, bool $force_refresh = false): array
    {
        if (! $force_refresh) {
            $cached = get_transient(self::CACHE_KEY . '_' . md5($owner));
            if (is_array($cached)) {
                return $cached;
            }
        }

        $client = new YWHH_GitHub_Client($owner);
        $repos  = $client->get_repositories();

        $installed_map = $this->get_installed_plugin_map();
        $catalog       = [];

        foreach ($repos as $repo) {
            $repo_name = (string) ($repo['name'] ?? '');
            if ($repo_name === '') {
                continue;
            }

            $release = $client->get_latest_release($repo_name);
            if (! $release) {
                continue;
            }

            $tag             = (string) ($release['tag_name'] ?? '');
            $normalized_tag  = ltrim($tag, 'vV');
            $repo_html_url   = (string) ($repo['html_url'] ?? '');
            $installed_plugin = $installed_map[$repo_html_url] ?? null;

            $catalog[] = [
                'name'              => (string) ($repo['name'] ?? ''),
                'description'       => (string) ($repo['description'] ?? ''),
                'repo_url'          => $repo_html_url,
                'latest_version'    => $normalized_tag,
                'latest_tag'        => $tag,
                'release_date'      => (string) ($release['published_at'] ?? ''),
                'release_notes'     => (string) ($release['body'] ?? ''),
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

        set_transient(self::CACHE_KEY . '_' . md5($owner), $catalog, self::CACHE_TTL);

        return $catalog;
    }

    private function resolve_download_url(array $release): string
    {
        if (! empty($release['assets']) && is_array($release['assets'])) {
            foreach ($release['assets'] as $asset) {
                if (! is_array($asset)) {
                    continue;
                }

                $browser_download_url = (string) ($asset['browser_download_url'] ?? '');
                if ($browser_download_url !== '' && $this->ends_with(strtolower($browser_download_url), '.zip')) {
                    return $browser_download_url;
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
            if ($update_uri === '' || ! $this->starts_with($update_uri, 'https://github.com/')) {
                continue;
            }

            $map[$update_uri] = [
                'file'    => $file,
                'version' => (string) ($plugin['Version'] ?? ''),
            ];
        }

        return $map;
    }

    private function is_update_available(?string $installed, string $latest): bool
    {
        if (! $installed || $latest === '') {
            return false;
        }

        return version_compare($installed, $latest, '<');
    }

    private function starts_with(string $haystack, string $needle): bool
    {
        return $needle === '' || strpos($haystack, $needle) === 0;
    }

    private function ends_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $length = strlen($needle);

        return substr($haystack, -$length) === $needle;
    }
}
