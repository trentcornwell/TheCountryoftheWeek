<?php
/**
 * 404 template.
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="site-main" id="main">
    <header class="page-header">
        <h1><?php esc_html_e('Page Not Found', 'country-week'); ?></h1>
        <p><?php esc_html_e('The page you were looking for doesn\'t exist. Try searching for a country instead.', 'country-week'); ?></p>
    </header>

    <?php get_search_form(); ?>

    <p>
        <a href="<?php echo esc_url((string) get_post_type_archive_link('country')); ?>">
            <?php esc_html_e('Browse all countries', 'country-week'); ?>
        </a>
    </p>
</main>

<?php get_footer(); ?>
