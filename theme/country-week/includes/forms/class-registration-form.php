<?php
/**
 * Free account registration gating downloadable resources (slides,
 * printable sheets) behind a login — collects a name and email so the
 * ministry knows who's using these resources, without adding any
 * external auth dependency.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Forms;

use CountryWeek\Services\Subscriber_Meta_Fields;
use CountryWeek\Utilities\Date_Utility;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Uses only native WordPress user functions (wp_insert_user(),
 * wp_set_auth_cookie()) — no membership plugin. New accounts get the
 * built-in "subscriber" role, which has no wp-admin/editing
 * capabilities; it exists purely so is_user_logged_in() can gate
 * resource downloads. Same honeypot/timing/nonce spam protection as
 * the other forms on this site.
 *
 * Also captures a time zone (defaulting to the site's own, pre-selected
 * automatically when JS can detect the visitor's real one — see
 * assets/js/main.js's initTimezoneAutodetect()) so the weekly upcoming-
 * country preview email (Services\Subscriber_Notifier) can be sent
 * around local noon on Saturday rather than only the site's fixed
 * America/New_York clock. New subscribers are opted into that email by
 * default (Subscriber_Meta_Fields::OPT_IN_META_KEY's registered
 * default) — see docs/decisions/0002-per-subscriber-timezone-weekly-email.md.
 */
class Registration_Form
{
    private const NONCE_ACTION = 'country_week_register';
    private const NONCE_FIELD = 'country_week_register_nonce';
    private const HONEYPOT_FIELD = 'cw_website';
    private const TIMING_FIELD = 'cw_rendered_at';
    private const MIN_SECONDS_BEFORE_SUBMIT = 3;

    public function register(): void
    {
        add_action('admin_post_country_week_register', [$this, 'handle_submission']);
        add_action('admin_post_nopriv_country_week_register', [$this, 'handle_submission']);
    }

