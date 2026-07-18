<?php
/**
 * "Join Us in Prayer" page (applies automatically to a Page with the
 * slug /join-us-in-prayer/). Collects name, church, email, and when
 * the visitor started praying through the world with us, storing
 * submissions as Prayer_Partner_Post_Type posts for follow-up.
 *
 * @package CountryWeek
 */

use CountryWeek\Forms\Prayer_Partner_Form;

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="site-main" id="main">
    <header class="page-header">
        <h1><?php the_title(); ?></h1>
        <p><?php esc_html_e('Join churches around the world praying through one country at a time. Tell us a bit about yourself and we\'ll send you additional helpful resources.', 'country-week'); ?></p>
    </header>

    <?php while (have_posts()) : the_post(); ?>
        <div class="page-content"><?php the_content(); ?></div>
    <?php endwhile; ?>

    <?php echo (new Prayer_Partner_Form())->render(); ?>
</main>

<?php get_footer(); ?>
