<?php
/**
 * "Suggest an Edit" page (applies automatically to a Page with the
 * slug /suggest-an-edit/). Shows the same form used in each country's
 * dialog, but with a country picker since there's no specific country
 * in context here.
 *
 * @package CountryWeek
 */

use CountryWeek\Forms\Suggest_Edit_Form;

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="site-main" id="main">
    <header class="page-header">
        <h1><?php the_title(); ?></h1>
        <p><?php esc_html_e('Spot outdated information or a factual error? Let us know and we\'ll review it.', 'country-week'); ?></p>
    </header>

    <?php while (have_posts()) : the_post(); ?>
        <div class="page-content"><?php the_content(); ?></div>
    <?php endwhile; ?>

    <?php echo (new Suggest_Edit_Form())->render(); ?>
</main>

<?php get_footer(); ?>
