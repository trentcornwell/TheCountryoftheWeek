<?php
/**
 * Theme bootstrap. Deliberately thin — every real module lives under
 * includes/ and is wired together by CountryWeek\Theme::boot(). See
 * includes/class-theme.php for the full list of modules.
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/class-theme.php';

CountryWeek\Theme::boot();

/**
 * The one deliberate exception to "every helper is a namespaced static
 * method" (see includes/class-theme.php): template files reach for this
 * by name, so it's exposed globally rather than requiring every partial
 * to import CountryWeek\Utilities\Map_Asset. Always returns a usable
 * URL — the country's own bundled map, or the placeholder graphic if
 * none exists for it. See MAP-SOURCES.md.
 */
function country_week_get_map_url(?WP_Post $country): string
{
    return CountryWeek\Utilities\Map_Asset::url_for($country);
}
