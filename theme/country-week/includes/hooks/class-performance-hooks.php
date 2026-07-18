<?php
/**
 * Small, native-WordPress-only performance hygiene tweaks.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Hooks;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nothing here is a "plugin" — it's the standard set of core filters
 * that remove head cruft most sites never use, plus enabling native
 * lazy-loading/async-decoding attributes on the theme's own images.
 * WordPress core already provides responsive srcset/sizes, so that is
 * intentionally left untouched.
 */
class Performance_Hooks
{
    public function register(): void
    {
        add_action('init', [$this, 'remove_head_cruft']);
        add_filter('emoji_svg_url', '__return_false');
        add_filter('wp_img_tag_add_loading_attr', [$this, 'force_lazy_loading'], 10, 2);
    }

    public function remove_head_cruft(): void
    {
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');

        add_filter('emoji_svg_url', '__return_false');
        remove_filter('wp_head', 'wp_oembed_add_discovery_links');
    }

    /**
     * The hero/featured image on the currently active country is almost
     * always the Largest Contentful Paint element, so it should never be
     * lazy-loaded. Every other image gets native lazy loading.
     */
    public function force_lazy_loading(string $value, string $context): string
    {
        if ($context === 'hero_image') {
            return 'eager';
        }

        return $value ?: 'lazy';
    }
}
