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
 * There is no PDF library dependency in this theme. For most countries,
 * "Download PDF" is a link to a print-optimized template
 * (templates/print/country-print.php, served through the /print/
 * rewrite endpoint registered in Hooks\Rewrite_Hooks) styled with
 * assets/css/print.css; the visitor's own browser produces the actual
 * PDF via window.print(). This class centralizes the two small pieces
 * of data that template needs: the print URL itself, and its QR code.
 *
 * A country can instead ship a real, pre-built PDF booklet directly —
 * see booklet_url_for() — in which case "Download PDF" links straight
 * to that file and skips the generated single-page sheet entirely.
 * Currently only Lebanon has one (assets/pdf/lebanon.pdf).
 */
class Pdf_Service
{
    private const BOOKLET_DIR = 'assets/pdf/';

    public static function print_url(WP_Post $post): string
    {
        return self::booklet_url_for($post) ?? (trailingslashit(get_permalink($post)) . 'print/');
    }

    /**
     * A pre-built PDF booklet for this country, if one exists at
     * assets/pdf/{manifest_key}.pdf, or null to fall back to the
     * generated /print/ sheet.
     */
    private static function booklet_url_for(WP_Post $post): ?string
    {
        $key = get_post_meta($post->ID, Country_Manifest::meta_key(), true);

        if (!is_string($key) || preg_match('/^[a-z0-9-]+$/', $key) !== 1) {
            return null;
        }

        $relative = self::BOOKLET_DIR . $key . '.pdf';

        return file_exists(get_theme_file_path($relative)) ? get_theme_file_uri($relative) : null;
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
