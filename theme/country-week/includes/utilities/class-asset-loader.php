<?php
/**
 * Enqueues the theme's CSS/JS, conditionally where it makes sense.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Utilities;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deliberately minimal: one stylesheet on every page (style.css itself,
 * which WordPress already requires to exist), one small script for
 * site-wide interactivity (nav, suggest-edit dialog, print button), and
 * the archive's filter script loaded only on the archive template where
 * it is used. Print styling is loaded only by templates/print/, never
 * on normal page views, so it never costs regular visitors anything.
 */
class Asset_Loader
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_site_assets']);
    }

    public function enqueue_site_assets(): void
    {
        $theme_version = wp_get_theme()->get('Version');

        wp_enqueue_style('country-week-style', get_stylesheet_uri(), [], $theme_version);

        wp_enqueue_script(
            'country-week-main',
            get_theme_file_uri('assets/js/main.js'),
            [],
            $theme_version,
            ['strategy' => 'defer', 'in_footer' => true]
        );

        if (is_post_type_archive('country') || is_tax(['continent', 'region'])) {
            wp_enqueue_script(
                'country-week-filter',
                get_theme_file_uri('assets/js/country-filter.js'),
                [],
                $theme_version,
                ['strategy' => 'defer', 'in_footer' => true]
            );
        }
    }

    /**
     * Enqueue the print-only stylesheet. Called directly from
     * templates/print/country-print.php, which renders its own
     * minimal <head> and does not run the normal wp_head asset queue.
     */
    public static function print_stylesheet_url(): string
    {
        return get_theme_file_uri('assets/css/print.css');
    }
}
