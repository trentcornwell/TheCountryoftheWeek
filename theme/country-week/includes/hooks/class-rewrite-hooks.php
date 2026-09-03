<?php
/**
 * URL structure: the /print/, /slide/, and /coloring/ endpoints on
 * every country page, the standalone top-level resource pages under
 * /resources/ (see maybe_flush_rewrite_rules() for why those don't
 * need a WordPress Page in the database to work), and a rewrite flush
 * whenever new rules are added.
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
    /**
     * Single toggle for the "free account required to download the
     * PDF/Slide" gate below. Temporarily set to false at the site
     * owner's request (2026-08-28) — flip back to true to require an
     * account again. Also consulted by templates/parts/share-buttons.php
     * (lock icon) and templates/parts/signup-popup.php (which advertises
     * exactly this gate, so it stays hidden while this is off).
     */
    public const RESOURCES_REQUIRE_ACCOUNT = false;

    /**
     * Bump this whenever a new rewrite rule is added anywhere in this
     * class (e.g. register_state_of_the_world_route()) so
     * maybe_flush_rewrite_rules() knows to flush again — see that
     * method's docblock for why this exists instead of the more usual
     * "flush once on theme activation" approach.
     */
    private const REWRITE_VERSION = 2;

    public function register(): void
    {
        add_action('init', [$this, 'register_print_endpoint']);
        add_action('init', [$this, 'register_slide_endpoint']);
        add_action('init', [$this, 'register_coloring_endpoint']);
        add_action('init', [$this, 'register_state_of_the_world_route']);
        add_action('init', [$this, 'maybe_flush_rewrite_rules'], 20);
        add_filter('query_vars', [$this, 'register_query_vars']);
        add_action('after_switch_theme', [$this, 'flush_rewrite_rules']);
        add_filter('template_include', [$this, 'maybe_load_print_template']);
        add_filter('template_include', [$this, 'maybe_load_coloring_template']);
        add_filter('template_include', [$this, 'maybe_load_state_of_the_world_template']);
        add_action('parse_query', [$this, 'unmark_resource_route_as_home']);
        add_action('template_redirect', [$this, 'maybe_require_login_for_resource'], 5);
        add_action('template_redirect', [$this, 'maybe_output_slide']);
        add_action('pre_get_posts', [$this, 'restrict_search_to_countries']);
    }

    /**
     * Downloadable resources (the printable sheet, the presentation
     * slide) require a free account — see Forms\Registration_Form.
     * Runs at priority 5, before template_include/maybe_output_slide,
     * so an anonymous visitor is redirected before either resource is
     * ever generated. The /coloring/ endpoint is deliberately NOT
     * checked here — the coloring page is free for anyone, no account
     * required, by explicit request.
     */
    public function maybe_require_login_for_resource(): void
    {
        if (!self::RESOURCES_REQUIRE_ACCOUNT) {
            return;
        }

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
     * The site's primary purpose is browsing countries, so the sitewide
     * header search box (see header.php, which submits to home_url('/'))
     * searches only the `country` post type rather than mixing in
     * Pages/etc. Only applies when nothing else has already picked a
     * post type for this request: the Missionary Review archive's own
     * search box (archive-mrw_issue.php, which submits to its own
     * archive URL) resolves `post_type=mrw_issue` from that URL before
     * this hook ever runs, and must keep searching mrw_issue, not be
     * silently overridden to country (a real bug caught by hand: typing
     * into the archive's search box was returning country search
     * results instead of missionary-review results).
     */
    public function restrict_search_to_countries(\WP_Query $query): void
    {
        if (!is_admin() && $query->is_main_query() && $query->is_search() && empty($query->get('post_type'))) {
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

    /**
     * Adds a /coloring/ endpoint to every permalink, e.g.
     * /countries/kiribati/coloring/ — a printable black-and-white
     * outline coloring page, handled like /print/ via a template swap
     * (see maybe_load_coloring_template()) rather than a direct
     * binary output.
     */
    public function register_coloring_endpoint(): void
    {
        add_rewrite_endpoint('coloring', EP_PERMALINK);
    }

    /**
     * A standalone top-level page at /resources/state-of-the-world/ —
     * deliberately not a WordPress Page (which would need creating
     * through wp-admin, i.e. a database write on whichever environment
     * needs it) but a plain rewrite rule the theme's own code deploy
     * ships everywhere automatically, same idea as the per-country
     * /print/, /slide/, /coloring/ endpoints above. See
     * templates/resources/state-of-the-world.php.
     */
    public function register_state_of_the_world_route(): void
    {
        add_rewrite_rule('^resources/state-of-the-world/?$', 'index.php?country_week_resource=state-of-the-world', 'top');
    }

    /**
     * Whitelists the query var register_state_of_the_world_route()
     * feeds through the rewrite rule — WordPress silently drops any
     * query var not explicitly registered here, even one supplied by
     * a rule's own replacement string.
     *
     * @param string[] $vars
     * @return string[]
     */
    public function register_query_vars(array $vars): array
    {
        $vars[] = 'country_week_resource';

        return $vars;
    }

    public function flush_rewrite_rules(): void
    {
        $this->register_print_endpoint();
        $this->register_slide_endpoint();
        $this->register_coloring_endpoint();
        $this->register_state_of_the_world_route();
        flush_rewrite_rules();
    }

    /**
     * A normal "flush once on theme activation" (the pattern
     * flush_rewrite_rules() above exists for) never fires for a code-
     * only deploy: DreamHost promotion just re-points a symlink at a
     * new release directory, it doesn't call switch_theme() through
     * WordPress, so after_switch_theme never runs and a brand new
     * rewrite rule like register_state_of_the_world_route()'s 404s
     * until *something* flushes the cached rewrite_rules option. This
     * self-heals on the next real request instead of requiring a
     * manual wp-admin "Save Permalinks" click or WP-CLI access after
     * every deploy that adds a rule: cheap to check (one option read)
     * on every request, and only actually flushes once per bump of
     * REWRITE_VERSION.
     */
    public function maybe_flush_rewrite_rules(): void
    {
        if ((int) get_option('country_week_rewrite_version') !== self::REWRITE_VERSION) {
            flush_rewrite_rules();
            update_option('country_week_rewrite_version', self::REWRITE_VERSION);
        }
    }

    /**
     * Swap in the resource page template (with normal header.php/
     * footer.php chrome, unlike the standalone print/coloring
     * templates) when register_state_of_the_world_route()'s query var
     * is present.
     */
    public function maybe_load_state_of_the_world_template(string $template): string
    {
        if (get_query_var('country_week_resource') === 'state-of-the-world') {
            $custom_template = get_theme_file_path('templates/resources/state-of-the-world.php');

            if (file_exists($custom_template)) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * WordPress's query parser defaults an "empty" main query — no
     * recognized content-selecting var, and country_week_resource
     * (from register_state_of_the_world_route()) isn't one WordPress
     * knows about — to is_home = true, the classic "nothing else
     * matched, this must be the blog index" fallback. That silently
     * made is_front_page() true for this route too, which fed the
     * *homepage's* title, meta description, schema.org markup, and
     * Open Graph tags (Seo\Seo_Fields, Seo\Schema_Generator,
     * Seo\Social_Meta all branch on is_front_page()) into this page
     * instead of their own generic handling — caught by hand, checking
     * this page's actual <title> tag after shipping it. Un-marks it
     * here, before any of those hooks run.
     */
    public function unmark_resource_route_as_home(\WP_Query $query): void
    {
        if ($query->is_main_query() && $query->get('country_week_resource') !== '') {
            $query->is_home = false;
        }
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
     * Swap in the standalone coloring-page template (no header.php/
     * footer.php chrome) when the /coloring/ endpoint is present on a
     * country's permalink. Same pattern as maybe_load_print_template().
     */
    public function maybe_load_coloring_template(string $template): string
    {
        $is_coloring_request = get_query_var('coloring', null) !== null;

        if ($is_coloring_request && is_singular(Country_Post_Type::POST_TYPE)) {
            $custom_template = get_theme_file_path('templates/print/country-coloring.php');

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
