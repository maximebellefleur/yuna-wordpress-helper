<?php

if (! defined('ABSPATH')) {
    exit;
}

class YWHH_Admin
{
    private const OPTION_KEY = 'ywhh_settings';

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
        register_setting('ywhh_settings_group', self::OPTION_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default'           => [
                'github_owner' => '',
            ],
        ]);

        add_settings_section(
            'ywhh_main_section',
            __('GitHub Source', 'yuna-wordpress-helper'),
            static function (): void {
                echo '<p>' . esc_html__('Set your public GitHub username or organization. The plugin will pull repositories and latest releases.', 'yuna-wordpress-helper') . '</p>';
            },
            'ywhh_settings_group'
        );

        add_settings_field(
            'github_owner',
            __('GitHub Owner', 'yuna-wordpress-helper'),
            [$this, 'render_owner_field'],
            'ywhh_settings_group',
            'ywhh_main_section'
        );
    }

    public function sanitize_settings(array $input): array
    {
        $owner = sanitize_text_field((string) ($input['github_owner'] ?? ''));
        $owner = preg_replace('/[^a-zA-Z0-9-]/', '', $owner) ?: '';

        return [
            'github_owner' => $owner,
        ];
    }

    public function render_owner_field(): void
    {
        $settings = $this->get_settings();
        ?>
        <input
            type="text"
            class="regular-text"
            name="<?php echo esc_attr(self::OPTION_KEY); ?>[github_owner]"
            value="<?php echo esc_attr($settings['github_owner']); ?>"
            placeholder="your-github-user"
        />
        <?php
    }

    public function render_page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to access this page.', 'yuna-wordpress-helper'));
        }

        $settings      = $this->get_settings();
        $owner         = $settings['github_owner'];
        $force_refresh = isset($_GET['ywhh_refresh']) && check_admin_referer('ywhh_refresh_catalog');
        $catalog       = [];

        if ($owner !== '') {
            $catalog = (new YWHH_Plugin_Catalog())->get_catalog($owner, (bool) $force_refresh);
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Yuna WordPress Helper', 'yuna-wordpress-helper'); ?></h1>
            <p><?php esc_html_e('Central place to discover your GitHub plugins, track version differences, and open the latest downloadable package.', 'yuna-wordpress-helper'); ?></p>

            <form method="post" action="options.php">
                <?php
                settings_fields('ywhh_settings_group');
                do_settings_sections('ywhh_settings_group');
                submit_button(__('Save Settings', 'yuna-wordpress-helper'));
                ?>
            </form>

            <?php if ($owner !== '') : ?>
                <hr />
                <h2><?php esc_html_e('Plugin Catalog', 'yuna-wordpress-helper'); ?></h2>
                <p>
                    <?php esc_html_e('Tip: in each managed plugin, set "Update URI" in the plugin header to its GitHub repository URL (e.g. https://github.com/OWNER/REPO) so installed-version matching works.', 'yuna-wordpress-helper'); ?>
                </p>

                <p>
                    <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=yuna-wordpress-helper&ywhh_refresh=1'), 'ywhh_refresh_catalog')); ?>">
                        <?php esc_html_e('Refresh Catalog', 'yuna-wordpress-helper'); ?>
                    </a>
                </p>

                <?php if (empty($catalog)) : ?>
                    <p><?php esc_html_e('No released repositories found. Create at least one GitHub release in your public repositories.', 'yuna-wordpress-helper'); ?></p>
                <?php else : ?>
                    <table class="widefat striped">
                        <thead>
                        <tr>
                            <th><?php esc_html_e('Plugin', 'yuna-wordpress-helper'); ?></th>
                            <th><?php esc_html_e('Installed', 'yuna-wordpress-helper'); ?></th>
                            <th><?php esc_html_e('Latest', 'yuna-wordpress-helper'); ?></th>
                            <th><?php esc_html_e('Release Log', 'yuna-wordpress-helper'); ?></th>
                            <th><?php esc_html_e('Actions', 'yuna-wordpress-helper'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($catalog as $item) : ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($item['name']); ?></strong><br />
                                    <span><?php echo esc_html($item['description']); ?></span><br />
                                    <a href="<?php echo esc_url($item['repo_url']); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html($item['repo_url']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if (! empty($item['installed_version'])) : ?>
                                        <code><?php echo esc_html($item['installed_version']); ?></code>
                                        <?php if (! empty($item['update_available'])) : ?>
                                            <span style="color:#b32d2e; font-weight:600;">
                                                <?php esc_html_e('Update available', 'yuna-wordpress-helper'); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <em><?php esc_html_e('Not detected', 'yuna-wordpress-helper'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <code><?php echo esc_html($item['latest_version']); ?></code><br />
                                    <small><?php echo esc_html($this->format_date($item['release_date'])); ?></small>
                                </td>
                                <td style="max-width: 420px;">
                                    <details>
                                        <summary><?php esc_html_e('Show release notes', 'yuna-wordpress-helper'); ?></summary>
                                        <pre style="white-space: pre-wrap;"><?php echo esc_html($this->trim_release_notes($item['release_notes'])); ?></pre>
                                    </details>
                                </td>
                                <td>
                                    <?php if ($item['download_url']) : ?>
                                        <a class="button button-primary" href="<?php echo esc_url($item['download_url']); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php esc_html_e('Download ZIP', 'yuna-wordpress-helper'); ?>
                                        </a>
                                    <?php else : ?>
                                        <em><?php esc_html_e('No ZIP found', 'yuna-wordpress-helper'); ?></em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_settings(): array
    {
        $settings = get_option(self::OPTION_KEY, []);

        return [
            'github_owner' => (string) ($settings['github_owner'] ?? ''),
        ];
    }

    private function trim_release_notes(string $notes): string
    {
        $notes = trim($notes);

        if ($notes === '') {
            return __('No release notes provided on GitHub.', 'yuna-wordpress-helper');
        }

        return mb_strimwidth($notes, 0, 1000, "\n...", 'UTF-8');
    }

    private function format_date(string $date): string
    {
        if ($date === '') {
            return __('Unknown publish date', 'yuna-wordpress-helper');
        }

        $timestamp = strtotime($date);
        if (! $timestamp) {
            return __('Unknown publish date', 'yuna-wordpress-helper');
        }

        return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $timestamp);
    }
}
