<?php
/**
 * Missionary Review Archive: every fetched issue/index of The
 * Missionary Review of the World (1888-1939), searchable and
 * filterable by date, country, and person — server-rendered, real
 * WP_Query filtering (see Services\Mrw_Repository), no client-side
 * JavaScript. See docs/decisions/0004-missionary-review-archive-ingestion.md.
 *
 * @package CountryWeek
 */

use CountryWeek\Services\Mrw_Repository;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$request = wp_unslash($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$query = Mrw_Repository::query_from_request($request);
$country_options = Mrw_Repository::country_options();
$person_options = Mrw_Repository::person_options();

$selected_country = isset($request['mrw_country']) ? sanitize_title($request['mrw_country']) : '';
$selected_person = isset($request['mrw_person']) ? sanitize_text_field($request['mrw_person']) : '';
$search_value = isset($request['s']) ? sanitize_text_field($request['s']) : '';
$year_from_value = isset($request['mrw_year_from']) ? absint($request['mrw_year_from']) : '';
$year_to_value = isset($request['mrw_year_to']) ? absint($request['mrw_year_to']) : '';
?>

<main class="site-main" id="main">
    <header class="archive-header">
        <h1><?php post_type_archive_title(); ?></h1>
        <p>
            <?php esc_html_e('Scanned issues of The Missionary Review of the World, 1888-1939, from the public archive at cafis.org. Search by date, country, or person.', 'country-week'); ?>
        </p>
        <p class="mrw-disclaimer">
            <?php esc_html_e('Country and person tags below are detected automatically from OCR text and may be incomplete or inaccurate — they are a browsing aid, not a verified index.', 'country-week'); ?>
        </p>
    </header>

    <form method="get" class="mrw-filters" action="<?php echo esc_url(get_post_type_archive_link('mrw_issue')); ?>">
        <p>
            <label for="mrw-search"><?php esc_html_e('Search text', 'country-week'); ?></label>
            <input type="search" id="mrw-search" name="s" value="<?php echo esc_attr($search_value); ?>" placeholder="<?php esc_attr_e('Keyword&hellip;', 'country-week'); ?>">
        </p>

        <p>
            <label for="mrw-country"><?php esc_html_e('Country mentioned', 'country-week'); ?></label>
            <select id="mrw-country" name="mrw_country">
                <option value=""><?php esc_html_e('Any country', 'country-week'); ?></option>
                <?php foreach ($country_options as $country_term) : ?>
                    <option value="<?php echo esc_attr($country_term->slug); ?>" <?php selected($selected_country, $country_term->slug); ?>>
                        <?php echo esc_html($country_term->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="mrw-person"><?php esc_html_e('Person mentioned', 'country-week'); ?></label>
            <input type="text" id="mrw-person" name="mrw_person" list="mrw-person-options" value="<?php echo esc_attr($selected_person); ?>" placeholder="<?php esc_attr_e('Start typing a name&hellip;', 'country-week'); ?>">
            <datalist id="mrw-person-options">
                <?php foreach ($person_options as $person_term) : ?>
                    <option value="<?php echo esc_attr($person_term->name); ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </p>

        <p class="mrw-filters__years">
            <label for="mrw-year-from"><?php esc_html_e('Year from', 'country-week'); ?></label>
            <input type="number" id="mrw-year-from" name="mrw_year_from" min="1888" max="1939" value="<?php echo esc_attr($year_from_value); ?>">

            <label for="mrw-year-to"><?php esc_html_e('to', 'country-week'); ?></label>
            <input type="number" id="mrw-year-to" name="mrw_year_to" min="1888" max="1939" value="<?php echo esc_attr($year_to_value); ?>">
        </p>

        <p>
            <button type="submit"><?php esc_html_e('Search', 'country-week'); ?></button>
            <?php if ($search_value || $selected_country || $selected_person || $year_from_value || $year_to_value) : ?>
                <a href="<?php echo esc_url(get_post_type_archive_link('mrw_issue')); ?>" class="mrw-filters__clear"><?php esc_html_e('Clear filters', 'country-week'); ?></a>
            <?php endif; ?>
        </p>
    </form>

    <?php if ($query->have_posts()) : ?>
        <p class="mrw-results-count">
            <?php
            printf(
                /* translators: %s: number of matching issues. */
                esc_html(_n('%s issue found', '%s issues found', $query->found_posts, 'country-week')),
                esc_html(number_format_i18n($query->found_posts))
            );
            ?>
        </p>

        <ul class="mrw-issue-grid">
            <?php foreach ($query->posts as $issue) :
                get_template_part('templates/parts/mrw-issue-card', null, ['issue' => $issue]);
            endforeach; ?>
        </ul>

        <?php
        $pagination = paginate_links([
            'base' => esc_url_raw(add_query_arg('mrw_page', '%#%')),
            'format' => '',
            'current' => max(1, absint($request['mrw_page'] ?? 1)),
            'total' => $query->max_num_pages,
            'prev_text' => __('&laquo; Previous', 'country-week'),
            'next_text' => __('Next &raquo;', 'country-week'),
        ]);

        if ($pagination) :
            ?>
            <nav class="mrw-pagination" aria-label="<?php esc_attr_e('Missionary Review Archive pages', 'country-week'); ?>">
                <?php echo $pagination; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <p class="mrw-results-empty"><?php esc_html_e('No issues match your search.', 'country-week'); ?></p>
    <?php endif; ?>
</main>

<?php
wp_reset_postdata();
get_footer();
