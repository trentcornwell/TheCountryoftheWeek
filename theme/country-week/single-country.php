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
    $active = Country_Repository::get_active();
    $is_active = $active instanceof WP_Post && $active->ID === $country->ID;

    $banner = '';

    if ($is_active) {
        $banner = '<p class="country-hero__status country-hero__status--active">' . esc_html__('Featured This Week', 'country-week') . '</p>';
    } else {
        // next_scheduled_date()/most_recent_date() return null for a
        // post with neither a rotation position nor a schedule
        // override (see Services\Schedule_Override) — e.g. a one-off
        // feature post before its data/schedule-overrides.json entry
        // exists yet — so no explicit "is this country in the
        // rotation" gate is needed here.
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
