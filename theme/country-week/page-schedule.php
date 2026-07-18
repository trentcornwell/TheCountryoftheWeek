<?php
/**
 * "Schedule" page (applies automatically to a Page with the slug
 * /schedule/, per WordPress's page-{slug}.php template convention).
 * Lists the full perpetual rotation: recently featured, active, and
 * upcoming countries, entirely derived from Rotation_Service — nothing
 * here is hand-maintained.
 *
 * @package CountryWeek
 */

use CountryWeek\Services\Country_Repository;
use CountryWeek\Services\Rotation_Service;
use CountryWeek\Utilities\Date_Utility;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$countries = Country_Repository::get_all_ordered();
$count = count($countries);
$has_started = Rotation_Service::has_started();
$active_position = $has_started ? Rotation_Service::active_index($count) : -1;
$launch_offset = Country_Repository::launch_offset();

/**
 * Build the rotation-ordered list (starting at the launch country) so
 * "previous weeks" and "future weeks" read in the actual order they
 * occur, not plain alphabetical order.
 */
$rotation_sequence = [];

for ($position = 0; $position < $count; $position++) {
    $index = ($launch_offset + $position) % $count;
    $rotation_sequence[] = ['country' => $countries[$index], 'position' => $position];
}
?>

<main class="site-main" id="main">
    <header class="schedule-header">
        <h1><?php the_title(); ?></h1>
        <?php if ($count > 0) : ?>
            <p>
                <?php
                printf(
                    /* translators: 1: country count, 2: date. */
                    esc_html__('%1$d countries rotate on a perpetual weekly schedule, beginning %2$s. After the last country, the schedule repeats from the beginning.', 'country-week'),
                    (int) $count,
                    esc_html(Date_Utility::format_human(Rotation_Service::start_date()))
                );
                ?>
            </p>
        <?php endif; ?>
    </header>

    <ol class="schedule-list">
        <?php foreach ($rotation_sequence as $entry) :
            $country = $entry['country'];
            $position = $entry['position'];
            $date = Rotation_Service::date_for_index($position, $count);
            $is_active = $has_started && $position === $active_position;
            $is_past = $has_started && $date < Date_Utility::now() && !$is_active;
            ?>
            <li class="schedule-list__item<?php echo $is_active ? ' schedule-list__item--active' : ''; ?><?php echo $is_past ? ' schedule-list__item--past' : ''; ?>">
                <span class="schedule-list__week">
                    <?php
                    printf(
                        /* translators: %s: date range, e.g. "July 19–25, 2026". */
                        esc_html__('Week of %s', 'country-week'),
                        esc_html(Date_Utility::week_range_label($date))
                    );
                    ?>
                </span>
                <a href="<?php echo esc_url(get_permalink($country)); ?>"><?php echo esc_html(get_the_title($country)); ?></a>
                <?php if ($is_active) : ?>
                    <span class="schedule-list__badge"><?php esc_html_e('This Week', 'country-week'); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</main>

<?php get_footer(); ?>
