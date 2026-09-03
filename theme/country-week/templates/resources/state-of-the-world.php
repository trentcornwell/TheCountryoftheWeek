<?php
/**
 * "The State of the World" resource page — video plus handouts/slides.
 * Reached via the /resources/state-of-the-world/ rewrite rule
 * registered in Hooks\Rewrite_Hooks, linked from the homepage banner
 * (templates/parts/resource-banner.php). Normal header.php/footer.php
 * chrome, unlike the standalone print/coloring templates.
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>
<main class="site-main" id="main">
    <article class="resource-page">
        <header class="resource-page__header">
            <p class="resource-page__eyebrow"><?php esc_html_e('Latest Resource', 'country-week'); ?></p>
            <h1><?php esc_html_e('The State of the World', 'country-week'); ?></h1>
            <p class="resource-page__meta">
                <?php esc_html_e('Presented September 2, 2026 by Travis Snode, Director of Vision Baptist Missions', 'country-week'); ?>
            </p>
        </header>

        <div class="resource-page__video">
            <iframe
                src="https://www.youtube.com/embed/P0S1w7hzKeA?start=2688"
                title="<?php esc_attr_e('The State of the World — Travis Snode', 'country-week'); ?>"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
            ></iframe>
        </div>

        <section class="resource-page__downloads" aria-labelledby="resource-downloads-heading">
            <h2 id="resource-downloads-heading"><?php esc_html_e('Handouts & Slides', 'country-week'); ?></h2>
            <p><?php esc_html_e('Coming soon.', 'country-week'); ?></p>
        </section>
    </article>
</main>
<?php get_footer(); ?>
