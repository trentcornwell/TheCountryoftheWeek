<?php
/**
 * Thin wrapper around the vendored QR code generator.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Keeps every other class in the theme from having to know the API
 * shape of the vendored library (includes/vendor/qr-code-generator.php,
 * Kazuhiko Arase's MIT-licensed QRCode for PHP5). Used by Pdf_Service to
 * embed a scannable link back to the canonical URL on the printable
 * country sheet.
 */
class Qr_Code_Service
{
    private static bool $vendor_loaded = false;

    private static function load_vendor(): void
    {
        if (self::$vendor_loaded) {
            return;
        }

        require_once dirname(__DIR__) . '/vendor/qr-code-generator.php';
        self::$vendor_loaded = true;
    }

    /**
     * Render a QR code encoding $data as a PNG data URI, suitable for
     * direct use in an <img src="..."> with no extra HTTP request.
     */
    public static function data_uri(string $data, int $module_size = 4, int $margin = 2): string
    {
        self::load_vendor();

        $qr = \QRCode::getMinimumQRCode($data, QR_ERROR_CORRECT_LEVEL_M);
        $image = $qr->createImage($module_size, $margin);

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}
