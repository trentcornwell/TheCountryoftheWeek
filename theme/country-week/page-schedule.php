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

use CountryWeek\Services\Country_Manifest;
use CountryWeek\Services\Country_Repository;
use CountryWeek\Services\Rotation_Service;
use CountryWeek\Services\Schedule_Override;
use CountryWeek\Utilities\Date_Utility;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$countries = Country_Repository::get_all_ordered();
$count = count($countries);
$has_started = Rotation_Service::has_started();
$launch_offset = Country_Repository::launch_offset();
$current_week_start = Rotation_Service::current_week_start();

/**
 * Build the rotation-ordered list (starting at the launch country) so
 * "previous weeks" and "future weeks" read in the actual order they
 * occur, not plain alphabetical order. Each position's date/country is
 * override-aware (see Services\Schedule_Override / docs/decisions/0006):
 * a week with an override shows that override's country instead of the
 * natural one, and the natural country whose slot was taken this cycle
 * is simply omitted here rather than shown at a wrong date — it still
 * has its own row at its own (possibly much later, next-cycle)
 * occurrence, which is outside this one-cycle-long listing.
 */
$rotation_sequence = [];

for ($position = 0; $position < $count; $position++) {
    $date = Rotation_Service::date_for_index($position, $count);
    $override_key = Schedule_Override::key_for_week($date);

    if ($override_key !== null) {
        $country = Country_Repository::find_by_key($override_key);

        if (!$country instanceof WP_Post) {
            // Content for this one-off week isn't published yet —
            // never claim a country for it.
            continue;
        }
    } else {
        $natural_country = $countries[($launch_offset + $position) % $count];
        $natural_key = get_post_meta($natural_country->ID, Country_Manifest::meta_key(), true);
        $own_override = Schedule_Override::week_for_key($natural_key);

        if ($own_override !== null && $own_override->format('Y-m-d') !== $date->format('Y-m-d')) {
            // This country's natural slot was handed to someone else
            // this cycle; it already has (or will have) its own row at
            // its own overridden date instead of here.
            continue;
        }

        $country = $natural_country;
    }

    $rotation_sequence[] = ['country' => $country, 'date' => $date];
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
            $date = $entry['date'];
            $is_active = $has_started && $date->format('Y-m-d') === $current_week_start->format('Y-m-d');
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
