<?php
/**
 * JSON-LD structured data (schema.org) for country pages.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Seo;

use CountryWeek\CPT\Country_Meta_Fields;
use CountryWeek\CPT\Country_Post_Type;
use CountryWeek\Services\Country_Repository;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Emits a schema.org Country node (a Place subtype) for the country in
 * view, plus a BreadcrumbList on single country pages. No SEO plugin
 * dependency — this is a small, focused JSON-LD block hand-written to
 * this site's actual data model.
 */
class Schema_Generator
{
    public function register(): void
    {
        add_action('wp_footer', [$this, 'output_schema']);
    }

    public function output_schema(): void
    {
        $country = $this->resolve_country();

        if (!$country instanceof WP_Post) {
            return;
        }

        $graph = [$this->country_node($country)];

        if (is_singular(Country_Post_Type::POST_TYPE)) {
            $graph[] = $this->breadcrumb_node($country);
        }

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES)
        );
    }

    private function country_node(WP_Post $country): array
    {
        $image_id = Country_Meta_Fields::social_image_id($country->ID);

        $node = [
            '@type' => 'Country',
            '@id' => get_permalink($country) . '#country',
            'name' => get_the_title($country),
            'url' => get_permalink($country),
        ];

        $capital = get_post_meta($country->ID, 'capital', true);
        if ($capital) {
            $node['containsPlace'] = ['@type' => 'City', 'name' => $capital];
        }

        if ($image_id) {
            $node['image'] = wp_get_attachment_image_url($image_id, 'large');
        }

        if (has_excerpt($country)) {
            $node['description'] = wp_strip_all_tags(get_the_excerpt($country));
        }

        $continents = wp_get_post_terms($country->ID, 'continent', ['fields' => 'names']);
        if (!is_wp_error($continents) && !empty($continents)) {
            $node['containedInPlace'] = ['@type' => 'Continent', 'name' => $continents[0]];
        }

        return $node;
    }

    private function breadcrumb_node(WP_Post $country): array
    {
        $index = Country_Repository::index_of($country->ID);
        $archive_url = get_post_type_archive_link(Country_Post_Type::POST_TYPE);

        $items = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('Home', 'country-week'), 'item' => home_url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('Countries', 'country-week'), 'item' => $archive_url],
            ['@type' => 'ListItem', 'position' => 3, 'name' => get_the_title($country), 'item' => get_permalink($country)],
        ];

        return [
            '@type' => 'BreadcrumbList',
            '@id' => get_permalink($country) . '#breadcrumb',
            'itemListElement' => $items,
        ];
    }

    private function resolve_country(): ?WP_Post
    {
        if (is_singular(Country_Post_Type::POST_TYPE)) {
            return get_queried_object();
        }

        if (is_front_page()) {
            return Country_Repository::get_active();
        }

        return null;
    }
}
