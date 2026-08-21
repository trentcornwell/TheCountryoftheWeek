<?php
/**
 * Registers the `mrw_issue` custom post type.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * One `mrw_issue` post per scanned issue (or yearly index) of The
 * Missionary Review of the World, 1888-1939, sourced from cafis.org's
 * public archive (see docs/decisions/0004-missionary-review-archive-ingestion.md
 * and scripts/mrw-fetch-extract.php). The post_content editor is
 * intentionally unused, same reasoning as `country`: the PDF itself is
 * never rehosted (see `source_pdf_url` in Mrw_Meta_Fields), and the
 * excerpt plus Mrw_Taxonomies terms are all a template needs to render.
 */
class Mrw_Issue_Post_Type
{
    public const POST_TYPE = 'mrw_issue';

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
            'menu_icon' => 'dashicons-book-alt',
            'menu_position' => 6,
            'supports' => ['title', 'excerpt', 'custom-fields'],
            'has_archive' => 'missionary-review',
            'rewrite' => [
                'slug' => 'missionary-review',
                'with_front' => false,
            ],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    private function labels(): array
    {
        return [
            'name' => __('Missionary Review Issues', 'country-week'),
            'singular_name' => __('Missionary Review Issue', 'country-week'),
            'add_new_item' => __('Add New Issue', 'country-week'),
            'edit_item' => __('Edit Issue', 'country-week'),
            'new_item' => __('New Issue', 'country-week'),
            'view_item' => __('View Issue', 'country-week'),
            'search_items' => __('Search Missionary Review Archive', 'country-week'),
            'not_found' => __('No issues found', 'country-week'),
            'not_found_in_trash' => __('No issues found in Trash', 'country-week'),
            'all_items' => __('All Issues', 'country-week'),
            'archives' => __('Missionary Review Archive', 'country-week'),
            'menu_name' => __('Missionary Review', 'country-week'),
        ];
    }
}
