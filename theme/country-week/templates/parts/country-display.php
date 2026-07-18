<?php
/**
 * Assembles the full country page out of the smaller template parts.
 * This is the single source of truth for "what a country page looks
 * like" — front-page.php includes it for the currently active country,
 * and single-country.php includes it for any country (past/future),
 * optionally passing a $args['banner'] notice.
 *
 * Expects $args['country'] (WP_Post) and optional $args['banner'] (string).
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

$part_args = ['country' => $country];
?>
<article class="country-display">
    <?php get_template_part('templates/parts/country-nav-arrows', null, $part_args); ?>
    <?php get_template_part('templates/parts/hero', null, ['country' => $country, 'banner' => $args['banner'] ?? '']); ?>

    <div class="country-display__actions">
        <?php get_template_part('templates/parts/share-buttons', null, $part_args); ?>
        <?php get_template_part('templates/parts/suggest-edit-dialog', null, $part_args); ?>
    </div>

    <div class="country-display__layout">
        <div class="country-display__primary">
            <?php get_template_part('templates/parts/location-map', null, $part_args); ?>
            <?php get_template_part('templates/parts/summaries', null, $part_args); ?>

            <?php
            get_template_part('templates/parts/facts-list', null, [
                'country' => $country,
                'meta_key' => 'interesting_facts',
                'heading' => __('Interesting Facts', 'country-week'),
                'heading_id' => 'interesting-facts-heading',
            ]);
            ?>

            <?php
            get_template_part('templates/parts/facts-list', null, [
                'country' => $country,
                'meta_key' => 'did_you_know',
                'heading' => __('Did You Know?', 'country-week'),
                'heading_id' => 'did-you-know-heading',
            ]);
            ?>

            <?php get_template_part('templates/parts/prayer-section', null, $part_args); ?>
            <?php get_template_part('templates/parts/gallery', null, $part_args); ?>
            <?php get_template_part('templates/parts/suggested-reading', null, $part_args); ?>
        </div>

        <aside class="country-display__sidebar">
            <?php get_template_part('templates/parts/quick-facts', null, $part_args); ?>
            <?php get_template_part('templates/parts/related-countries', null, $part_args); ?>
        </aside>
    </div>

    <?php get_template_part('templates/parts/adopt-cta', null, $part_args); ?>
</article>
