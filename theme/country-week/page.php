<?php
/**
 * Generic Page template (About, and any other plain content page that
 * doesn't have its own page-{slug}.php).
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<main class="site-main" id="main">
    <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class(); ?>>
            <header class="page-header">
                <h1><?php the_title(); ?></h1>
            </header>
            <div class="page-content">
                <?php the_content(); ?>
            </div>
        </article>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>
