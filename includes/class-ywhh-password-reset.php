<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Sends password reset emails for WordPress users.
 *
 * Usage:
 *   $result = YWHH_Password_Reset::send_reset_email('user@example.com');
 *   if (is_wp_error($result)) { echo $result->get_error_message(); }
 */
class YWHH_Password_Reset
{
    /**
     * Generate a reset key for the given user and email them a reset link.
     *
     * @param string|int|WP_User $user  User login, email address, ID or WP_User object.
     * @param string             $intro Optional extra text placed at the top of the email body.
     *
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public static function send_reset_email($user, string $intro = '')
    {
        $user = self::resolve_user($user);
        if (is_wp_error($user)) {
            return $user;
        }

        if (! function_exists('get_password_reset_key')) {
            require_once ABSPATH . 'wp-includes/user.php';
        }

        $key = get_password_reset_key($user);
        if (is_wp_error($key)) {
            return $key;
        }

        $reset_url = network_site_url(
            'wp-login.php?action=rp&key=' . rawurlencode($key) . '&login=' . rawurlencode($user->user_login),
            'login'
        );

        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);

        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Password Reset', 'yuna-wordpress-helper'),
            $site_name
        );

        $lines = [];
        if ($intro !== '') {
            $lines[] = wp_strip_all_tags($intro);
            $lines[] = '';
        }
        $lines[] = __('Someone has requested a password reset for the following account:', 'yuna-wordpress-helper');
        $lines[] = '';
        $lines[] = sprintf(__('Site Name: %s', 'yuna-wordpress-helper'), $site_name);
        $lines[] = sprintf(__('Username: %s', 'yuna-wordpress-helper'), $user->user_login);
        $lines[] = '';
        $lines[] = __('If this was a mistake, just ignore this email and nothing will happen.', 'yuna-wordpress-helper');
        $lines[] = '';
        $lines[] = __('To reset your password, visit the following address:', 'yuna-wordpress-helper');
        $lines[] = $reset_url;
        $lines[] = '';
        $lines[] = sprintf(__('This link will expire in %s.', 'yuna-wordpress-helper'), self::get_expiry_label());

        $message = implode("\r\n", $lines);

        $subject = (string) apply_filters('ywhh_password_reset_subject', $subject, $user, $reset_url);
        $message = (string) apply_filters('ywhh_password_reset_message', $message, $user, $reset_url, $key);
        $headers = (array) apply_filters('ywhh_password_reset_headers', [], $user);

        $sent = wp_mail($user->user_email, wp_specialchars_decode($subject), $message, $headers);
        if (! $sent) {
            return new WP_Error(
                'ywhh_reset_email_failed',
                __('The password reset email could not be sent.', 'yuna-wordpress-helper')
            );
        }

        do_action('ywhh_password_reset_email_sent', $user, $reset_url);

        return true;
    }

    /**
     * Accept a login, email, ID or WP_User and return a WP_User or WP_Error.
     *
     * @param string|int|WP_User $user
     *
     * @return WP_User|WP_Error
     */
    private static function resolve_user($user)
    {
        if ($user instanceof WP_User) {
            return $user->exists()
                ? $user
                : new WP_Error('ywhh_invalid_user', __('Invalid user.', 'yuna-wordpress-helper'));
        }

        if (is_int($user) || (is_string($user) && ctype_digit($user))) {
            $found = get_user_by('id', (int) $user);
        } else {
            $user = trim((string) $user);
            if ($user === '') {
                return new WP_Error('ywhh_empty_user', __('Enter a username or email address.', 'yuna-wordpress-helper'));
            }

            $found = is_email($user)
                ? get_user_by('email', $user)
                : get_user_by('login', $user);
        }

        if (! $found instanceof WP_User) {
            return new WP_Error(
                'ywhh_user_not_found',
                __('There is no account with that username or email address.', 'yuna-wordpress-helper')
            );
        }

        return $found;
    }

    private static function get_expiry_label(): string
    {
        $seconds = (int) apply_filters('password_reset_expiration', DAY_IN_SECONDS);
        if ($seconds <= 0) {
            $seconds = DAY_IN_SECONDS;
        }

        return human_time_diff(time(), time() + $seconds);
    }
}

if (! function_exists('ywhh_send_password_reset_email')) {
    /**
     * Procedural shortcut for YWHH_Password_Reset::send_reset_email().
     *
     * @param string|int|WP_User $user  User login, email, ID or WP_User.
     * @param string             $intro Optional extra text at the top of the email.
     *
     * @return true|WP_Error
     */
    function ywhh_send_password_reset_email($user, string $intro = '')
    {
        return YWHH_Password_Reset::send_reset_email($user, $intro);
    }
}
