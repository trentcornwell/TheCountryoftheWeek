<?php
/**
 * A read-only wp-admin dashboard widget showing the current and
 * upcoming rotation — visibility without ever requiring action.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Admin;

use CountryWeek\Services\Country_Repository;
use CountryWeek\Services\Rotation_Service;
use CountryWeek\Utilities\Date_Utility;

if (!defined('ABSPATH')) {
    exit;
}

class Schedule_Dashboard_Widget
{
    public function register(): void
    {
        add_action('wp_dashboard_setup', [$this, 'add_widget']);
    }

    public function add_widget(): void
    {
        wp_add_dashboard_widget(
            'country_week_schedule',
            __('Country of the Week — Schedule', 'country-week'),
            [$this, 'render']
        );
    }

    public function render(): void
    {
        $countries = Country_Repository::get_all_ordered();
        $count = count($countries);

        if ($count === 0) {
            echo '<p>' . esc_html__('No countries are published yet.', 'country-week') . '</p>';

            return;
        }

        if (!Rotation_Service::has_started()) {
            $starts = Rotation_Service::start_date();
            $launch_country = $countries[Country_Repository::launch_offset()] ?? $countries[0];
            printf(
                '<p>%s</p>',
                esc_html(sprintf(
                    /* translators: 1: country name, 2: date. */
                    __('The rotation has not started yet. %1$s will be the first featured country, beginning %2$s.', 'country-week'),
                    get_the_title($launch_country),
                    Date_Utility::format_human($starts)
                ))
            );

            return;
        }

        // Deliberately goes through Country_Repository::get_active()/
        // get_by_offset() rather than re-deriving the array index from
        // Rotation_Service::active_index() directly here: the raw
        // rotation position must be translated through launch_offset()
        // to land on the right entry in the alphabetical $countries
        // array, and get_active()/get_by_offset() are the only place
        // that translation is guaranteed to happen correctly.
        $active = Country_Repository::get_active();

        if (!$active instanceof \WP_Post) {
            echo '<p>' . esc_html__('Unable to determine the active country.', 'country-week') . '</p>';

            return;
        }

        $active_date = Country_Repository::next_scheduled_date($active) ?? Date_Utility::now();
        $week_label = Date_Utility::week_range_label($active_date);

        printf(
            '<p><strong>%s</strong></p>',
            esc_html(sprintf(
                /* translators: 1: date range, 2: country name. */
                __('Week of %1$s: %2$s is currently featured.', 'country-week'),
                $week_label,
                get_the_title($active)
            ))
        );

        echo '<p>' . esc_html__('Next up:', 'country-week') . '</p><ol>';

        $cursor = $active;

        for ($offset = 1; $offset <= min(5, $count - 1); $offset++) {
            $cursor = Country_Repository::get_by_offset($cursor, 1);

            if (!$cursor instanceof \WP_Post) {
                break;
            }

            $date = Country_Repository::next_scheduled_date($cursor);

            printf(
                '<li>%s &mdash; %s</li>',
                esc_html(get_the_title($cursor)),
                esc_html($date ? Date_Utility::format_human($date) : '')
            );
        }

        echo '</ol>';
    }
}
