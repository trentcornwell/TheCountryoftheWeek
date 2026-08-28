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

    /**
     * The theme's own style.css `Version:` header isn't bumped on
     * every release (deploys are tagged in git, not reflected there),
     * so using it as every enqueued asset's cache-busting `ver=` query
     * arg means a visitor who cached an asset under an old,
     * unchanged-looking version string keeps serving it indefinitely —
     * see the near-identical fix in print_stylesheet_url() below,
     * which this mirrors for the normally-enqueued assets.
     */
    public function enqueue_site_assets(): void
    {
        wp_enqueue_style('country-week-style', get_stylesheet_uri(), [], self::asset_version('style.css'));

        wp_enqueue_script(
            'country-week-main',
            get_theme_file_uri('assets/js/main.js'),
            [],
            self::asset_version('assets/js/main.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );

        if (is_post_type_archive('country') || is_tax(['continent', 'region'])) {
            wp_enqueue_script(
                'country-week-filter',
                get_theme_file_uri('assets/js/country-filter.js'),
                [],
                self::asset_version('assets/js/country-filter.js'),
                ['strategy' => 'defer', 'in_footer' => true]
            );
        }
    }

    /**
     * A cache-busting version string for a theme-relative asset path:
     * the file's own filemtime() where possible (changes on every
     * deploy that touches it), falling back to the theme version if
     * the file can't be stat'd.
     */
    private static function asset_version(string $relative_path): string
    {
        $mtime = filemtime(get_theme_file_path($relative_path));

        return $mtime !== false ? (string) $mtime : wp_get_theme()->get('Version');
    }

    /**
     * The print-only stylesheet's URL. Called directly from
     * templates/print/country-print.php and
     * templates/print/country-coloring.php, which render their own
     * minimal <head> and don't run the normal wp_head asset queue —
     * so unlike enqueue_site_assets() above, there's no wp_enqueue_style()
     * call here to attach a cache-busting `?ver=` query string
     * automatically; asset_version() stands in for that. This file is
     * served with a 30-day max-age, so without it browsers holding a
     * long-cached copy would silently render new template markup
     * against old CSS.
     */
    public static function print_stylesheet_url(): string
    {
        $relative_path = 'assets/css/print.css';

        return add_query_arg('ver', self::asset_version($relative_path), get_theme_file_uri($relative_path));
    }
}
