<?php
/**
 * Logged-in-only form letting a subscriber change their weekly
 * preview opt-in and time zone.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Forms;

use CountryWeek\Services\Subscriber_Meta_Fields;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Unlike Registration_Form and the site's other public forms, this one
 * is only ever reachable by an authenticated user (see
 * page-email-preferences.php's login gate), so the login requirement
 * plus a nonce is the applicable protection here — no honeypot/timing
 * fields, since those exist specifically to filter anonymous bot
 * traffic that can't reach this form in the first place.
 */
class Email_Preferences_Form
{
    private const NONCE_ACTION = 'country_week_email_preferences';
    private const NONCE_FIELD = 'country_week_email_preferences_nonce';

    public function register(): void
    {
        add_action('admin_post_country_week_update_email_preferences', [$this, 'handle_submission']);
    }

    public function render(): string
    {
        if (!is_user_logged_in()) {
            return '';
        }

        $user = wp_get_current_user();
        $current_timezone = Subscriber_Meta_Fields::timezone_for($user->ID)->getName();
        $wants_email = Subscriber_Meta_Fields::wants_email($user->ID);
        $saved = isset($_GET['email_pref']) ? sanitize_key(wp_unslash($_GET['email_pref'])) : '';

        if (!function_exists('wp_timezone_choice')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }

        ob_start();
        ?>
        <div class="account-form">
            <?php if ($saved === 'updated') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--success">
                    <?php esc_html_e('Your email preferences have been saved.', 'country-week'); ?>
                </p>
            <?php elseif ($saved === 'unsubscribed') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--success">
                    <?php esc_html_e("You've been unsubscribed from the weekly email. You can turn it back on below at any time.", 'country-week'); ?>
                </p>
            <?php endif; ?>

            <form class="suggest-edit__form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="country_week_update_email_preferences">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>

                <p>
                    <label for="cw_email_optin">
                        <input type="checkbox" id="cw_email_optin" name="cw_email_optin" value="1" <?php checked($wants_email); ?>>
                        <?php esc_html_e('Email me a preview of the upcoming Country of the Week every Saturday', 'country-week'); ?>
                    </label>
                </p>

                <p>
                    <label for="cw_timezone"><?php esc_html_e('Your Time Zone', 'country-week'); ?></label><br>
                    <select id="cw_timezone" name="cw_timezone">
                        <?php echo wp_timezone_choice($current_timezone, get_user_locale($user)); ?>
                    </select>
                </p>

                <p>
                    <button type="submit"><?php esc_html_e('Save Preferences', 'country-week'); ?></button>
                </p>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function handle_submission(): void
    {
        if (!is_user_logged_in()) {
            wp_safe_redirect(add_query_arg('redirect_to', home_url('/email-preferences/'), home_url('/login/')));
            exit;
        }

        if (
            !isset($_POST[self::NONCE_FIELD])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            wp_safe_redirect(home_url('/email-preferences/'));
            exit;
        }

        $user_id = get_current_user_id();
        $timezone = isset($_POST['cw_timezone']) ? sanitize_text_field(wp_unslash($_POST['cw_timezone'])) : '';
        $opt_in = !empty($_POST['cw_email_optin']);

        if ($timezone !== '') {
            Subscriber_Meta_Fields::set_timezone($user_id, $timezone);
        }

        Subscriber_Meta_Fields::set_opt_in($user_id, $opt_in);

        wp_safe_redirect(add_query_arg('email_pref', 'updated', home_url('/email-preferences/')));
        exit;
    }
}
