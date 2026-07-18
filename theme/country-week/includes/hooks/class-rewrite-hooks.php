<?php
/**
 * URL structure: the /print/ and /slide/ endpoints on every country
 * page, and a one-time rewrite flush when the theme is activated.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Hooks;

use CountryWeek\CPT\Country_Post_Type;
use CountryWeek\Services\Slide_Service;

if (!defined('ABSPATH')) {
    exit;
}

class Rewrite_Hooks
{
    public function register(): void
    {
        add_action('init', [$this, 'register_print_endpoint']);
        add_action('init', [$this, 'register_slide_endpoint']);
        add_action('after_switch_theme', [$this, 'flush_rewrite_rules']);
        add_filter('template_include', [$this, 'maybe_load_print_template']);
        add_action('template_redirect', [$this, 'maybe_require_login_for_resource'], 5);
        add_action('template_redirect', [$this, 'maybe_output_slide']);
        add_action('pre_get_posts', [$this, 'restrict_search_to_countries']);
    }

    /**
     * Downloadable resources (the printable sheet, the presentation
     * slide) require a free account — see Forms\Registration_Form.
     * Runs at priority 5, before template_include/maybe_output_slide,
     * so an anonymous visitor is redirected before either resource is
     * ever generated.
     */
    public function maybe_require_login_for_resource(): void
    {
        $is_print_request = get_query_var('print', null) !== null;
        $is_slide_request = get_query_var('slide', null) !== null;

        if ((!$is_print_request && !$is_slide_request) || !is_singular(Country_Post_Type::POST_TYPE)) {
            return;
        }

        if (is_user_logged_in()) {
            return;
        }

        $current_url = home_url(add_query_arg(null, null));
        $register_url = add_query_arg('redirect_to', $current_url, home_url('/register/'));

        wp_safe_redirect($register_url);
        exit;
    }

    /**
     * The site's entire purpose is browsing countries, so the front-end
     * search box (see searchform.php) searches only the `country` post
     * type rather than mixing in Pages/etc.
     */
    public function restrict_search_to_countries(\WP_Query $query): void
    {
        if (!is_admin() && $query->is_main_query() && $query->is_search()) {
            $query->set('post_type', Country_Post_Type::POST_TYPE);
        }
    }

    /**
     * Adds a /print/ endpoint to every permalink, e.g.
     * /countries/kiribati/print/. Only acted on for singular `country`
     * pages (see maybe_load_print_template()).
     */
    public function register_print_endpoint(): void
    {
        add_rewrite_endpoint('print', EP_PERMALINK);
    }

    /**
     * Adds a /slide/ endpoint to every permalink, e.g.
     * /countries/kiribati/slide/ — a direct PNG download, handled
     * entirely in maybe_output_slide() rather than a template.
     */
    public function register_slide_endpoint(): void
    {
        add_rewrite_endpoint('slide', EP_PERMALINK);
    }

    public function flush_rewrite_rules(): void
    {
        $this->register_print_endpoint();
        $this->register_slide_endpoint();
        flush_rewrite_rules();
    }

    /**
     * Swap in the standalone print template (no header/footer chrome)
     * when the /print/ endpoint is present on a country's permalink.
     */
    public function maybe_load_print_template(string $template): string
    {
        $is_print_request = get_query_var('print', null) !== null;

        if ($is_print_request && is_singular(Country_Post_Type::POST_TYPE)) {
            $custom_template = get_theme_file_path('templates/print/country-print.php');

            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Serves the 16:9 presentation slide as a direct PNG download when
     * the /slide/ endpoint is present on a country's permalink. Ends
     * the request itself (binary output, not a template), so this runs
     * on template_redirect — before WordPress would otherwise start
     * loading a template — rather than template_include.
     */
    public function maybe_output_slide(): void
    {
        $is_slide_request = get_query_var('slide', null) !== null;

        if (!$is_slide_request || !is_singular(Country_Post_Type::POST_TYPE)) {
            return;
        }

        $country = get_queried_object();

        if (!$country instanceof \WP_Post) {
            return;
        }

        $png = Slide_Service::generate($country);

        nocache_headers();
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . Slide_Service::filename($country) . '"');
        header('Content-Length: ' . strlen($png));

        echo $png;
        exit;
    }
}
