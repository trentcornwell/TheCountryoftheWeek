<?php
/**
 * One-off coloring page for Lebanon specifically — by explicit request,
 * not (yet) generalized to the other 195 countries, which keep the
 * standard layout in templates/print/country-coloring.php.
 *
 * Unlike the standard layout, this doesn't build a scene from the
 * theme's own map/flag assets: it prints a single custom illustration
 * (assets/coloring/lebanon.png) supplied directly by the site owner —
 * already a complete coloring page (title, labeled map, decorative
 * border) — and adds only the "stick me on the fridge" caption below
 * it, since that line isn't part of the artwork itself.
 *
 * Expects $args['country_name'] (string).
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

$country_name = $args['country_name'] ?? '';

if ($country_name === '') {
    return;
}

$image_url = get_theme_file_uri('assets/coloring/lebanon.png');
?>
<main class="print-sheet__page coloring-page coloring-page--photo">
    <img
        class="coloring-page__custom-image"
        src="<?php echo esc_url($image_url); ?>"
        alt="<?php
            printf(
                /* translators: %s: country name. */
                esc_attr__('%s coloring page map', 'country-week'),
                esc_attr($country_name)
            );
        ?>"
    >

    <p class="coloring-page__caption coloring-page__caption--bottom">
        <?php
        printf(
            /* translators: %s: country name. */
            esc_html__('Color me in, then stick me on the fridge to remember to pray for %s!', 'country-week'),
            esc_html($country_name)
        );
        ?>
    </p>

    <p class="coloring-page__footer"><?php bloginfo('name'); ?> &middot; <?php echo esc_html(home_url('/')); ?></p>
</main>
