<?php
/**
 * Plugin Name: Yuna WordPress Helper
 * Plugin URI: https://maximebellefleur.com/yunadesign
 * Description: Manage yuna-* plugin repositories from one simple admin page.
 * Version: 0.3.6
 * Author: Yuna
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('YWHH_VERSION', '0.3.6');
define('YWHH_PLUGIN_FILE', __FILE__);
define('YWHH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YWHH_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once YWHH_PLUGIN_DIR . 'includes/class-ywhh-github-client.php';
require_once YWHH_PLUGIN_DIR . 'includes/class-ywhh-plugin-catalog.php';
require_once YWHH_PLUGIN_DIR . 'includes/class-ywhh-access-manager.php';
require_once YWHH_PLUGIN_DIR . 'includes/class-ywhh-admin.php';

final class YWHH_Plugin
{
    private YWHH_Admin $admin;
    private YWHH_Access_Manager $access_manager;

    public function __construct()
    {
        $this->access_manager = new YWHH_Access_Manager();
        $this->admin = new YWHH_Admin($this->access_manager);
    }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'register_admin']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'maybe_redirect_after_activation']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_filter('auto_update_plugin', [$this, 'sync_auto_update_setting'], 10, 2);
        $this->access_manager->register_hooks();
    }

    public static function activate(): void
    {
        YWHH_Access_Manager::activate();
        set_transient('ywhh_do_activation_redirect', '1', 30);
    }

    public static function deactivate(): void
    {
        YWHH_Access_Manager::deactivate();
    }

    public function register_admin(): void
    {
        $this->admin->register_menu();
    }

    public function register_settings(): void
    {
        $this->admin->register_settings();
    }

    public function maybe_redirect_after_activation(): void
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }

        if (get_transient('ywhh_do_activation_redirect') !== '1') {
            return;
        }

        delete_transient('ywhh_do_activation_redirect');
        wp_safe_redirect(admin_url('admin.php?page=yuna-wordpress-helper'));
        exit;
    }

    public function enqueue_admin_assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_yuna-wordpress-helper') {
            return;
        }

        wp_enqueue_style('ywhh-admin-style', YWHH_PLUGIN_URL . 'assets/ywhh-admin.css', [], YWHH_VERSION);
    }

    public function sync_auto_update_setting(bool $update, $item): bool
    {
        if (! is_object($item) || empty($item->plugin)) {
            return $update;
        }

        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugins = get_plugins();
        $plugin_data = $plugins[$item->plugin] ?? null;
        if (! is_array($plugin_data)) {
            return $update;
        }

        $update_uri = trim((string) ($plugin_data['UpdateURI'] ?? ''));
        if ($update_uri === '') {
            return $update;
        }

        $managed = get_option(YWHH_Admin::MANAGED_PLUGINS_OPTION, []);
        $state = is_array($managed) ? ($managed[$update_uri] ?? null) : null;

        return is_array($state) && ! empty($state['enabled']) && ! empty($state['auto_update']);
    }
}

register_activation_hook(__FILE__, ['YWHH_Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['YWHH_Plugin', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    (new YWHH_Plugin())->boot();
});
