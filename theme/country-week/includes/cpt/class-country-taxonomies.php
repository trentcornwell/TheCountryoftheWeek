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
 * five UN M49 Regions (Africa, Americas, Asia, Europe, Oceania —
 * M49 does not split the Americas). `region` is flat (behaves like
 * tags) and holds M49 Sub-region/Intermediate Region values (e.g.
 * "South-eastern Asia", "Caribbean"). Term values come from UN M49,
 * not the CIA Factbook — see
 * docs/decisions/0003-multi-source-country-data-model.md. Both drive
 * the archive's filter controls and Country_Repository's "related
 * countries" lookup.
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
