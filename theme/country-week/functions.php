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
