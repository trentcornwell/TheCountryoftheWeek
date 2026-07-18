<?php
/**
 * Builds everything the printable/PDF country sheet needs.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * There is no PDF library dependency in this theme. "Download PDF" is
 * a link to a print-optimized template (templates/print/country-print.php,
 * served through the /print/ rewrite endpoint registered in
 * Hooks\Rewrite_Hooks) styled with assets/css/print.css; the visitor's
 * own browser produces the actual PDF via window.print(). This class
 * just centralizes the two small pieces of data that template needs:
 * the print URL itself, and its QR code.
 */
class Pdf_Service
{
    public static function print_url(WP_Post $post): string
    {
        return trailingslashit(get_permalink($post)) . 'print/';
    }

    /**
     * A QR code (as a base64 PNG data URI) linking back to the
     * country's canonical (non-print) URL, for the printed page.
     */
    public static function qr_code_data_uri(WP_Post $post): string
    {
        return Qr_Code_Service::data_uri(get_permalink($post));
    }
}
