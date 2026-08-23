<?php
/**
 * A single card in the Missionary Review archive grid: date, detected
 * country/person chips, excerpt, and links to the issue's own page and
 * the original scan.
 *
 * Expects $args['issue'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\CPT\Mrw_Taxonomies;

if (!defined('ABSPATH')) {
    exit;
}

$issue = $args['issue'] ?? null;

if (!$issue instanceof WP_Post) {
    return;
}

$kind = get_post_meta($issue->ID, 'issue_kind', true);
$source_url = get_post_meta($issue->ID, 'source_pdf_url', true);
$countries = get_the_terms($issue->ID, Mrw_Taxonomies::COUNTRY);
$persons = get_the_terms($issue->ID, Mrw_Taxonomies::PERSON);
$countries = is_wp_error($countries) || !$countries ? [] : $countries;
$persons = is_wp_error($persons) || !$persons ? [] : $persons;
?>
<li class="mrw-issue-card">
    <a href="<?php echo esc_url(get_permalink($issue)); ?>" class="mrw-issue-card__link">
        <span class="mrw-issue-card__date">
            <?php echo esc_html(get_the_date('F Y', $issue)); ?>
            <?php if ($kind === 'index') : ?>
                <span class="mrw-issue-card__badge"><?php esc_html_e('Yearly Index', 'country-week'); ?></span>
            <?php endif; ?>
        </span>

        <span class="mrw-issue-card__title"><?php echo esc_html(get_the_title($issue)); ?></span>

        <?php if (get_the_excerpt($issue) !== '') : ?>
            <span class="mrw-issue-card__excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt($issue), 28)); ?></span>
        <?php endif; ?>

        <?php if (!empty($countries)) : ?>
            <span class="mrw-issue-card__chips">
                <?php foreach (array_slice($countries, 0, 6) as $country_term) : ?>
                    <span class="mrw-chip mrw-chip--country"><?php echo esc_html($country_term->name); ?></span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>

        <?php if (!empty($persons)) : ?>
            <span class="mrw-issue-card__chips">
                <?php foreach (array_slice($persons, 0, 4) as $person_term) : ?>
                    <span class="mrw-chip mrw-chip--person"><?php echo esc_html($person_term->name); ?></span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>
    </a>

    <?php if ($source_url) : ?>
        <a href="<?php echo esc_url($source_url); ?>" class="mrw-issue-card__source" rel="noopener" target="_blank">
            <?php esc_html_e('Read the original scan (PDF)', 'country-week'); ?>
        </a>
    <?php endif; ?>
</li>
