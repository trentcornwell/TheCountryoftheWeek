<?php
/**
 * Previous/Next country arrow navigation, shown at the top of every
 * country page (including the homepage, which always displays the
 * currently active country) so visitors can browse the rotation
 * forward or backward without hunting for a link.
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\Services\Country_Repository;
use CountryWeek\Utilities\Date_Utility;

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

$previous = Country_Repository::get_by_offset($country, -1);
$next = Country_Repository::get_by_offset($country, 1);
$scheduled_date = Country_Repository::next_scheduled_date($country);
$week_label = $scheduled_date ? Date_Utility::week_range_label($scheduled_date) : null;

if (!$previous && !$next) {
    return;
}
?>
<nav class="country-nav-arrows" aria-label="<?php esc_attr_e('Browse countries', 'country-week'); ?>">
    <?php if ($previous) : ?>
        <a class="country-nav-arrows__link country-nav-arrows__link--prev" href="<?php echo esc_url(get_permalink($previous)); ?>">
            <span class="country-nav-arrows__arrow" aria-hidden="true">&larr;</span>
            <span class="country-nav-arrows__label">
                <span class="country-nav-arrows__eyebrow"><?php esc_html_e('Previous', 'country-week'); ?></span>
                <span class="country-nav-arrows__name"><?php echo esc_html(get_the_title($previous)); ?></span>
            </span>
        </a>
    <?php else : ?>
        <span></span>
    <?php endif; ?>

    <?php if ($week_label !== null) : ?>
        <span class="country-nav-arrows__week">
            <?php
            printf(
                /* translators: %s: date range, e.g. "July 19–25, 2026". */
                esc_html__('Week of %s', 'country-week'),
                esc_html($week_label)
            );
            ?>
        </span>
    <?php endif; ?>

    <?php if ($next) : ?>
        <a class="country-nav-arrows__link country-nav-arrows__link--next" href="<?php echo esc_url(get_permalink($next)); ?>">
            <span class="country-nav-arrows__label">
                <span class="country-nav-arrows__eyebrow"><?php esc_html_e('Next', 'country-week'); ?></span>
                <span class="country-nav-arrows__name"><?php echo esc_html(get_the_title($next)); ?></span>
            </span>
            <span class="country-nav-arrows__arrow" aria-hidden="true">&rarr;</span>
        </a>
    <?php else : ?>
        <span></span>
    <?php endif; ?>
</nav>
