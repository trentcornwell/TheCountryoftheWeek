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
        <p class="resource-page__eyebrow"><?php esc_html_e('Latest Resource', 'country-week'); ?></p>

        <div class="resource-page__video">
            <iframe
                src="https://www.youtube.com/embed/P0S1w7hzKeA?start=2688"
                title="<?php esc_attr_e('The State of the World — Travis Snode', 'country-week'); ?>"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen
            ></iframe>
        </div>

        <header class="resource-page__header">
            <h1><?php esc_html_e('The State of the World', 'country-week'); ?></h1>
            <p class="resource-page__meta">
                <?php esc_html_e('Presented September 2, 2026 by Travis Snode, Director of Vision Baptist Missions', 'country-week'); ?>
            </p>
            <p class="resource-page__description">
                <?php
                esc_html_e(
                    'At the beginning of Vision Baptist Church\'s annual Missions Conference in 2026, Travis Snode presented "The State of the World in Our Generation." Below is the video and the resources used. We encourage you to take time to see the need and pray.',
                    'country-week'
                );
                ?>
            </p>
            <p class="resource-page__description">
                <?php
                esc_html_e(
                    'We would encourage your church to take time in a service to walk through these facts. It is a worthy use of time to inform ourselves of the needs around the world. Please, allow this website to be a helpful resource as you work to learn more about our world.',
                    'country-week'
                );
                ?>
            </p>
        </header>

        <section class="resource-page__downloads" aria-labelledby="resource-downloads-heading">
            <h2 id="resource-downloads-heading"><?php esc_html_e('Handouts & Slides', 'country-week'); ?></h2>
            <ul class="resource-page__downloads-list">
                <li>
                    <a href="<?php echo esc_url(get_theme_file_uri('assets/pdf/state-of-the-world-handout.pdf')); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('Download the 8-page handout (PDF)', 'country-week'); ?>
                    </a>
                </li>
                <li>
                    <a href="<?php echo esc_url(get_theme_file_uri('assets/pdf/state-of-the-world-slides.pdf')); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e('Download the presentation slides (PDF)', 'country-week'); ?>
                    </a>
                </li>
            </ul>
        </section>
    </article>
</main>
<?php get_footer(); ?>
