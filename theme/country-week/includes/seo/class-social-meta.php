<?php
/**
 * Open Graph and Twitter Card meta tags.
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
 * No SEO plugin is used, so Open Graph/Twitter tags are emitted
 * directly in wp_head. Only rendered on pages where a specific country
 * is in view (single country pages and the front page's active
 * country) — generic pages get WordPress/browser defaults.
 */
class Social_Meta
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'output_tags'], 2);
    }

    public function output_tags(): void
    {
        $country = $this->resolve_country();

        if (!$country instanceof WP_Post) {
            return;
        }

        $url = get_permalink($country);
        $title = get_the_title($country);
        $description = has_excerpt($country) ? wp_strip_all_tags(get_the_excerpt($country)) : '';
        $image_id = Country_Meta_Fields::social_image_id($country->ID);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';

        $tags = [
            'og:type' => 'article',
            'og:site_name' => get_bloginfo('name'),
            'og:title' => $title,
            'og:url' => $url,
            'og:description' => $description,
            'og:image' => $image_url,
            'twitter:card' => $image_url ? 'summary_large_image' : 'summary',
            'twitter:title' => $title,
            'twitter:description' => $description,
            'twitter:image' => $image_url,
        ];

        foreach ($tags as $property => $content) {
            if ((string) $content === '') {
                continue;
            }

            $attribute = str_starts_with($property, 'twitter:') ? 'name' : 'property';

            printf(
                '<meta %1$s="%2$s" content="%3$s">' . "\n",
                esc_attr($attribute),
                esc_attr($property),
                esc_attr((string) $content)
            );
        }
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
