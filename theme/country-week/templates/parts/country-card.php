<?php
/**
 * A single card in the archive grid: flag, name, continent, and
 * rotation status (active/upcoming/last-featured date), built from
 * Country_Repository's derived schedule data rather than any stored
 * "status" field.
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\Services\Country_Repository;
use CountryWeek\Services\Rotation_Service;
use CountryWeek\Utilities\Date_Utility;

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

$flag_id = (int) get_post_meta($country->ID, 'flag_image_id', true);
$continents = get_the_terms($country->ID, 'continent');
$continent_name = (!is_wp_error($continents) && !empty($continents)) ? $continents[0]->name : '';

$status = '';

if (Rotation_Service::has_started()) {
    $cycle_position = Country_Repository::cycle_position_of($country->ID);
    $count = Country_Repository::count();

    if ($cycle_position !== null && $cycle_position === Rotation_Service::active_index($count)) {
        $status = __('Featured this week', 'country-week');
    } else {
        $next = Country_Repository::next_scheduled_date($country);
        $now = Date_Utility::now();

        if ($next && $next > $now) {
            /* translators: %s: date. */
            $status = sprintf(__('Upcoming: %s', 'country-week'), Date_Utility::format_human($next));
        } else {
            $recent = Country_Repository::most_recent_date($country);

            if ($recent) {
                /* translators: %s: date. */
                $status = sprintf(__('Featured %s', 'country-week'), Date_Utility::format_human($recent));
            }
        }
    }
}
?>
<li class="country-card" data-country-name="<?php echo esc_attr(get_the_title($country)); ?>" data-continent="<?php echo esc_attr($continent_name); ?>">
    <a href="<?php echo esc_url(get_permalink($country)); ?>" class="country-card__link">
        <?php if ($flag_id) : ?>
            <?php
            echo wp_get_attachment_image($flag_id, 'medium', false, [
                'loading' => 'lazy',
                'decoding' => 'async',
                'class' => 'country-card__flag',
                'alt' => '',
            ]);
            ?>
        <?php endif; ?>
        <span class="country-card__name"><?php echo esc_html(get_the_title($country)); ?></span>
        <?php if ($continent_name !== '') : ?>
            <span class="country-card__continent"><?php echo esc_html($continent_name); ?></span>
        <?php endif; ?>
        <?php if ($status !== '') : ?>
            <span class="country-card__status"><?php echo esc_html($status); ?></span>
        <?php endif; ?>
    </a>
</li>
