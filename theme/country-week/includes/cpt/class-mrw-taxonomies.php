<?php
/**
 * Registers the `mrw_country` and `mrw_person` taxonomies for the
 * `mrw_issue` post type.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Both taxonomies are flat (tag-like) and both hold automated, unreviewed
 * mentions detected from OCR text by scripts/mrw-fetch-extract.php — not
 * editorially verified facts (see docs/decisions/0004-missionary-review-archive-ingestion.md).
 * `mrw_country` term slugs deliberately reuse the same manifest `key`
 * values as the `country` CPT (see includes/data/country-manifest.json)
 * so a term can be cross-linked to the matching country page; historical
 * names in the source prose (e.g. "Siam", "Persia") are aliased to their
 * modern manifest key at extraction time, not stored as separate terms.
 *
 * Both are registered with `query_var => false`: a public taxonomy
 * otherwise auto-registers a flat `?mrw_country=slug` query var that
 * WordPress's *main* query intercepts itself (turning it into a
 * taxonomy-archive request and 404ing for any slug that isn't a real
 * term) — which collided with archive-mrw_issue.php's own filter form,
 * whose fields are named exactly `mrw_country`/`mrw_person` and are
 * meant to be read only by Services\Mrw_Repository's secondary query.
 * Pretty term-archive URLs (get_term_link(), is_tax()) are unaffected —
 * only the flat query-string form is disabled.
 */
class Mrw_Taxonomies
{
    public const COUNTRY = 'mrw_country';
    public const PERSON = 'mrw_person';

    public function register(): void
    {
        add_action('init', [$this, 'register_country']);
        add_action('init', [$this, 'register_person']);
    }

    public function register_country(): void
    {
        register_taxonomy(self::COUNTRY, [Mrw_Issue_Post_Type::POST_TYPE], [
            'labels' => [
                'name' => __('Countries Mentioned', 'country-week'),
                'singular_name' => __('Country Mentioned', 'country-week'),
                'menu_name' => __('Countries Mentioned', 'country-week'),
            ],
            'hierarchical' => false,
            'public' => true,
            'show_in_rest' => true,
            'query_var' => false,
            'rewrite' => ['slug' => 'missionary-review/country'],
        ]);
    }

    public function register_person(): void
    {
        register_taxonomy(self::PERSON, [Mrw_Issue_Post_Type::POST_TYPE], [
            'labels' => [
                'name' => __('People Mentioned', 'country-week'),
                'singular_name' => __('Person Mentioned', 'country-week'),
                'menu_name' => __('People Mentioned', 'country-week'),
            ],
            'hierarchical' => false,
            'public' => true,
            'show_in_rest' => true,
            'query_var' => false,
            'rewrite' => ['slug' => 'missionary-review/person'],
        ]);
    }
}
