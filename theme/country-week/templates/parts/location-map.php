<?php
/**
 * The country's location map. Prefers the real CIA World Factbook
 * reference map (github.com/factbook/media, CC0 — the same public-
 * domain lineage cia.gov itself publishes from) already downloaded and
 * attached to every country post at import time — see
 * scripts/import-media.php and the `map_image_id` meta it sets.
 *
 * Falls back to the theme's own bundled outline-map SVG (see
 * MAP-SOURCES.md, resolved via country_week_get_map_url()) only for a
 * post that has no map_image_id — currently just the two one-off,
 * non-manifest countries added outside the normal import pipeline
 * (see data/one-off-features.json); every one of the 196 manifest
 * countries has the real map attached.
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

$map_alt = sprintf(
    /* translators: %s: country name. */
    __('Map showing the location of %s', 'country-week'),
    get_the_title($country)
);

$map_attachment_id = (int) get_post_meta($country->ID, 'map_image_id', true);
$has_real_map = $map_attachment_id && get_post($map_attachment_id);
?>
<section class="country-map" aria-labelledby="country-map-heading">
    <h2 id="country-map-heading"><?php esc_html_e('Location', 'country-week'); ?></h2>
    <?php if ($has_real_map) : ?>
        <?php
        echo wp_get_attachment_image($map_attachment_id, 'large', false, [
            'class' => 'country-map__image',
            'alt' => $map_alt,
            'loading' => 'lazy',
            'decoding' => 'async',
        ]);
        ?>
        <p class="country-map__source"><?php esc_html_e('Source: CIA World Factbook (public domain)', 'country-week'); ?></p>
    <?php else : ?>
        <div class="country-map__frame">
            <img
                src="<?php echo esc_url(country_week_get_map_url($country)); ?>"
                alt="<?php echo esc_attr($map_alt); ?>"
                class="country-map__image"
                loading="lazy"
                decoding="async"
                width="1000"
                height="1000"
            >
        </div>
        <p class="country-map__source"><?php esc_html_e('Source: Natural Earth (public domain)', 'country-week'); ?></p>
    <?php endif; ?>
</section>
