<?php
/**
 * Builds the URL for a country's printable coloring page.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Same pattern as Pdf_Service::print_url() — no image-generation
 * library dependency here either. The page itself
 * (templates/print/country-coloring.php) renders the country's own
 * bundled outline map inline so the browser's print/"Save as PDF"
 * produces the actual output. Unlike the print sheet and slide, this
 * is deliberately not gated behind a login — see
 * Hooks\Rewrite_Hooks::maybe_require_login_for_resource().
 */
class Coloring_Page_Service
{
    public static function url(WP_Post $country): string
    {
        return trailingslashit(get_permalink($country)) . 'coloring/';
    }
}
