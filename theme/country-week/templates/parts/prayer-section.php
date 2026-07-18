<?php
/**
 * Prayer & Mission section. Content here is always manually authored
 * or licensed — never auto-imported from Operation World or similar
 * copyrighted sources (see Country_Meta_Fields field descriptions).
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\CPT\Country_Meta_Fields;

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

$intro = get_post_meta($country->ID, 'prayer_intro', true);
$points = Country_Meta_Fields::lines($country->ID, 'prayer_points');
$mission = get_post_meta($country->ID, 'mission_emphasis', true);
$source = get_post_meta($country->ID, 'prayer_source', true);

if ($intro === '' && empty($points) && $mission === '') {
    return;
}
?>
<section class="prayer-section" aria-labelledby="prayer-heading">
    <h2 id="prayer-heading"><?php esc_html_e('Pray for This Country', 'country-week'); ?></h2>

    <?php if (is_string($intro) && $intro !== '') : ?>
        <div class="prayer-section__intro"><?php echo wp_kses_post(wpautop($intro)); ?></div>
    <?php endif; ?>

    <?php if (!empty($points)) : ?>
        <ul class="prayer-section__points">
            <?php foreach ($points as $point) : ?>
                <li><?php echo esc_html($point); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (is_string($mission) && $mission !== '') : ?>
        <div class="prayer-section__mission">
            <h3><?php esc_html_e('Mission Emphasis', 'country-week'); ?></h3>
            <?php echo wp_kses_post(wpautop($mission)); ?>
        </div>
    <?php endif; ?>

    <?php if (is_string($source) && $source !== '') : ?>
        <p class="prayer-section__source">
            <?php
            printf(
                /* translators: %s: source name, e.g. "Operation World". */
                esc_html__('Source: %s', 'country-week'),
                esc_html($source)
            );
            ?>
        </p>
    <?php endif; ?>
</section>
