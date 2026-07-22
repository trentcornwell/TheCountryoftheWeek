<?php
/**
 * Shown at the bottom of every country page: either who has adopted
 * it (once a moderator has approved the request — see
 * Admin\Submission_Moderation and Country_Adoption_Post_Type), a
 * neutral notice while a request is still pending review, or the
 * "Adopt This Country" call-to-action if no one has claimed it yet.
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\CPT\Country_Adoption_Post_Type;

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

$approved = Country_Adoption_Post_Type::find_approved_for_country($country->ID);
?>
<section class="adopt-cta">
    <?php if ($approved instanceof WP_Post) : ?>
        <?php
        $adopter_name = get_post_meta($approved->ID, 'submitter_name', true);
        $bio = get_post_meta($approved->ID, 'bio', true);
        ?>
        <h2><?php esc_html_e('Adopted', 'country-week'); ?></h2>
        <p class="adopt-cta__adopter">
            <?php
            printf(
                /* translators: %s: adopter's name. */
                esc_html__('Adopted by %s', 'country-week'),
                esc_html(is_string($adopter_name) ? $adopter_name : '')
            );
            ?>
        </p>
        <?php if (is_string($bio) && $bio !== '') : ?>
            <p class="adopt-cta__bio"><?php echo nl2br(esc_html($bio)); ?></p>
        <?php endif; ?>
    <?php elseif (Country_Adoption_Post_Type::is_taken($country->ID)) : ?>
        <h2><?php esc_html_e('Adoption Pending', 'country-week'); ?></h2>
        <p><?php esc_html_e('Someone has requested to adopt this country and it\'s under review.', 'country-week'); ?></p>
    <?php else : ?>
        <?php $adopt_url = add_query_arg('country_id', $country->ID, home_url('/adopt-a-country/')); ?>
        <h2><?php esc_html_e('Adopt This Country', 'country-week'); ?></h2>
        <p>
            <?php
            printf(
                /* translators: %s: country name. */
                esc_html__('Will you help us keep the %s page accurate and up to date?', 'country-week'),
                esc_html(get_the_title($country))
            );
            ?>
        </p>
        <a class="country-actions__button country-actions__button--pdf" href="<?php echo esc_url($adopt_url); ?>">
            <?php esc_html_e('Adopt This Country', 'country-week'); ?>
        </a>
    <?php endif; ?>
</section>
