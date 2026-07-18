<?php
/**
 * Page titles, meta descriptions, and canonical URLs — hand-rolled with
 * native WordPress hooks since no SEO plugin is used.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Seo;

use CountryWeek\CPT\Country_Post_Type;
use CountryWeek\Services\Country_Repository;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WordPress core already outputs a correct rel="canonical" link for
 * every page (see wp-includes/general-template.php: rel_canonical()),
 * so this class does not duplicate that. It only supplies the two
 * things core leaves generic: the title tag content for country pages,
 * and a meta description tag (which core does not emit at all).
 */
class Seo_Fields
{
    public function register(): void
    {
        add_filter('document_title_parts', [$this, 'filter_title_parts']);
        add_action('wp_head', [$this, 'output_meta_description'], 1);
    }

    public function filter_title_parts(array $title): array
    {
        if (is_singular(Country_Post_Type::POST_TYPE)) {
            $country = get_queried_object();
            $title['title'] = sprintf(
                /* translators: %s: country name. */
                __('%s — Facts, Prayer & Culture', 'country-week'),
                get_the_title($country)
            );
        } elseif (is_front_page()) {
            $active = Country_Repository::get_active();

            if ($active) {
                $title['title'] = sprintf(
                    /* translators: %s: country name. */
                    __('%s is This Week\'s Country', 'country-week'),
                    get_the_title($active)
                );
            }
        }

        return $title;
    }

    public function output_meta_description(): void
    {
        $description = $this->build_description();

        if ($description === '') {
            return;
        }

        printf(
            '<meta name="description" content="%s">' . "\n",
            esc_attr($description)
        );
    }

    private function build_description(): string
    {
        if (is_singular(Country_Post_Type::POST_TYPE)) {
            return $this->description_for_country(get_queried_object());
        }

        if (is_front_page()) {
            $active = Country_Repository::get_active();

            if ($active) {
                return $this->description_for_country($active);
            }

            return __('A new country is featured every week. Learn about its people, culture, and how to pray for it.', 'country-week');
        }

        if (is_post_type_archive(Country_Post_Type::POST_TYPE)) {
            return __('Browse every country ever featured on The Country of the Week, searchable and filterable by continent.', 'country-week');
        }

        return '';
    }

    private function description_for_country(\WP_Post $country): string
    {
        $excerpt = has_excerpt($country) ? get_the_excerpt($country) : '';

        if ($excerpt !== '') {
            return wp_strip_all_tags($excerpt);
        }

        $summary = get_post_meta($country->ID, 'geography_summary', true);

        if (!is_string($summary) || $summary === '') {
            $summary = get_post_meta($country->ID, 'history_summary', true);
        }

        if (is_string($summary) && $summary !== '') {
            return wp_trim_words(wp_strip_all_tags($summary), 30);
        }

        return sprintf(
            /* translators: %s: country name. */
            __('Learn about %s: quick facts, culture, history, and how to pray for its people.', 'country-week'),
            get_the_title($country)
        );
    }
}
