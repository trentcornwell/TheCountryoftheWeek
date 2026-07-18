<?php
/**
 * The country's location map: the theme's bundled outline map (see
 * MAP-SOURCES.md), resolved via country_week_get_map_url(), which
 * falls back to a generic placeholder graphic if even that is
 * unavailable. Takes priority over the legacy `map_image_id`
 * admin-uploaded attachment (a per-country CIA World Factbook raster
 * PNG, imported for every country before this bundled SVG set
 * existed) — that meta field and its data are left untouched, but no
 * longer read here, since it can't meet the square/consistent/
 * transparent-background requirements the bundled maps were built to
 * satisfy site-wide.
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
?>
<section class="country-map" aria-labelledby="country-map-heading">
    <h2 id="country-map-heading"><?php esc_html_e('Location', 'country-week'); ?></h2>
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
</section>
