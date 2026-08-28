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
     * The print-only stylesheet's URL. Called directly from
     * templates/print/country-print.php and
     * templates/print/country-coloring.php, which render their own
     * minimal <head> and don't run the normal wp_head asset queue —
     * so unlike enqueue_site_assets() above, there's no wp_enqueue_style()
     * call here to attach WordPress's automatic cache-busting `?ver=`
     * query string. filemtime() stands in for that: it changes on every
     * deploy that touches this file, which is what actually matters
     * (the theme's style.css Version header isn't bumped on every
     * release) and forces browsers holding a long-cached copy — this
     * file is served with a 30-day max-age — to fetch the new one
     * instead of silently rendering new template markup against old
     * CSS. Falls back to the theme version if the file can't be stat'd.
     */
    public static function print_stylesheet_url(): string
    {
        $relative_path = 'assets/css/print.css';
        $mtime = filemtime(get_theme_file_path($relative_path));
        $version = $mtime !== false ? (string) $mtime : wp_get_theme()->get('Version');

        return add_query_arg('ver', $version, get_theme_file_uri($relative_path));
    }
}
