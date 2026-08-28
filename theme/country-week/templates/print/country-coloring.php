<?php
/**
 * Standalone, printable black-and-white coloring page featuring the
 * country's own outline map — no header.php/footer.php site chrome,
 * same pattern as templates/print/country-print.php. Reached via the
 * /coloring/ rewrite endpoint; see Hooks\Rewrite_Hooks. Deliberately
 * NOT gated behind login (unlike /print/ and /slide/) — free for
 * anyone, by explicit request.
 *
 * @package CountryWeek
 */

use CountryWeek\Services\Country_Manifest;
use CountryWeek\Utilities\Asset_Loader;
use CountryWeek\Utilities\Map_Asset;

if (!defined('ABSPATH')) {
    exit;
}

the_post();
$country = get_post();
$map_markup = Map_Asset::inline_markup_for($country);
$country_name = get_the_title($country);

/**
 * Lebanon gets a one-off, hand-built scene (flag, sun, trees around the
 * country outline) instead of the standard layout every other country
 * uses below — by explicit request, not generalized to all 196
 * countries yet. See templates/print/parts/coloring-scene-lebanon.php.
 */
$manifest_key = get_post_meta($country->ID, Country_Manifest::meta_key(), true);
$is_lebanon = $manifest_key === 'lebanon';

/**
 * The country name's bubble-letter size has to work for both "Chad"
 * and "Congo, Democratic Republic of the" (4 to 33 characters across
 * the manifest) with no client-side JS to measure/auto-fit it — see
 * MAP-SOURCES.md's sibling docs for why this theme avoids JS on
 * content pages. A simple length-tiered class, with a CSS word-wrap
 * safety net regardless of tier, covers every real country name
 * without needing real text measurement.
 */
$name_length = mb_strlen($country_name);

if ($name_length <= 6) {
    $name_size_class = 'coloring-page__name--xl';
} elseif ($name_length <= 10) {
    $name_size_class = 'coloring-page__name--lg';
} elseif ($name_length <= 16) {
    $name_size_class = 'coloring-page__name--md';
} elseif ($name_length <= 24) {
    $name_size_class = 'coloring-page__name--sm';
} else {
    $name_size_class = 'coloring-page__name--xs';
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title>
        <?php
        printf(
            /* translators: %s: country name. */
            esc_html__('%s Coloring Page', 'country-week'),
            esc_html($country_name)
        );
        ?>
         — <?php bloginfo('name'); ?>
    </title>
    <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- standalone document with no wp_head()/wp_footer(), so wp_enqueue_style() has nothing to print into; a raw link tag is the correct choice here (same as templates/print/country-print.php). ?>
    <link rel="stylesheet" href="<?php echo esc_url(Asset_Loader::print_stylesheet_url()); ?>">
</head>
<body class="print-sheet">
    <button type="button" class="print-sheet__print-button no-print" onclick="window.print()">
        <?php esc_html_e('Print / Save as PDF', 'country-week'); ?>
    </button>

    <?php if ($is_lebanon) : ?>
        <?php get_template_part('templates/print/parts/coloring-scene-lebanon', null, ['country_name' => $country_name]); ?>
    <?php else : ?>
        <main class="print-sheet__page coloring-page">
            <h1 class="coloring-page__bubble-text coloring-page__kicker">
                <?php esc_html_e('The Country', 'country-week'); ?><br>
                <?php esc_html_e('of the Week', 'country-week'); ?>
            </h1>

            <p class="coloring-page__caption">
                <?php
                printf(
                    /* translators: %s: country name. */
                    esc_html__('Color me in, then stick me on the fridge to remember to pray for %s!', 'country-week'),
                    esc_html($country_name)
                );
                ?>
            </p>

            <div class="coloring-page__art">
                <?php
                // Trusted, pipeline-generated markup — see
                // Map_Asset::inline_markup_for()'s docblock. Deliberately not
                // escaped: it's our own build-pipeline SVG, and escaping would
                // print the markup as text instead of rendering the map.
                echo $map_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>

            <p class="coloring-page__bubble-text coloring-page__name <?php echo esc_attr($name_size_class); ?>">
                <?php echo esc_html($country_name); ?>
            </p>

            <p class="coloring-page__footer"><?php bloginfo('name'); ?> &middot; <?php echo esc_html(home_url('/')); ?></p>
        </main>
    <?php endif; ?>
</body>
</html>
