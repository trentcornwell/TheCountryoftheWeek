<?php
/**
 * The "Suggest an Edit" form: rendering, spam-resistant submission
 * handling, storage, and admin notification.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Forms;

use CountryWeek\CPT\Country_Post_Type;
use CountryWeek\CPT\Edit_Suggestion_Post_Type;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Spam prevention is entirely native, no external CAPTCHA service:
 *   1. A WordPress nonce (proves the form was rendered by this site).
 *   2. A honeypot field that is hidden from humans via CSS but visible
 *      to most bots; any value in it silently discards the submission.
 *   3. A minimum-time-on-page check (bots that submit within a couple
 *      seconds of the page loading are almost certainly automated).
 * None of these require configuration, API keys, or an external
 * request, matching the project's minimal-dependency requirement.
 */
class Suggest_Edit_Form
{
    private const NONCE_ACTION = 'country_week_suggest_edit';
    private const NONCE_FIELD = 'country_week_suggest_edit_nonce';
    private const HONEYPOT_FIELD = 'cw_website';
    private const TIMING_FIELD = 'cw_rendered_at';
    private const MIN_SECONDS_BEFORE_SUBMIT = 3;

    public function register(): void
    {
        add_action('admin_post_country_week_suggest_edit', [$this, 'handle_submission']);
        add_action('admin_post_nopriv_country_week_suggest_edit', [$this, 'handle_submission']);
    }

