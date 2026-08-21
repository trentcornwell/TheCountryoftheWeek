<?php
/**
 * Single Missionary Review issue/index page: date, detected
 * country/person tags, excerpt, and a link to the original scan.
 *
 * @package CountryWeek
 */

use CountryWeek\CPT\Mrw_Taxonomies;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

while (have_posts()) :
    the_post();

    $issue = get_queried_object();
    $kind = get_post_meta(get_the_ID(), 'issue_kind', true);
    $volume_label = get_post_meta(get_the_ID(), 'volume_label', true);
    $source_url = get_post_meta(get_the_ID(), 'source_pdf_url', true);
    $countries = get_the_terms(get_the_ID(), Mrw_Taxonomies::COUNTRY);
    $persons = get_the_terms(get_the_ID(), Mrw_Taxonomies::PERSON);
    $countries = is_wp_error($countries) || !$countries ? [] : $countries;
    $persons = is_wp_error($persons) || !$persons ? [] : $persons;
    ?>

    <main class="site-main" id="main">
        <article class="mrw-issue">
            <header class="mrw-issue__header">
                <p class="mrw-issue__date">
                    <?php echo esc_html(get_the_date('F Y')); ?>
                    <?php if ($kind === 'index') : ?>
                        <span class="mrw-issue-card__badge"><?php esc_html_e('Yearly Index', 'country-week'); ?></span>
                    <?php endif; ?>
                </p>
                <h1><?php the_title(); ?></h1>
                <?php if ($volume_label) : ?>
                    <p class="mrw-issue__volume"><?php echo esc_html($volume_label); ?></p>
                <?php endif; ?>
                <p class="mrw-disclaimer">
                    <?php esc_html_e('Country and person tags below are detected automatically from OCR text and may be incomplete or inaccurate.', 'country-week'); ?>
                </p>
            </header>

            <?php if (get_the_excerpt() !== '') : ?>
                <p class="mrw-issue__excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
            <?php endif; ?>

            <?php if ($source_url) : ?>
                <p>
                    <a href="<?php echo esc_url($source_url); ?>" class="mrw-issue__source" rel="noopener" target="_blank">
                        <?php esc_html_e('Read the original scan (PDF) on cafis.org', 'country-week'); ?>
                    </a>
                </p>
            <?php endif; ?>

            <?php if (!empty($countries)) : ?>
                <section class="mrw-issue__tags">
                    <h2><?php esc_html_e('Countries mentioned', 'country-week'); ?></h2>
                    <p class="mrw-issue-card__chips">
                        <?php foreach ($countries as $term) : ?>
                            <a href="<?php echo esc_url(get_term_link($term)); ?>" class="mrw-chip mrw-chip--country"><?php echo esc_html($term->name); ?></a>
                        <?php endforeach; ?>
                    </p>
                </section>
            <?php endif; ?>

            <?php if (!empty($persons)) : ?>
                <section class="mrw-issue__tags">
                    <h2><?php esc_html_e('People mentioned', 'country-week'); ?></h2>
                    <p class="mrw-issue-card__chips">
                        <?php foreach ($persons as $term) : ?>
                            <a href="<?php echo esc_url(get_term_link($term)); ?>" class="mrw-chip mrw-chip--person"><?php echo esc_html($term->name); ?></a>
                        <?php endforeach; ?>
                    </p>
                </section>
            <?php endif; ?>

            <p>
                <a href="<?php echo esc_url(get_post_type_archive_link('mrw_issue')); ?>">
                    <?php esc_html_e('&larr; Back to the Missionary Review Archive', 'country-week'); ?>
                </a>
            </p>
        </article>
    </main>

    <?php
endwhile;

get_footer();
