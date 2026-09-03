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
 * directly in wp_head — on every front-end page, not just country
 * pages: a country page uses that country's own photo/flag/map as
 * og:image, and everything else (the homepage before rotation starts,
 * the resources pages, Schedule, About, search results, etc.) falls
 * back to assets/img/social-default.jpg so a shared link always has a
 * real preview image instead of none at all.
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

        if ($country instanceof WP_Post) {
            $url = get_permalink($country);
            $title = get_the_title($country);
            $description = has_excerpt($country) ? wp_strip_all_tags(get_the_excerpt($country)) : '';
            $image_id = Country_Meta_Fields::social_image_id($country->ID);
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
            $type = 'article';
        } else {
            $url = home_url(add_query_arg(null, null));
            $title = wp_get_document_title();
            $description = Seo_Fields::build_description();

            if ($description === '') {
                $description = get_bloginfo('description');
            }

            $image_url = '';
            $type = 'website';
        }

        if ($image_url === '') {
            $image_url = self::default_image_url();
        }

        $tags = [
            'og:type' => $type,
            'og:site_name' => get_bloginfo('name'),
            'og:title' => $title,
            'og:url' => $url,
            'og:description' => $description,
            'og:image' => $image_url,
            'twitter:card' => 'summary_large_image',
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

    private static function default_image_url(): string
    {
        return get_theme_file_uri('assets/img/social-default.jpg');
    }
}