    /**
     * Render the form markup. $country pre-selects/locks the country
     * when shown on a specific country's page; omit it to show a
     * dropdown of every country (used on the general "Suggest an Edit"
     * page reached from the main navigation).
     */
    public function render(?WP_Post $country = null): string
    {
        $action_url = admin_url('admin-post.php');
        $result = isset($_GET['suggestion']) ? sanitize_key(wp_unslash($_GET['suggestion'])) : '';

        ob_start();
        ?>
        <div class="suggest-edit">
            <?php if ($result === 'success') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--success">
                    <?php esc_html_e('Thank you! Your suggestion has been submitted for review.', 'country-week'); ?>
                </p>
            <?php elseif ($result === 'error') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--error">
                    <?php esc_html_e('Something went wrong submitting your suggestion. Please try again.', 'country-week'); ?>
                </p>
            <?php endif; ?>

            <form class="suggest-edit__form" method="post" action="<?php echo esc_url($action_url); ?>">
                <input type="hidden" name="action" value="country_week_suggest_edit">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                <input type="hidden" name="<?php echo esc_attr(self::TIMING_FIELD); ?>" value="<?php echo esc_attr((string) time()); ?>">

                <p class="suggest-edit__honeypot" aria-hidden="true">
                    <label for="cw_website"><?php esc_html_e('Website', 'country-week'); ?></label>
                    <input type="text" id="cw_website" name="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>" tabindex="-1" autocomplete="off">
                </p>

                <p>
                    <label for="cw_name"><?php esc_html_e('Name', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <input type="text" id="cw_name" name="cw_name" required>
                </p>

                <p>
                    <label for="cw_email"><?php esc_html_e('Email', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <input type="email" id="cw_email" name="cw_email" required>
                </p>

                <p>
                    <label for="cw_country"><?php esc_html_e('Country', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <?php if ($country instanceof WP_Post) : ?>
                        <input type="text" id="cw_country" value="<?php echo esc_attr(get_the_title($country)); ?>" readonly>
                        <input type="hidden" name="cw_country_id" value="<?php echo esc_attr((string) $country->ID); ?>">
                    <?php else : ?>
                        <select id="cw_country" name="cw_country_id" required>
                            <option value=""><?php esc_html_e('Select a country&hellip;', 'country-week'); ?></option>
                            <?php foreach (\CountryWeek\Services\Country_Repository::get_all_ordered() as $option) : ?>
                                <option value="<?php echo esc_attr((string) $option->ID); ?>">
                                    <?php echo esc_html(get_the_title($option)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </p>

                <p>
                    <label for="cw_correction"><?php esc_html_e('Suggested Correction', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                    <textarea id="cw_correction" name="cw_correction" rows="5" required></textarea>
                </p>

                <p>
                    <label for="cw_source"><?php esc_html_e('Source', 'country-week'); ?></label>
                    <input type="text" id="cw_source" name="cw_source">
                </p>

                <p>
                    <label for="cw_source_url"><?php esc_html_e('Source URL (optional)', 'country-week'); ?></label>
                    <input type="url" id="cw_source_url" name="cw_source_url">
                </p>

                <p>
                    <button type="submit"><?php esc_html_e('Submit Suggestion', 'country-week'); ?></button>
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

        // Honeypot: bots tend to fill in every field they can see in
        // the DOM, including ones hidden only by CSS.
        if (!empty($_POST[self::HONEYPOT_FIELD])) {
            $this->redirect_with_result($redirect_to, 'success');
        }

        $rendered_at = isset($_POST[self::TIMING_FIELD]) ? (int) $_POST[self::TIMING_FIELD] : 0;

        if ($rendered_at <= 0 || (time() - $rendered_at) < self::MIN_SECONDS_BEFORE_SUBMIT) {
            $this->redirect_with_result($redirect_to, 'success');
        }

        $name = isset($_POST['cw_name']) ? sanitize_text_field(wp_unslash($_POST['cw_name'])) : '';
        $email = isset($_POST['cw_email']) ? sanitize_email(wp_unslash($_POST['cw_email'])) : '';
        $country_id = isset($_POST['cw_country_id']) ? absint($_POST['cw_country_id']) : 0;
        $correction = isset($_POST['cw_correction']) ? sanitize_textarea_field(wp_unslash($_POST['cw_correction'])) : '';
        $source = isset($_POST['cw_source']) ? sanitize_text_field(wp_unslash($_POST['cw_source'])) : '';
        $source_url = isset($_POST['cw_source_url']) ? esc_url_raw(wp_unslash($_POST['cw_source_url'])) : '';

        $country = get_post($country_id);

        if ($name === '' || !is_email($email) || $correction === '' || !$country instanceof WP_Post || $country->post_type !== Country_Post_Type::POST_TYPE) {
            $this->redirect_with_result($redirect_to, 'error');
        }

        $suggestion_id = wp_insert_post([
            'post_type' => Edit_Suggestion_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            /* translators: %s: country name. */
            'post_title' => sprintf(__('Suggestion for %s', 'country-week'), get_the_title($country)),
        ], true);

        if (is_wp_error($suggestion_id)) {
            $this->redirect_with_result($redirect_to, 'error');
        }

        update_post_meta($suggestion_id, 'submitter_name', $name);
        update_post_meta($suggestion_id, 'submitter_email', $email);
        update_post_meta($suggestion_id, 'country_post_id', (string) $country->ID);
        update_post_meta($suggestion_id, 'correction', $correction);
        update_post_meta($suggestion_id, 'source', $source);
        update_post_meta($suggestion_id, 'source_url', $source_url);
        update_post_meta($suggestion_id, Edit_Suggestion_Post_Type::STATUS_META_KEY, Edit_Suggestion_Post_Type::STATUS_PENDING);

        $this->email_admin($country, $name, $email, $correction, $source, $source_url);

        $this->redirect_with_result($redirect_to, 'success');
    }

    private function email_admin(WP_Post $country, string $name, string $email, string $correction, string $source, string $source_url): void
    {
        $admin_email = get_option('admin_email');

        /* translators: %s: country name. */
        $subject = sprintf(__('[The Country of the Week] Edit suggestion for %s', 'country-week'), get_the_title($country));

        $body = implode("\n\n", array_filter([
            sprintf(__('Country: %s', 'country-week'), get_the_title($country)),
            sprintf(__('From: %1$s <%2$s>', 'country-week'), $name, $email),
            sprintf(__('Suggested correction:%s%s', 'country-week'), "\n", $correction),
            $source !== '' ? sprintf(__('Source: %s', 'country-week'), $source) : '',
            $source_url !== '' ? sprintf(__('Source URL: %s', 'country-week'), $source_url) : '',
            sprintf(__('Review in wp-admin: %s', 'country-week'), admin_url('edit.php?post_type=' . Edit_Suggestion_Post_Type::POST_TYPE)),
        ]));

        wp_mail($admin_email, $subject, $body, ['Reply-To: ' . $name . ' <' . $email . '>']);
    }

    private function redirect_with_result(string $redirect_to, string $result): void
    {
        $url = add_query_arg('suggestion', $result, $redirect_to);
        wp_safe_redirect($url);
        exit;
    }
}
