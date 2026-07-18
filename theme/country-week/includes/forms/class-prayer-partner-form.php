<?php
/**
 * The "Join Us in Prayer" signup form: rendering, spam-resistant
 * submission handling, storage, and admin notification.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Forms;

use CountryWeek\CPT\Prayer_Partner_Post_Type;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Same spam-prevention approach as Suggest_Edit_Form (native nonce +
 * honeypot + minimum-time-on-page check, no external CAPTCHA service)
 * — see that class for the rationale.
 */
class Prayer_Partner_Form
{
    private const NONCE_ACTION = 'country_week_join_prayer';
    private const NONCE_FIELD = 'country_week_join_prayer_nonce';
    private const HONEYPOT_FIELD = 'cw_website';
    private const TIMING_FIELD = 'cw_rendered_at';
    private const MIN_SECONDS_BEFORE_SUBMIT = 3;

    public function register(): void
    {
        add_action('admin_post_country_week_join_prayer', [$this, 'handle_submission']);
        add_action('admin_post_nopriv_country_week_join_prayer', [$this, 'handle_submission']);
    }

    public function render(): string
    {
        $action_url = admin_url('admin-post.php');
        $result = isset($_GET['joined']) ? sanitize_key(wp_unslash($_GET['joined'])) : '';

        ob_start();
        ?>
        <div class="prayer-partner">
            <?php if ($result === 'success') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--success">
                    <?php esc_html_e('Thank you for joining us! We\'ll be in touch soon with additional helpful resources.', 'country-week'); ?>
                </p>
            <?php elseif ($result === 'error') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--error">
                    <?php esc_html_e('Something went wrong submitting the form. Please try again.', 'country-week'); ?>
                </p>
            <?php endif; ?>

            <form class="suggest-edit__form" method="post" action="<?php echo esc_url($action_url); ?>">
                <input type="hidden" name="action" value="country_week_join_prayer">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="<?php echo esc_attr(self::TIMING_FIELD); ?>" value="<?php echo esc_attr((string) time()); ?>">

                <p class="suggest-edit__honeypot" aria-hidden="true">
                    <label for="cw_website"><?php esc_html_e('Website', 'country-week'); ?></label>
                    <input type="text" id="cw_website" name="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>" tabindex="-1" autocomplete="off">
                </p>

                <p>
                    <label for="cw_partner_name"><?php esc_html_e('Name', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <input type="text" id="cw_partner_name" name="cw_partner_name" required>
                </p>

                <p>
                    <label for="cw_partner_church"><?php esc_html_e('Church', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <input type="text" id="cw_partner_church" name="cw_partner_church" required>
                </p>

                <p>
                    <label for="cw_partner_email"><?php esc_html_e('Email', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <input type="email" id="cw_partner_email" name="cw_partner_email" required>
                    <span class="suggest-edit__hint"><?php esc_html_e('So we can send you additional helpful resources.', 'country-week'); ?></span>
                </p>

                <p>
                    <label for="cw_partner_started"><?php esc_html_e('When did you start praying through the world with us?', 'country-week'); ?></label>
                    <input type="text" id="cw_partner_started" name="cw_partner_started" placeholder="<?php esc_attr_e('e.g. this week, or the week of Kiribati', 'country-week'); ?>">
                </p>

                <p>
                    <button type="submit"><?php esc_html_e('Join Us in Prayer', 'country-week'); ?></button>
                </p>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function handle_submission(): void
    {
        $redirect_to = wp_get_referer() ?: home_url('/');

        if (
            !isset($_POST[self::NONCE_FIELD])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            $this->redirect_with_result($redirect_to, 'error');
        }

        if (!empty($_POST[self::HONEYPOT_FIELD])) {
            $this->redirect_with_result($redirect_to, 'success');
        }

        $rendered_at = isset($_POST[self::TIMING_FIELD]) ? (int) $_POST[self::TIMING_FIELD] : 0;

        if ($rendered_at <= 0 || (time() - $rendered_at) < self::MIN_SECONDS_BEFORE_SUBMIT) {
            $this->redirect_with_result($redirect_to, 'success');
        }

        $name = isset($_POST['cw_partner_name']) ? sanitize_text_field(wp_unslash($_POST['cw_partner_name'])) : '';
        $church = isset($_POST['cw_partner_church']) ? sanitize_text_field(wp_unslash($_POST['cw_partner_church'])) : '';
        $email = isset($_POST['cw_partner_email']) ? sanitize_email(wp_unslash($_POST['cw_partner_email'])) : '';
        $started = isset($_POST['cw_partner_started']) ? sanitize_text_field(wp_unslash($_POST['cw_partner_started'])) : '';

        if ($name === '' || $church === '' || !is_email($email)) {
            $this->redirect_with_result($redirect_to, 'error');
        }

        $partner_id = wp_insert_post([
            'post_type' => Prayer_Partner_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            /* translators: 1: name, 2: church. */
            'post_title' => sprintf(__('%1$s (%2$s)', 'country-week'), $name, $church),
        ], true);

        if (is_wp_error($partner_id)) {
            $this->redirect_with_result($redirect_to, 'error');
        }

        update_post_meta($partner_id, 'submitter_name', $name);
        update_post_meta($partner_id, 'church', $church);
        update_post_meta($partner_id, 'email', $email);
        update_post_meta($partner_id, 'started_praying', $started);
        update_post_meta($partner_id, 'resources_sent', 'no');

        $this->email_admin($name, $church, $email, $started);

        $this->redirect_with_result($redirect_to, 'success');
    }

    private function email_admin(string $name, string $church, string $email, string $started): void
    {
        $admin_email = get_option('admin_email');
        $subject = sprintf(
            /* translators: %s: name. */
            __('[The Country of the Week] New prayer partner: %s', 'country-week'),
            $name
        );

        $body = implode("\n\n", array_filter([
            sprintf(__('Name: %s', 'country-week'), $name),
            sprintf(__('Church: %s', 'country-week'), $church),
            sprintf(__('Email: %s', 'country-week'), $email),
            $started !== '' ? sprintf(__('Started praying with us: %s', 'country-week'), $started) : '',
            __('Remember to send them additional helpful resources.', 'country-week'),
            sprintf(__('Review in wp-admin: %s', 'country-week'), admin_url('edit.php?post_type=' . Prayer_Partner_Post_Type::POST_TYPE)),
        ]));

        wp_mail($admin_email, $subject, $body, ['Reply-To: ' . $name . ' <' . $email . '>']);
    }

    private function redirect_with_result(string $redirect_to, string $result): void
    {
        $url = add_query_arg('joined', $result, $redirect_to);
        wp_safe_redirect($url);
        exit;
    }
}
