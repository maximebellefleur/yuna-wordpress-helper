<?php

if (! defined('ABSPATH')) {
    exit;
}

class YWHH_Admin
{
    public const MANAGED_PLUGINS_OPTION = 'ywhh_managed_plugins';
    private YWHH_Access_Manager $access_manager;

    public function __construct(YWHH_Access_Manager $access_manager)
    {
        $this->access_manager = $access_manager;
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Yuna Helper', 'yuna-wordpress-helper'),
            __('Yuna Helper', 'yuna-wordpress-helper'),
            'manage_options',
            'yuna-wordpress-helper',
            [$this, 'render_page'],
            'dashicons-admin-plugins',
            59
        );
    }

    public function register_settings(): void
    {
        register_setting('ywhh_access_settings_group', YWHH_Access_Manager::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this->access_manager, 'sanitize_settings'],
            'default'           => $this->access_manager->get_settings(),
        ]);
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'yuna-wordpress-helper'));
        }

        $access_settings = $this->access_manager->get_settings();
        $last_status = $access_settings['last_status'];
        $last_message = $access_settings['last_message'];

        $catalog = [];
        $force_refresh = isset($_GET['ywhh_refresh']) && check_admin_referer('ywhh_refresh_catalog');

        if (isset($_POST['ywhh_run_install']) && check_admin_referer('ywhh_install_plugin')) {
            $this->handle_install_action();
            $force_refresh = true;
        }

        if (isset($_POST['ywhh_helper_update']) && check_admin_referer('ywhh_helper_update')) {
            $this->handle_helper_update();
            $force_refresh = true;
        }

        if ($access_settings['access_token'] !== '') {
            if ($force_refresh) {
                $has_access = $this->verify_access_or_notice();
                if (! $has_access) {
                    $force_refresh = false;
                }
            }

            if (! $force_refresh && $last_status === 'valid') {
                $has_access = true;
            } else {
                $has_access = $this->verify_access_or_notice();
            }

            if ($has_access) {
                $catalog = (new YWHH_Plugin_Catalog())->get_catalog((bool) $force_refresh);
            }
        }

        if (isset($_POST['ywhh_save_managed']) && check_admin_referer('ywhh_save_managed_plugins')) {
            $this->save_managed_plugins($catalog);
            echo '<div class="notice notice-success"><p>' . esc_html__('Saved.', 'yuna-wordpress-helper') . '</p></div>';
        }

        $managed = get_option(self::MANAGED_PLUGINS_OPTION, []);
        ?>
        <div class="wrap ywhh-wrap">
            <h1><?php esc_html_e('Yuna WordPress Helper', 'yuna-wordpress-helper'); ?></h1>
            <p><strong><?php echo esc_html(sprintf(__('Helper version: %s', 'yuna-wordpress-helper'), YWHH_VERSION)); ?></strong></p>
            <p><?php esc_html_e('Enter your token, validate it on the minisite, then manage downloadable yuna-* plugins.', 'yuna-wordpress-helper'); ?></p>

            <div class="ywhh-card">
                <h2><?php esc_html_e('Client Access Token', 'yuna-wordpress-helper'); ?></h2>
                <form method="post" action="options.php">
                    <?php settings_fields('ywhh_access_settings_group'); ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><?php esc_html_e('Access Token', 'yuna-wordpress-helper'); ?></th>
                            <td><input type="password" class="regular-text" autocomplete="new-password" name="<?php echo esc_attr(YWHH_Access_Manager::OPTION_KEY); ?>[access_token]" value="<?php echo esc_attr($access_settings['access_token']); ?>" /></td>
                        </tr>
                    </table>
                    <?php submit_button(__('Save Token', 'yuna-wordpress-helper')); ?>
                </form>
                <?php if ($last_status !== '') : ?>
                    <p>
                        <?php
                        echo esc_html(
                            sprintf(
                                __('Last check: %1$s (%2$s)', 'yuna-wordpress-helper'),
                                $access_settings['last_check'] ?: __('never', 'yuna-wordpress-helper'),
                                $last_status
                            )
                        );
                        ?>
                    </p>
                    <?php if ($last_message !== '') : ?>
                        <p><em><?php echo esc_html($last_message); ?></em></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ($access_settings['access_token'] !== '') : ?>
                <div class="ywhh-card">
                    <p>
                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=yuna-wordpress-helper&ywhh_refresh=1'), 'ywhh_refresh_catalog')); ?>"><?php esc_html_e('Refresh', 'yuna-wordpress-helper'); ?></a>
                    </p>

                    <?php if (empty($catalog)) : ?>
                        <p><?php esc_html_e('No matching released repositories found (must contain yuna-).', 'yuna-wordpress-helper'); ?></p>
                    <?php else : ?>
                        <form method="post">
                            <?php wp_nonce_field('ywhh_save_managed_plugins'); ?>
                            <?php wp_nonce_field('ywhh_install_plugin'); ?>
                            <table class="widefat striped ywhh-table">
                                <thead>
                                <tr>
                                    <th><?php esc_html_e('Use', 'yuna-wordpress-helper'); ?></th>
                                    <th><?php esc_html_e('Plugin', 'yuna-wordpress-helper'); ?></th>
                                    <th><?php esc_html_e('Installed', 'yuna-wordpress-helper'); ?></th>
                                    <th><?php esc_html_e('Latest', 'yuna-wordpress-helper'); ?></th>
                                    <th><?php esc_html_e('Auto-update', 'yuna-wordpress-helper'); ?></th>
                                    <th><?php esc_html_e('Action', 'yuna-wordpress-helper'); ?></th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($catalog as $item) :
                                    $repo_url = $item['repo_url'];
                                    $state = is_array($managed) ? ($managed[$repo_url] ?? []) : [];
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" name="ywhh_managed[<?php echo esc_attr($repo_url); ?>][enabled]" <?php checked(! empty($state['enabled'])); ?> /></td>
                                        <td><strong><?php echo esc_html($item['name']); ?></strong></td>
                                        <td><?php echo $item['installed_version'] ? '<code>' . esc_html($item['installed_version']) . '</code>' : '<em>' . esc_html__('No', 'yuna-wordpress-helper') . '</em>'; ?></td>
                                        <td><code><?php echo esc_html($item['latest_version']); ?></code></td>
                                        <td><input type="checkbox" name="ywhh_managed[<?php echo esc_attr($repo_url); ?>][auto_update]" <?php checked(! empty($state['auto_update'])); ?> /></td>
                                        <td>
                                            <button type="submit" name="ywhh_run_install" value="<?php echo esc_attr($repo_url); ?>" class="button button-primary"><?php esc_html_e('Install / Update + Activate', 'yuna-wordpress-helper'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p><button type="submit" name="ywhh_save_managed" class="button button-secondary"><?php esc_html_e('Save Selection', 'yuna-wordpress-helper'); ?></button></p>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="ywhh-card">
                <h2><?php esc_html_e('Need to update the helper?', 'yuna-wordpress-helper'); ?></h2>
                <form method="post">
                    <?php wp_nonce_field('ywhh_helper_update'); ?>
                    <button type="submit" name="ywhh_helper_update" class="button button-primary"><?php esc_html_e('Click here to run helper update', 'yuna-wordpress-helper'); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    private function handle_install_action(): void
    {
        $access_settings = $this->access_manager->get_settings();
        if ($access_settings['access_token'] === '') {
            echo '<div class="notice notice-error"><p>' . esc_html__('Save token first.', 'yuna-wordpress-helper') . '</p></div>';

            return;
        }

        if (! $this->verify_access_or_notice()) {
            return;
        }

        $repo_url = sanitize_text_field((string) ($_POST['ywhh_run_install'] ?? ''));
        $catalog = (new YWHH_Plugin_Catalog())->get_catalog(true);

        $target = null;
        foreach ($catalog as $item) {
            if ((string) $item['repo_url'] === $repo_url) {
                $target = $item;
                break;
            }
        }

        if (! is_array($target) || empty($target['download_url'])) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Package not found.', 'yuna-wordpress-helper') . '</p></div>';

            return;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        WP_Filesystem();

        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        $result = $upgrader->install((string) $target['download_url'], ['overwrite_package' => true]);

        if (! $result || is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Install/update failed.', 'yuna-wordpress-helper') . '</p></div>';

            return;
        }

        $installed_file = $this->find_plugin_file_by_repo_url((string) $target['repo_url']);
        if ($installed_file !== '' && ! is_plugin_active($installed_file)) {
            activate_plugin($installed_file);
        }

        echo '<div class="notice notice-success"><p>' . esc_html__('Plugin installed/updated and activated.', 'yuna-wordpress-helper') . '</p></div>';
    }

    private function handle_helper_update(): void
    {
        if (! $this->verify_access_or_notice()) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        wp_update_plugins();
        $updates = get_site_transient('update_plugins');
        $plugin_file = plugin_basename(YWHH_PLUGIN_FILE);

        if (! isset($updates->response[$plugin_file])) {
            echo '<div class="notice notice-info"><p>' . esc_html__('Helper is already up to date.', 'yuna-wordpress-helper') . '</p></div>';

            return;
        }

        $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
        $result = $upgrader->upgrade($plugin_file);

        if (! $result || is_wp_error($result)) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Helper update failed.', 'yuna-wordpress-helper') . '</p></div>';

            return;
        }

        echo '<div class="notice notice-success"><p>' . esc_html__('Helper updated successfully.', 'yuna-wordpress-helper') . '</p></div>';
    }

    private function find_plugin_file_by_repo_url(string $repo_url): string
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        foreach (get_plugins() as $file => $plugin) {
            if (trim((string) ($plugin['UpdateURI'] ?? '')) === $repo_url) {
                return $file;
            }
        }

        return '';
    }

    private function save_managed_plugins(array $catalog): void
    {
        $input = isset($_POST['ywhh_managed']) && is_array($_POST['ywhh_managed']) ? wp_unslash($_POST['ywhh_managed']) : [];

        $managed = [];
        foreach ($catalog as $item) {
            $repo_url = (string) ($item['repo_url'] ?? '');
            if ($repo_url === '') {
                continue;
            }

            $row = is_array($input[$repo_url] ?? null) ? $input[$repo_url] : [];
            $enabled = ! empty($row['enabled']);
            $auto = ! empty($row['auto_update']);

            $managed[$repo_url] = [
                'enabled' => $enabled ? 1 : 0,
                'auto_update' => $enabled && $auto ? 1 : 0,
            ];
        }

        update_option(self::MANAGED_PLUGINS_OPTION, $managed, false);
        $this->sync_native_auto_updates($managed);
    }

    private function sync_native_auto_updates(array $managed): void
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $auto_updates = get_site_option('auto_update_plugins', []);
        if (! is_array($auto_updates)) {
            $auto_updates = [];
        }

        $auto_map = array_fill_keys($auto_updates, true);

        foreach (get_plugins() as $file => $plugin) {
            $update_uri = trim((string) ($plugin['UpdateURI'] ?? ''));
            if ($update_uri === '') {
                continue;
            }

            $state = $managed[$update_uri] ?? null;
            if (is_array($state) && ! empty($state['enabled']) && ! empty($state['auto_update'])) {
                $auto_map[$file] = true;
            } else {
                unset($auto_map[$file]);
            }
        }

        update_site_option('auto_update_plugins', array_values(array_keys($auto_map)));
    }

    private function verify_access_or_notice(): bool
    {
        if ($this->access_manager->perform_token_check()) {
            return true;
        }

        $access = $this->access_manager->get_settings();
        $reason = $access['last_message'] ?: __('Token is not valid for this action.', 'yuna-wordpress-helper');
        echo '<div class="notice notice-error"><p>' . esc_html($reason) . '</p></div>';

        return false;
    }
}
