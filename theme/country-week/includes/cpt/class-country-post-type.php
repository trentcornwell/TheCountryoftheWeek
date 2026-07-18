<?php
/**
 * Registers the `country` custom post type.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Every country in the CIA World Factbook is stored as one `country`
 * post. The post title is the official Factbook country name and is
 * the sole source of alphabetical ordering the rotation schedule relies
 * on (see Services\Rotation_Service and Services\Country_Repository).
 *
 * The post_content editor is intentionally unused in favor of the
 * structured meta fields registered in Country_Meta_Fields — every
 * displayed fact lives in a named field so templates never have to
 * parse free-form content.
 */
class Country_Post_Type
{
    public const POST_TYPE = 'country';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
    }

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => $this->labels(),
            'public' => true,
            'hierarchical' => false,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-location-alt',
            'menu_position' => 5,
            'supports' => ['title', 'thumbnail', 'excerpt', 'custom-fields'],
            'has_archive' => 'countries',
            'rewrite' => [
                'slug' => 'countries',
                'with_front' => false,
            ],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    private function labels(): array
    {
        return [
            'name' => __('Countries', 'country-week'),
            'singular_name' => __('Country', 'country-week'),
            'add_new_item' => __('Add New Country', 'country-week'),
            'edit_item' => __('Edit Country', 'country-week'),
            'new_item' => __('New Country', 'country-week'),
            'view_item' => __('View Country', 'country-week'),
            'search_items' => __('Search Countries', 'country-week'),
            'not_found' => __('No countries found', 'country-week'),
            'not_found_in_trash' => __('No countries found in Trash', 'country-week'),
            'all_items' => __('All Countries', 'country-week'),
            'archives' => __('Country Archive', 'country-week'),
            'menu_name' => __('Countries', 'country-week'),
        ];
    }
}
