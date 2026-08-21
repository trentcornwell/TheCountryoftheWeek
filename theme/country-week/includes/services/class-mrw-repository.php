<?php
/**
 * Turns the Missionary Review archive's filter form into a real,
 * server-side, paginated WP_Query.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use CountryWeek\CPT\Mrw_Issue_Post_Type;
use CountryWeek\CPT\Mrw_Taxonomies;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deliberately NOT the client-side "render everything, filter in the
 * DOM" approach `country-filter.js` uses for the ~196-item country
 * archive (see archive-country.php) — at ~670 issues plus free-text
 * search, that would either blow the JS/data budget in
 * docs/PERFORMANCE.md or ship far more markup than a visitor needs.
 * Instead this is a plain `<form method="get">` (see archive-mrw_issue.php)
 * with zero new JavaScript: every filter is a real WP_Query arg, and
 * results are genuinely paginated. Uses its own `mrw_page` query var
 * rather than WordPress's `paged`, so this secondary query's pagination
 * never collides with the main query on the same archive template.
 */
class Mrw_Repository
{
    public const PER_PAGE = 24;

    public static function query_from_request(array $get): WP_Query
    {
        $args = [
            'post_type' => Mrw_Issue_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => self::PER_PAGE,
            'paged' => max(1, absint($get['mrw_page'] ?? 1)),
            'orderby' => 'date',
            'order' => 'ASC',
            'ignore_sticky_posts' => true,
        ];

        $search = isset($get['s']) ? sanitize_text_field(wp_unslash($get['s'])) : '';

        if ($search !== '') {
            $args['s'] = $search;
        }

        $tax_query = [];

        $country = isset($get['mrw_country']) ? sanitize_title(wp_unslash($get['mrw_country'])) : '';

        if ($country !== '') {
            $tax_query[] = ['taxonomy' => Mrw_Taxonomies::COUNTRY, 'field' => 'slug', 'terms' => $country];
        }

        // Matched by exact term NAME, not slug: the filter form is a
        // plain text input with a <datalist> of suggested names (no JS
        // available to translate a typed label into a hidden slug
        // field), so the value submitted is whatever the visitor typed
        // or picked — normally an exact name from the datalist.
        $person = isset($get['mrw_person']) ? trim(sanitize_text_field(wp_unslash($get['mrw_person']))) : '';

        if ($person !== '') {
            $tax_query[] = ['taxonomy' => Mrw_Taxonomies::PERSON, 'field' => 'name', 'terms' => $person];
        }

        if ($tax_query !== []) {
            $args['tax_query'] = $tax_query;
        }

        $year_from = isset($get['mrw_year_from']) ? absint($get['mrw_year_from']) : 0;
        $year_to = isset($get['mrw_year_to']) ? absint($get['mrw_year_to']) : 0;

        if ($year_from || $year_to) {
            $date_query = ['inclusive' => true];

            if ($year_from) {
                $date_query['after'] = sprintf('%04d-01-01', $year_from);
            }

            if ($year_to) {
                $date_query['before'] = sprintf('%04d-12-31', $year_to);
            }

            $args['date_query'] = [$date_query];
        }

        return new WP_Query($args);
    }

    /**
     * @return \WP_Term[]
     */
    public static function country_options(): array
    {
        $terms = get_terms([
            'taxonomy' => Mrw_Taxonomies::COUNTRY,
            'hide_empty' => true,
            'orderby' => 'name',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    /**
     * Capped and ordered by how often a name is tagged, not
     * alphabetically — with a five-decade OCR'd archive this list can
     * run into the thousands, and a plain <select> with everything
     * would be unusable. The archive template renders this into a
     * <datalist> behind a text input instead of a dropdown.
     *
     * @return \WP_Term[]
     */
    public static function person_options(int $limit = 500): array
    {
        $terms = get_terms([
            'taxonomy' => Mrw_Taxonomies::PERSON,
            'hide_empty' => true,
            'orderby' => 'count',
            'order' => 'DESC',
            'number' => $limit,
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }
}
