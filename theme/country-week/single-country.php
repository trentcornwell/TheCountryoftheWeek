<?php
/**
 * Single Country template — works for any country, whether it's
 * currently featured, already had its turn, or is scheduled for a
 * future week. Reuses the exact same display partial as the homepage
 * (templates/parts/country-display.php) so there is only one place
 * that defines "what a country page looks like."
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

while (have_posts()) :
    the_post();

    $country = get_post();
    $count = Country_Repository::count();
    $cycle_position = Country_Repository::cycle_position_of($country->ID);
    $active_position = Rotation_Service::has_started() ? Rotation_Service::active_index($count) : null;
    $is_active = $cycle_position !== null && $active_position !== null && $cycle_position === $active_position;

    $banner = '';

    if ($is_active) {
        $banner = '<p class="country-hero__status country-hero__status--active">' . esc_html__('Featured This Week', 'country-week') . '</p>';
    } elseif ($cycle_position !== null) {
        $next = Country_Repository::next_scheduled_date($country);
        $now = Date_Utility::now();

        if ($next && (!Rotation_Service::has_started() || $next > $now)) {
            $banner = '<p class="country-hero__status">' . esc_html(sprintf(
                /* translators: %s: date. */
                __('Scheduled to be featured %s', 'country-week'),
                Date_Utility::format_human($next)
            )) . '</p>';
        } else {
            $recent = Country_Repository::most_recent_date($country);

            if ($recent) {
                $banner = '<p class="country-hero__status">' . esc_html(sprintf(
                    /* translators: %s: date. */
                    __('Featured %s', 'country-week'),
                    Date_Utility::format_human($recent)
                )) . '</p>';
            }
        }
    }
    ?>

    <main class="site-main" id="main">
        <?php get_template_part('templates/parts/country-display', null, ['country' => $country, 'banner' => $banner]); ?>
    </main>

<?php
endwhile;

get_footer();
