<?php
/**
 * Plugin Name: Yuna WordPress Helper
 * Plugin URI: https://github.com/
 * Description: Manage and monitor WordPress plugins published in a public GitHub account.
 * Version: 0.1.0
 * Author: Yuna
 * License: GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (! defined('ABSPATH')) {
    exit;
}

define('YWHH_VERSION', '0.1.0');
define('YWHH_PLUGIN_FILE', __FILE__);
define('YWHH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YWHH_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once YWHH_PLUGIN_DIR . 'includes/class-ywhh-github-client.php';
require_once YWHH_PLUGIN_DIR . 'includes/class-ywhh-plugin-catalog.php';
require_once YWHH_PLUGIN_DIR . 'includes/class-ywhh-admin.php';

final class YWHH_Plugin
{
    public function boot(): void
    {
        add_action('admin_menu', [$this, 'register_admin']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function register_admin(): void
    {
        (new YWHH_Admin())->register_menu();
    }

    public function register_settings(): void
    {
        (new YWHH_Admin())->register_settings();
    }
}

add_action('plugins_loaded', static function (): void {
    (new YWHH_Plugin())->boot();
});
