<?php
/**
 * Homepage: shows whichever country Services\Rotation_Service says is
 * currently active. Nothing here is manually selected — see
 * Services\Country_Repository::get_active().
 *
 * Before the rotation's official start date, the homepage instead
 * previews the launch country (still fully derived from the manifest,
 * never hand-picked) with a "coming soon" banner instead of "Featured
 * This Week". This is purely a display choice for the pre-launch
 * window — it does not touch Rotation_Service::ROTATION_START or any
 * date math, so the real rotation still flips at the exact intended
 * Sunday-midnight boundary once it arrives, with no discontinuity.
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

$active_country = Country_Repository::get_active();
$banner = '';

if (!$active_country instanceof WP_Post) {
    $all_countries = Country_Repository::get_all_ordered();
    $active_country = $all_countries[Country_Repository::launch_offset()] ?? null;

    if ($active_country instanceof WP_Post) {
        $banner = '<p class="country-hero__status">' . esc_html(sprintf(
            /* translators: %s: date. */
            __('Coming %s — sneak peek', 'country-week'),
            Date_Utility::format_human(Rotation_Service::start_date())
        )) . '</p>';
    }
}
?>

<main class="site-main" id="main">
    <?php if ($active_country instanceof WP_Post) : ?>
        <p class="site-main__tagline"><?php esc_html_e('Explore the world one country at a time.', 'country-week'); ?></p>
        <?php get_template_part('templates/parts/country-display', null, ['country' => $active_country, 'banner' => $banner]); ?>
    <?php else : ?>
        <section class="launch-countdown">
            <h1><?php bloginfo('name'); ?></h1>
            <p><?php esc_html_e('Explore the world one country at a time.', 'country-week'); ?></p>
        </section>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
