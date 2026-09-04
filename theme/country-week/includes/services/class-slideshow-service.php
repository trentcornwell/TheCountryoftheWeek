<?php
/**
 * Per-country photo slideshows — an optional extra, not part of every
 * country's standard content. A country gets the "Slideshow" button
 * and viewer automatically the moment it has at least one image at
 * assets/slideshow/{manifest_key}/, same "does the file exist" pattern
 * Services\Pdf_Service uses for one-off PDF booklets. Currently just
 * India (assets/slideshow/india/).
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

class Slideshow_Service
{
    private const SLIDESHOW_DIR = 'assets/slideshow/';

    public static function has_slideshow(WP_Post $country): bool
    {
        return count(self::slide_paths($country)) > 0;
    }

    /**
     * Ordered list of slide image URLs, sorted by filename (so slides
     * are numbered 01.jpg, 02.jpg, etc. to control order).
     *
     * @return string[]
     */
    public static function slide_urls(WP_Post $country): array
    {
        $key = self::manifest_key_for($country);

        if ($key === '') {
            return [];
        }

        $relative_dir = self::SLIDESHOW_DIR . $key . '/';

        return array_map(
            static fn (string $path): string => get_theme_file_uri($relative_dir . basename($path)),
            self::slide_paths($country)
        );
    }

    public static function viewer_url(WP_Post $country): string
    {
        return trailingslashit(get_permalink($country)) . 'slideshow/';
    }

    /**
     * @return string[] Absolute filesystem paths, sorted by filename.
     */
    private static function slide_paths(WP_Post $country): array
    {
        $key = self::manifest_key_for($country);

        if ($key === '') {
            return [];
        }

        $dir = get_theme_file_path(self::SLIDESHOW_DIR . $key);

        if (!is_dir($dir)) {
            return [];
        }

        $paths = glob($dir . '/*.{jpg,jpeg,png,webp}', GLOB_BRACE);

        if ($paths === false) {
            return [];
        }

        sort($paths, SORT_NATURAL);

        return $paths;
    }

    private static function manifest_key_for(WP_Post $country): string
    {
        $key = get_post_meta($country->ID, Country_Manifest::meta_key(), true);

        if (!is_string($key) || preg_match('/^[a-z0-9-]+$/', $key) !== 1) {
            return '';
        }

        return $key;
    }
}
