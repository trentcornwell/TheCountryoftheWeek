<?php
/**
 * Custom admin list table columns for the Country post type.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Admin;

use CountryWeek\CPT\Country_Post_Type;
use CountryWeek\Services\Country_Repository;
use CountryWeek\Services\Rotation_Service;
use CountryWeek\Utilities\Date_Utility;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The rotation is fully automatic (see Rotation_Service), but admins
 * still benefit from seeing at a glance where each country sits in the
 * schedule without leaving the post list. These columns are read-only
 * and derived — nothing here is stored or editable.
 */
class Admin_Columns
{
    public function register(): void
    {
        add_filter('manage_' . Country_Post_Type::POST_TYPE . '_posts_columns', [$this, 'add_columns']);
        add_action('manage_' . Country_Post_Type::POST_TYPE . '_posts_custom_column', [$this, 'render_column'], 10, 2);
    }

    public function add_columns(array $columns): array
    {
        $with_continent = [];

        foreach ($columns as $key => $label) {
            $with_continent[$key] = $label;

            if ($key === 'title') {
                $with_continent['continent'] = __('Continent', 'country-week');
                $with_continent['rotation_status'] = __('Rotation', 'country-week');
            }
        }

        return $with_continent;
    }

    public function render_column(string $column, int $post_id): void
    {
        if ($column === 'continent') {
            $terms = get_the_term_list($post_id, 'continent', '', ', ');
            echo $terms && !is_wp_error($terms) ? wp_kses_post($terms) : '&#8212;';

            return;
        }

        if ($column === 'rotation_status') {
            echo esc_html($this->rotation_status_label($post_id));
        }
    }

    private function rotation_status_label(int $post_id): string
    {
        $post = get_post($post_id);

        // A published post with no manifest_key at all (e.g. a one-off
        // feature like Martinique/Mayotte outside its single active
        // week — see data/one-off-features.json) has no cycle position
        // of its own, so it's only "in rotation" if a schedule override
        // gives it a date directly.
        if (!$post instanceof WP_Post || (Country_Repository::cycle_position_of($post_id) === null && Country_Repository::next_scheduled_date($post) === null)) {
            return __('Not in rotation (unpublished)', 'country-week');
        }

        if (!Rotation_Service::has_started()) {
            $date = Country_Repository::next_scheduled_date($post) ?? Rotation_Service::start_date();

            /* translators: %s: date. */
            return sprintf(__('Scheduled: %s', 'country-week'), Date_Utility::format_human($date));
        }

        $active = Country_Repository::get_active();

        if ($active instanceof WP_Post && $active->ID === $post_id) {
            return __('Active this week', 'country-week');
        }

        $next = Country_Repository::next_scheduled_date($post);
        $now = Date_Utility::now();

        if ($next && $next > $now) {
            /* translators: %s: date. */
            return sprintf(__('Upcoming: %s', 'country-week'), Date_Utility::format_human($next));
        }

        $recent = Country_Repository::most_recent_date($post);

        if ($recent === null) {
            return __('Not yet scheduled', 'country-week');
        }

        /* translators: %s: date. */
        return sprintf(__('Last featured: %s', 'country-week'), Date_Utility::format_human($recent));
    }
}
