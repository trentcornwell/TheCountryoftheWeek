<?php
/**
 * Per-country photo slideshow viewer. Reached via the /slideshow/
 * rewrite endpoint on a country's own permalink (see Hooks\Rewrite_Hooks),
 * only ever loaded for a country that actually has one — see
 * Services\Slideshow_Service. Normal header.php/footer.php chrome,
 * unlike the standalone print/coloring templates, since this is for
 * on-site viewing/sharing rather than printing.
 *
 * @package CountryWeek
 */

use CountryWeek\Services\Slideshow_Service;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $country = get_post();
    $slides = Slideshow_Service::slide_urls($country);
    $total = count($slides);

    if ($total === 0) {
        get_template_part('404');
        break;
    }

    // Deliberately not named "slide": Hooks\Rewrite_Hooks already
    // registers a `slide` rewrite endpoint/query var for the
    // presentation-PNG download feature, and its handler treats *any*
    // request carrying a `slide` query var (this pagination link
    // included) as a request for that binary PNG — caught by hand when
    // ?slide=5 returned raw PNG bytes instead of the fifth photo.
    $current = isset($_GET['photo']) ? absint($_GET['photo']) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pagination, no state change.
    $current = max(1, min($total, $current));
    $index = $current - 1;

    $base_url = Slideshow_Service::viewer_url($country);
    $prev_url = $current > 1 ? add_query_arg('photo', $current - 1, $base_url) : '';
    $next_url = $current < $total ? add_query_arg('photo', $current + 1, $base_url) : '';
    ?>

    <main class="site-main" id="main">
        <article class="country-slideshow">
            <header class="country-slideshow__header">
                <p class="country-slideshow__eyebrow"><?php esc_html_e('Slideshow', 'country-week'); ?></p>
                <h1><?php echo esc_html(get_the_title($country)); ?></h1>
                <a class="country-slideshow__back" href="<?php echo esc_url(get_permalink($country)); ?>">
                    <?php
                    printf(
                        /* translators: %s: country name. */
                        esc_html__('&larr; Back to %s', 'country-week'),
                        esc_html(get_the_title($country))
                    );
                    ?>
                </a>
            </header>

            <div class="country-slideshow__stage">
                <img
                    src="<?php echo esc_url($slides[$index]); ?>"
                    alt="<?php
                        printf(
                            /* translators: 1: slide number, 2: total slides, 3: country name. */
                            esc_attr__('Slide %1$d of %2$d: %3$s', 'country-week'),
                            absint($current),
                            absint($total),
                            esc_attr(get_the_title($country))
                        );
                    ?>"
                >
            </div>

            <?php if ($total > 1) : ?>
                <nav class="country-slideshow__nav" aria-label="<?php esc_attr_e('Slideshow navigation', 'country-week'); ?>">
                    <?php if ($prev_url !== '') : ?>
                        <a class="country-slideshow__nav-link" href="<?php echo esc_url($prev_url); ?>">&larr; <?php esc_html_e('Previous', 'country-week'); ?></a>
                    <?php else : ?>
                        <span class="country-slideshow__nav-link country-slideshow__nav-link--disabled">&larr; <?php esc_html_e('Previous', 'country-week'); ?></span>
                    <?php endif; ?>

                    <span class="country-slideshow__count">
                        <?php
                        printf(
                            /* translators: 1: current slide, 2: total slides. */
                            esc_html__('%1$d of %2$d', 'country-week'),
                            absint($current),
                            absint($total)
                        );
                        ?>
                    </span>

                    <?php if ($next_url !== '') : ?>
                        <a class="country-slideshow__nav-link" href="<?php echo esc_url($next_url); ?>"><?php esc_html_e('Next', 'country-week'); ?> &rarr;</a>
                    <?php else : ?>
                        <span class="country-slideshow__nav-link country-slideshow__nav-link--disabled"><?php esc_html_e('Next', 'country-week'); ?> &rarr;</span>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        </article>
    </main>

<?php
endwhile;

get_footer();
