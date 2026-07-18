<?php
/**
 * The long-form narrative sections: Geography, History, Government,
 * Economy, People, and Culture. Each renders only if that field has
 * content, so a sparsely-filled stub country never shows empty headings.
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

// Which summaries are typically populated from the CIA World Factbook
// import (scripts/import-countries.php) vs. hand-written editorial
// content (people_summary, culture_section aren't in Factbook data) —
// used to show an accurate "Source" credit only where it applies.
$factbook_sourced = ['geography_summary', 'history_summary', 'government_summary', 'economy_summary'];

$sections = [
    'geography_summary' => __('Geography', 'country-week'),
    'history_summary' => __('History', 'country-week'),
    'government_summary' => __('Government', 'country-week'),
    'economy_summary' => __('Economy', 'country-week'),
    'people_summary' => __('People', 'country-week'),
    'culture_section' => __('Culture', 'country-week'),
];
?>
<?php foreach ($sections as $key => $label) :
    $text = get_post_meta($country->ID, $key, true);

    if (!is_string($text) || trim($text) === '') {
        continue;
    }
    ?>
    <section class="country-summary" aria-labelledby="summary-<?php echo esc_attr($key); ?>">
        <h2 id="summary-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></h2>
        <div class="country-summary__body">
            <?php echo wp_kses_post(wpautop($text)); ?>
        </div>
        <?php if (in_array($key, $factbook_sourced, true)) : ?>
            <p class="country-summary__source"><?php esc_html_e('Source: CIA World Factbook (public domain)', 'country-week'); ?></p>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
