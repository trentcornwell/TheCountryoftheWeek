<?php
/**
 * The "Adopt This Country" form: rendering, spam-resistant submission
 * handling, storage, and admin notification.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Forms;

use CountryWeek\CPT\Country_Adoption_Post_Type;
use CountryWeek\CPT\Country_Post_Type;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * "Adopting" a country means volunteering to help keep its page
 * accurate and up to date — it does not grant any editing capability
 * automatically. A moderator reviews every request in wp-admin (see
 * Country_Adoption_Post_Type::STATUS_*) before following up, the same
 * approve/reject pattern as Suggest_Edit_Form's suggestions.
 */
class Adoption_Form
{
    private const NONCE_ACTION = 'country_week_adopt';
    private const NONCE_FIELD = 'country_week_adopt_nonce';
    private const HONEYPOT_FIELD = 'cw_website';
    private const TIMING_FIELD = 'cw_rendered_at';
    private const MIN_SECONDS_BEFORE_SUBMIT = 3;

    public function register(): void
    {
        add_action('admin_post_country_week_adopt', [$this, 'handle_submission']);
        add_action('admin_post_nopriv_country_week_adopt', [$this, 'handle_submission']);
    }

    public function render(?WP_Post $country = null): string
    {
        $action_url = admin_url('admin-post.php');
        $result = isset($_GET['adopted']) ? sanitize_key(wp_unslash($_GET['adopted'])) : '';

        // A country can arrive here already taken — either via a stale
        // ?country_id= link (e.g. adopted by someone else since the
        // linking page was cached/loaded) or because it's genuinely
        // unavailable. Show why instead of a form that can only fail.
        // Skipped when $result is already set: that means we're
        // rendering after a redirect (e.g. the submission itself was
        // rejected as 'taken'), and the notice below would just repeat
        // the one the normal result-handling block already shows.
        if ($country instanceof WP_Post && $result === '' && Country_Adoption_Post_Type::is_taken($country->ID)) {
            ob_start();
            ?>
            <div class="adopt-country">
                <p class="suggest-edit__notice">
                    <?php
                    printf(
                        /* translators: %s: country name. */
                        esc_html__('%s has already been adopted or has a pending adoption request. Choose a different country below.', 'country-week'),
                        esc_html(get_the_title($country))
                    );
                    ?>
                </p>
                <?php echo $this->render(null); // phpcs:ignore -- intentional recursive fallback to the full picker. ?>
            </div>
            <?php
            return (string) ob_get_clean();
        }

        if ($country instanceof WP_Post && Country_Adoption_Post_Type::is_taken($country->ID)) {
            // $result !== '': fall through to the plain picker below,
            // country_id cleared so it doesn't re-render as locked.
            $country = null;
        }

        $available = $country instanceof WP_Post ? [] : $this->available_countries();

        ob_start();
        ?>
        <div class="adopt-country">
            <?php if ($result === 'success') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--success">
                    <?php esc_html_e('Thank you! We\'ll review your request and follow up by email.', 'country-week'); ?>
                </p>
            <?php elseif ($result === 'taken') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--error">
                    <?php esc_html_e('Sorry, that country was just adopted by someone else. Please choose another.', 'country-week'); ?>
                </p>
            <?php elseif ($result === 'error') : ?>
                <p class="suggest-edit__notice suggest-edit__notice--error">
                    <?php esc_html_e('Something went wrong submitting the form. Please try again.', 'country-week'); ?>
                </p>
            <?php endif; ?>

            <?php if (!$country instanceof WP_Post && empty($available)) : ?>
                <p class="suggest-edit__notice">
                    <?php esc_html_e('Every country currently has an adopter or a pending request. Check back later!', 'country-week'); ?>
                </p>
            <?php else : ?>
                <form class="suggest-edit__form" method="post" action="<?php echo esc_url($action_url); ?>">
                    <input type="hidden" name="action" value="country_week_adopt">
                    <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
                    <input type="hidden" name="<?php echo esc_attr(self::TIMING_FIELD); ?>" value="<?php echo esc_attr((string) time()); ?>">

                    <p class="suggest-edit__honeypot" aria-hidden="true">
                        <label for="cw_adopt_website"><?php esc_html_e('Website', 'country-week'); ?></label>
                        <input type="text" id="cw_adopt_website" name="<?php echo esc_attr(self::HONEYPOT_FIELD); ?>" tabindex="-1" autocomplete="off">
                    </p>

                    <p>
                        <label for="cw_adopt_name"><?php esc_html_e('Name', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                        <input type="text" id="cw_adopt_name" name="cw_adopt_name" required>
                    </p>

                    <p>
                        <label for="cw_adopt_email"><?php esc_html_e('Email', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                        <input type="email" id="cw_adopt_email" name="cw_adopt_email" required>
                    </p>

                    <p>
                        <label for="cw_adopt_country"><?php esc_html_e('Country', 'country-week'); ?> <span aria-hidden="true">*</span></label>
                        <?php if ($country instanceof WP_Post) : ?>
                            <input type="text" id="cw_adopt_country" value="<?php echo esc_attr(get_the_title($country)); ?>" readonly>
                            <input type="hidden" name="cw_adopt_country_id" value="<?php echo esc_attr((string) $country->ID); ?>">
                        <?php else : ?>
                            <select id="cw_adopt_country" name="cw_adopt_country_id" required>
                                <option value=""><?php esc_html_e('Select a country&hellip;', 'country-week'); ?></option>
                                <?php foreach ($available as $option) : ?>
                                    <option value="<?php echo esc_attr((string) $option->ID); ?>">
                                        <?php echo esc_html(get_the_title($option)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </p>

                    <p>
                        <label for="cw_adopt_bio"><?php esc_html_e('Short Bio (optional — shown publicly on the country page if approved)', 'country-week'); ?></label>
                        <textarea id="cw_adopt_bio" name="cw_adopt_bio" rows="3" maxlength="500"></textarea>
                    </p>

                    <p>
                        <label for="cw_adopt_message"><?php esc_html_e('Why do you want to adopt this country? (optional, for our review only — not shown publicly)', 'country-week'); ?></label>
                        <textarea id="cw_adopt_message" name="cw_adopt_message" rows="4"></textarea>
                    </p>

                    <p class="suggest-edit__hint">
                        <?php esc_html_e('Adopting a country means committing to help us keep its page accurate and up to date. We\'ll review your request and reach out by email.', 'country-week'); ?>
                    </p>

                    <p>
                        <button type="submit"><?php esc_html_e('Adopt This Country', 'country-week'); ?></button>
                    </p>
                </form>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Every published country minus ones with a pending or approved
     * adoption (Country_Adoption_Post_Type::taken_country_ids()) — a
     * rejected request frees its country back up.
     *
     * @return WP_Post[]
     */
    private function available_countries(): array
    {
        $taken = array_flip(Country_Adoption_Post_Type::taken_country_ids());

        return array_values(array_filter(
            \CountryWeek\Services\Country_Repository::get_all_ordered(),
            fn (WP_Post $option) => !isset($taken[$option->ID])
        ));
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

        $name = isset($_POST['cw_adopt_name']) ? sanitize_text_field(wp_unslash($_POST['cw_adopt_name'])) : '';
        $email = isset($_POST['cw_adopt_email']) ? sanitize_email(wp_unslash($_POST['cw_adopt_email'])) : '';
        $country_id = isset($_POST['cw_adopt_country_id']) ? absint($_POST['cw_adopt_country_id']) : 0;
        $bio = isset($_POST['cw_adopt_bio']) ? sanitize_textarea_field(wp_unslash($_POST['cw_adopt_bio'])) : '';
        $message = isset($_POST['cw_adopt_message']) ? sanitize_textarea_field(wp_unslash($_POST['cw_adopt_message'])) : '';

        $country = get_post($country_id);

        if ($name === '' || !is_email($email) || !$country instanceof WP_Post || $country->post_type !== Country_Post_Type::POST_TYPE) {
            $this->redirect_with_result($redirect_to, 'error');
        }

        // Re-check availability server-side: the dropdown/locked-field
        // only reflect what was true when the page rendered, so a
        // country could have been claimed by someone else (or by a
        // second tab) in the meantime.
        if (Country_Adoption_Post_Type::is_taken($country->ID)) {
            $this->redirect_with_result(add_query_arg('country_id', $country->ID, $redirect_to), 'taken');
        }

        $adoption_id = wp_insert_post([
            'post_type' => Country_Adoption_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            /* translators: 1: name, 2: country name. */
            'post_title' => sprintf(__('%1$s wants to adopt %2$s', 'country-week'), $name, get_the_title($country)),
        ], true);

        if (is_wp_error($adoption_id)) {
            $this->redirect_with_result($redirect_to, 'error');
        }

        update_post_meta($adoption_id, 'submitter_name', $name);
        update_post_meta($adoption_id, 'submitter_email', $email);
        update_post_meta($adoption_id, 'country_post_id', (string) $country->ID);
        update_post_meta($adoption_id, 'bio', $bio);
        update_post_meta($adoption_id, 'message', $message);
        update_post_meta($adoption_id, Country_Adoption_Post_Type::STATUS_META_KEY, Country_Adoption_Post_Type::STATUS_PENDING);

        $this->email_admin($country, $name, $email, $bio, $message);

        $this->redirect_with_result($redirect_to, 'success');
    }

    private function email_admin(WP_Post $country, string $name, string $email, string $bio, string $message): void
    {
        $admin_email = get_option('admin_email');

        /* translators: %s: country name. */
        $subject = sprintf(__('[The Country of the Week] Adoption request for %s', 'country-week'), get_the_title($country));

        $body = implode("\n\n", array_filter([
            sprintf(__('Country: %s', 'country-week'), get_the_title($country)),
            sprintf(__('From: %1$s <%2$s>', 'country-week'), $name, $email),
            $bio !== '' ? sprintf(__('Public bio (shown on the country page if approved):%s%s', 'country-week'), "\n", $bio) : '',
            $message !== '' ? sprintf(__('Message (private, for review only):%s%s', 'country-week'), "\n", $message) : '',
            sprintf(__('Review in wp-admin: %s', 'country-week'), admin_url('edit.php?post_type=' . Country_Adoption_Post_Type::POST_TYPE)),
        ]));

        wp_mail($admin_email, $subject, $body, ['Reply-To: ' . $name . ' <' . $email . '>']);
    }

    private function redirect_with_result(string $redirect_to, string $result): void
    {
        $url = add_query_arg('adopted', $result, $redirect_to);
        wp_safe_redirect($url);
        exit;
    }
}
