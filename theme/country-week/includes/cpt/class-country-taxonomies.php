<?php
/**
 * Registers the `continent` and `region` taxonomies for the country CPT.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * `continent` is hierarchical (behaves like categories) and holds the
 * seven continents. `region` is flat (behaves like tags) and holds
 * Factbook-style sub-regions (e.g. "Southeast Asia", "Caribbean").
 * Both drive the archive's filter controls and Country_Repository's
 * "related countries" lookup.
 */
class Country_Taxonomies
{
    public const CONTINENT = 'continent';
    public const REGION = 'region';

    public function register(): void
    {
        add_action('init', [$this, 'register_continent']);
        add_action('init', [$this, 'register_region']);
    }

    public function register_continent(): void
    {
        register_taxonomy(self::CONTINENT, [Country_Post_Type::POST_TYPE], [
            'labels' => [
                'name' => __('Continents', 'country-week'),
                'singular_name' => __('Continent', 'country-week'),
                'menu_name' => __('Continents', 'country-week'),
            ],
            'hierarchical' => true,
            'public' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'continent'],
        ]);
    }

    public function register_region(): void
    {
        register_taxonomy(self::REGION, [Country_Post_Type::POST_TYPE], [
            'labels' => [
                'name' => __('Regions', 'country-week'),
                'singular_name' => __('Region', 'country-week'),
                'menu_name' => __('Regions', 'country-week'),
            ],
            'hierarchical' => false,
            'public' => true,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'region'],
        ]);
    }
}
