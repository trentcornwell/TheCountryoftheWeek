<?php
/**
 * The top-of-page hero: flag, country name, hero photo, and short
 * excerpt. The hero image is the Largest Contentful Paint element on
 * the homepage, so it is rendered eagerly (never lazy-loaded) — see
 * Hooks\Performance_Hooks::force_lazy_loading().
 *
 * Expects $args['country'] (WP_Post) and optional $args['banner']
 * (string of HTML, e.g. an "upcoming"/"past" notice from single-country.php).
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

$banner = $args['banner'] ?? '';
$flag_id = (int) get_post_meta($country->ID, 'flag_image_id', true);
$has_hero_image = has_post_thumbnail($country);
?>
<header class="country-hero">
    <?php if ($banner !== '') : ?>
        <div class="country-hero__banner"><?php echo wp_kses_post($banner); ?></div>
    <?php endif; ?>

    <?php if ($has_hero_image) : ?>
        <div class="country-hero__image">
            <?php
            echo get_the_post_thumbnail($country, 'large', [
                'loading' => 'eager',
                'decoding' => 'async',
                'fetchpriority' => 'high',
                'alt' => sprintf(
                    /* translators: %s: country name. */
                    __('Scenic photo representing %s', 'country-week'),
                    get_the_title($country)
                ),
            ]);
            ?>
        </div>
    <?php endif; ?>

    <div class="country-hero__identity">
        <?php if ($flag_id) : ?>
            <?php
            echo wp_get_attachment_image($flag_id, 'medium', false, [
                'class' => 'country-hero__flag',
                'loading' => 'eager',
                'decoding' => 'async',
                'alt' => sprintf(
                    /* translators: %s: country name. */
                    __('Flag of %s', 'country-week'),
                    get_the_title($country)
                ),
            ]);
            ?>
        <?php endif; ?>

        <h1 class="country-hero__name"><?php echo esc_html(get_the_title($country)); ?></h1>

        <?php if (has_excerpt($country)) : ?>
            <p class="country-hero__excerpt"><?php echo esc_html(get_the_excerpt($country)); ?></p>
        <?php endif; ?>
    </div>
</header>