    public function render(string $redirect_to = ''): string
    {
        if (is_user_logged_in()) {
            return $this->already_logged_in_notice();
        }

        $action_url = admin_url('admin-post.php');
        $error = isset($_GET['register_error']) ? sanitize_key(wp_unslash($_GET['register_error'])) : '';

        if (!function_exists('wp_timezone_choice')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }

        ob_start();
        ?>
        <div class="account-form">
            <?php if ($error !== '') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--error">
                    <?php echo esc_html($this->error_message($error)); ?>
                </p>
            <?php endif; ?>

            <form class="suggest-edit__form" method="post" action="<?php echo esc_url($action_url); ?>">
                <input type="hidden" name="action" value="country_week_register">
                <input type="hidden" name="redirect_to" value="<?php echo esc_url($redirect_to); ?>">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="<?php echo esc_attr(self::TIMING_FIELD); ?>" value="<?php echo esc_attr((string) time()); ?>">

                <p class="suggest-edit__honeypot" aria-hidden="true">
                    <label for="cw_reg_website"><?php esc_html_e('Website', 'country-week'); ?></label>
                    <input type="text" id="cw_reg_website" name="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>" tabindex="-1" autocomplete="off">
                </p>

                <p>
                    <label for="cw_reg_name"><?php esc_html_e('Name', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <input type="text" id="cw_reg_name" name="cw_reg_name" required>
                </p>

                <p>
                    <label for="cw_reg_email"><?php esc_html_e('Email', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <input type="email" id="cw_reg_email" name="cw_reg_email" required>
                </p>

                <p>
                    <label for="cw_reg_password"><?php esc_html_e('Password', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <input type="password" id="cw_reg_password" name="cw_reg_password" minlength="8" required>
                </p>

                <p>
                    <label for="cw_reg_timezone"><?php esc_html_e('Time Zone', 'country-week'); ?></label><br>
                    <select id="cw_reg_timezone" name="cw_reg_timezone">
                        <?php echo wp_timezone_choice(Date_Utility::SITE_TIMEZONE); ?>
                    </select>
                    <?php esc_html_e('(used to send the weekly upcoming-country email around midday Saturday)', 'country-week'); ?>
                </p>

                <p>
                    <button type="submit"><?php esc_html_e('Create Free Account', 'country-week'); ?></button>
                </p>

                <p class="account-form__alt-link">
                    <?php
                    printf(
                        /* translators: %s: login link. */
                        esc_html__('Already have an account? %s', 'country-week'),
                        '<a href="' . esc_url(add_query_arg('redirect_to', $redirect_to, home_url('/login/'))) . '">' . esc_html__('Log in', 'country-week') . '</a>'
                    );
                    ?>
                </p>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function already_logged_in_notice(): string
    {
        $user = wp_get_current_user();

        ob_start();
        ?>
        <p class="suggest-edit__notice suggest-edit__notice--success">
            <?php
            printf(
                /* translators: %s: display name. */
                esc_html__('You\'re signed in as %s.', 'country-week'),
                esc_html($user->display_name)
            );
            ?>
            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Log out', 'country-week'); ?></a>
        </p>
        <?php
        return (string) ob_get_clean();
    }

    private function error_message(string $code): string
    {
        return match ($code) {
            'email_exists' => __('An account with that email already exists. Try logging in instead.', 'country-week'),
            'invalid_email' => __('Please enter a valid email address.', 'country-week'),
            'weak_password' => __('Please use a password of at least 8 characters.', 'country-week'),
            default => __('Something went wrong creating your account. Please try again.', 'country-week'),
        };
    }

    public function handle_submission(): void
    {
        $redirect_to = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : '';
        $register_page = home_url('/register/');

        if (
            !isset($_POST[self::NONCE_FIELD])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            $this->redirect_with_error($register_page, $redirect_to, 'error');
        }

        if (!empty($_POST[self::HONEYPOT_FIELD])) {
            // Bots get silently sent onward as if it worked.
            wp_safe_redirect($redirect_to ?: home_url('/'));
            exit;
        }

        $rendered_at = isset($_POST[self::TIMING_FIELD]) ? (int) $_POST[self::TIMING_FIELD] : 0;

        if ($rendered_at <= 0 || (time() - $rendered_at) < self::MIN_SECONDS_BEFORE_SUBMIT) {
            wp_safe_redirect($redirect_to ?: home_url('/'));
            exit;
        }

        $name = isset($_POST['cw_reg_name']) ? sanitize_text_field(wp_unslash($_POST['cw_reg_name'])) : '';
        $email = isset($_POST['cw_reg_email']) ? sanitize_email(wp_unslash($_POST['cw_reg_email'])) : '';
        $password = isset($_POST['cw_reg_password']) ? (string) wp_unslash($_POST['cw_reg_password']) : '';

        if (!is_email($email)) {
            $this->redirect_with_error($register_page, $redirect_to, 'invalid_email');
        }

        if (strlen($password) < 8) {
            $this->redirect_with_error($register_page, $redirect_to, 'weak_password');
        }

        if (email_exists($email)) {
            $this->redirect_with_error($register_page, $redirect_to, 'email_exists');
        }

        $username = $this->unique_username_from_email($email);

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'display_name' => $name !== '' ? $name : $username,
            'role' => 'subscriber',
        ]);

        if (is_wp_error($user_id)) {
            $this->redirect_with_error($register_page, $redirect_to, 'error');
        }

        $timezone = isset($_POST['cw_reg_timezone']) ? sanitize_text_field(wp_unslash($_POST['cw_reg_timezone'])) : '';

        if ($timezone !== '') {
            Subscriber_Meta_Fields::set_timezone($user_id, $timezone);
        }

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id, true);

        wp_safe_redirect($redirect_to ?: home_url('/'));
        exit;
    }

    private function unique_username_from_email(string $email): string
    {
        $base = sanitize_user(current(explode('@', $email)), true);
        $base = $base !== '' ? $base : 'member';
        $username = $base;
        $suffix = 1;

        while (username_exists($username)) {
            $suffix++;
            $username = $base . $suffix;
        }

        return $username;
    }

    private function redirect_with_error(string $page, string $redirect_to, string $error): void
    {
        $url = add_query_arg([
            'register_error' => $error,
            'redirect_to' => $redirect_to,
        ], $page);

        wp_safe_redirect($url);
        exit;
    }
}
