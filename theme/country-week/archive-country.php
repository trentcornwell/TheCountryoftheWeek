<?php
/**
 * Country Archive: every country, browsable, searchable, and
 * filterable by continent — all client-side (assets/js/country-filter.js)
 * since the full list is small enough to render at once and this
 * avoids extra requests/round-trips for what is fundamentally a filter
 * over data already on the page.
 *
 * @package CountryWeek
 */

use CountryWeek\CPT\Country_Taxonomies;
use CountryWeek\Services\Country_Repository;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$countries = Country_Repository::get_all_ordered();
$continents = get_terms(['taxonomy' => Country_Taxonomies::CONTINENT, 'hide_empty' => true]);

if (is_wp_error($continents)) {
    $continents = [];
}
?>

<main class="site-main" id="main">
    <header class="archive-header">
        <h1><?php post_type_archive_title(); ?></h1>
        <p><?php esc_html_e('Every country ever featured on The Country of the Week — past, present, and future.', 'country-week'); ?></p>
    </header>

    <div class="country-filters" data-country-filters>
        <p>
            <label for="country-search" class="screen-reader-text"><?php esc_html_e('Search countries', 'country-week'); ?></label>
            <input type="search" id="country-search" placeholder="<?php esc_attr_e('Search countries&hellip;', 'country-week'); ?>" data-filter-search>
        </p>

        <p class="country-filters__continents" data-filter-continents>
            <button type="button" class="is-active" data-continent="">
                <?php esc_html_e('All Continents', 'country-week'); ?>
            </button>
            <?php foreach ($continents as $continent) : ?>
                <button type="button" data-continent="<?php echo esc_attr($continent->name); ?>">
                    <?php echo esc_html($continent->name); ?>
                </button>
            <?php endforeach; ?>
        </p>

        <p class="country-filters__alpha" data-filter-alpha>
            <button type="button" class="is-active" data-letter="">
                <?php esc_html_e('A–Z', 'country-week'); ?>
            </button>
            <?php foreach (range('A', 'Z') as $letter) : ?>
                <button type="button" data-letter="<?php echo esc_attr($letter); ?>"><?php echo esc_html($letter); ?></button>
            <?php endforeach; ?>
        </p>
    </div>

    <p class="country-filters__empty" data-filter-empty hidden>
        <?php esc_html_e('No countries match your search.', 'country-week'); ?>
    </p>

    <ul class="country-grid" data-country-grid>
        <?php foreach ($countries as $country) :
            get_template_part('templates/parts/country-card', null, ['country' => $country]);
        endforeach; ?>
    </ul>
</main>

<?php get_footer(); ?>
