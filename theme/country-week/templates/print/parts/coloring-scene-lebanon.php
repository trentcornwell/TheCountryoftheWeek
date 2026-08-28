<?php
/**
 * One-off, hand-built coloring scene for Lebanon specifically — by
 * explicit request, not (yet) generalized to the other 195 countries,
 * which keep the standard layout in templates/print/country-coloring.php.
 *
 * The sun, trees, and flag are original line-art built for this page
 * (not sourced from any reference image), deliberately built around
 * things that are already accurate and sourced elsewhere in this
 * project — Lebanon's real flag (with its cedar tree) and its real
 * country outline — rather than an invented human character in
 * "traditional dress," which this project's own content-safety rule
 * (see CLAUDE.md) says shouldn't be guessed at without a vetted
 * reference. The cedar emblem here is a simplified, tiered silhouette
 * for a children's coloring page, not a precise heraldic rendering.
 *
 * Expects $args['country'] (WP_Post), $args['map_markup'] (string, raw
 * trusted SVG — see Utilities\Map_Asset), $args['country_name'] (string).
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

$country_name = $args['country_name'] ?? '';
$map_markup = $args['map_markup'] ?? '';

if ($country_name === '' || $map_markup === '') {
    return;
}
?>
<main class="print-sheet__page coloring-page coloring-page--lebanon">
    <h1 class="coloring-page__bubble-text coloring-page__kicker coloring-page__kicker--big">
        <?php esc_html_e('The Country', 'country-week'); ?><br>
        <?php esc_html_e('of the Week', 'country-week'); ?>
    </h1>

    <div class="lebanon-scene">
        <svg class="lebanon-scene__sun" viewBox="0 0 120 120" aria-hidden="true">
            <circle cx="60" cy="60" r="26"/>
            <g class="lebanon-scene__sun-rays">
                <line x1="60" y1="6" x2="60" y2="28"/>
                <line x1="60" y1="92" x2="60" y2="114"/>
                <line x1="6" y1="60" x2="28" y2="60"/>
                <line x1="92" y1="60" x2="114" y2="60"/>
                <line x1="19.7" y1="19.7" x2="35.8" y2="35.8"/>
                <line x1="84.2" y1="84.2" x2="100.3" y2="100.3"/>
                <line x1="19.7" y1="100.3" x2="35.8" y2="84.2"/>
                <line x1="84.2" y1="35.8" x2="100.3" y2="19.7"/>
            </g>
        </svg>

        <svg class="lebanon-scene__flag" viewBox="0 0 140 130" aria-hidden="true" role="img" aria-label="<?php esc_attr_e('Flag of Lebanon', 'country-week'); ?>">
            <line x1="10" y1="8" x2="10" y2="122"/>
            <rect x="10" y="8" width="112" height="56"/>
            <line x1="10" y1="22" x2="122" y2="22"/>
            <line x1="10" y1="50" x2="122" y2="50"/>
            <polygon points="46,45 86,45 66,29"/>
            <polygon points="52,38 80,38 66,24"/>
            <polygon points="57,31 75,31 66,19"/>
            <rect x="63" y="45" width="6" height="6"/>
        </svg>

        <div class="lebanon-scene__stage">
            <div class="coloring-page__art">
                <?php
                // Trusted, pipeline-generated markup — see
                // Map_Asset::inline_markup_for()'s docblock. Deliberately
                // not escaped: it's our own build-pipeline SVG, and
                // escaping would print the markup as text instead of
                // rendering the map.
                echo $map_markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
            </div>
        </div>

        <svg class="lebanon-scene__tree lebanon-scene__tree--left" viewBox="0 0 80 120" aria-hidden="true">
            <rect x="34" y="88" width="12" height="28"/>
            <circle cx="26" cy="55" r="22"/>
            <circle cx="54" cy="55" r="22"/>
            <circle cx="40" cy="34" r="24"/>
        </svg>

        <svg class="lebanon-scene__tree lebanon-scene__tree--right" viewBox="0 0 80 120" aria-hidden="true">
            <rect x="34" y="88" width="12" height="28"/>
            <circle cx="26" cy="55" r="22"/>
            <circle cx="54" cy="55" r="22"/>
            <circle cx="40" cy="34" r="24"/>
        </svg>
    </div>

    <p class="coloring-page__bubble-text coloring-page__name coloring-page__name--xl">
        <?php echo esc_html($country_name); ?>
    </p>

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
