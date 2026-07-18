<?php
/**
 * Search results — restricted to the `country` post type (see
 * Hooks\Rewrite_Hooks::restrict_search_to_countries()), rendered as
 * the same country-card grid the archive uses.
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="site-main" id="main">
    <header class="archive-header">
        <h1>
            <?php
            printf(
                /* translators: %s: search query. */
                esc_html__('Search results for: %s', 'country-week'),
                '<span>' . esc_html(get_search_query()) . '</span>'
            );
            ?>
        </h1>
    </header>

    <?php if (have_posts()) : ?>
        <ul class="country-grid">
            <?php
            while (have_posts()) :
                the_post();
                get_template_part('templates/parts/country-card', null, ['country' => get_post()]);
            endwhile;
            ?>
        </ul>
    <?php else : ?>
        <p><?php esc_html_e('No countries matched your search.', 'country-week'); ?></p>
        <?php get_search_form(); ?>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
