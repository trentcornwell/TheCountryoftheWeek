<?php
/**
 * The country's location map image, if one has been uploaded.
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

$map_id = (int) get_post_meta($country->ID, 'map_image_id', true);

if (!$map_id) {
    return;
}
?>
<section class="country-map" aria-labelledby="country-map-heading">
    <h2 id="country-map-heading"><?php esc_html_e('Location', 'country-week'); ?></h2>
    <?php
    echo wp_get_attachment_image($map_id, 'large', false, [
        'loading' => 'lazy',
        'decoding' => 'async',
        'class' => 'country-map__image',
        'alt' => sprintf(
            /* translators: %s: country name. */
            __('Map showing the location of %s', 'country-week'),
            get_the_title($country)
        ),
    ]);
    ?>
    <p class="country-map__source"><?php esc_html_e('Source: CIA World Factbook (public domain)', 'country-week'); ?></p>
</section>
