<?php
/**
 * "Adopt This Country" call-to-action shown at the bottom of every
 * country page, linking to the Adopt a Country form with this country
 * pre-selected.
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

$adopt_url = add_query_arg('country_id', $country->ID, home_url('/adopt-a-country/'));
?>
<section class="adopt-cta">
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
</section>
