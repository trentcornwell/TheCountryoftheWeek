<?php
/**
 * Per-subscriber email preferences: time zone and weekly-preview opt-in.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use CountryWeek\Utilities\Date_Utility;
use DateTimeZone;
use Exception;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The theme's first use of WordPress user meta — everything else in
 * this theme is post meta on `country` posts. Also the first per-user
 * time zone anywhere in the codebase; every other clock here is the
 * single fixed Date_Utility::SITE_TIMEZONE. Unlike Country_Meta_Fields
 * (editorial content, intentionally exposed via REST), these are
 * private preferences, so both fields are `show_in_rest => false`.
 */
class Subscriber_Meta_Fields
{
    public const TIMEZONE_META_KEY = 'country_week_timezone';
    public const OPT_IN_META_KEY = 'country_week_email_optin';

    public function register(): void
    {
        add_action('init', [$this, 'register_meta_fields']);
    }

    public function register_meta_fields(): void
    {
        register_meta('user', self::TIMEZONE_META_KEY, [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => false,
            'default' => Date_Utility::SITE_TIMEZONE,
            'sanitize_callback' => [self::class, 'sanitize_timezone'],
            'auth_callback' => fn ($allowed, $key, $user_id) => $user_id === get_current_user_id(),
        ]);

        register_meta('user', self::OPT_IN_META_KEY, [
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => false,
            'default' => true,
            'auth_callback' => fn ($allowed, $key, $user_id) => $user_id === get_current_user_id(),
        ]);
    }

    /**
     * Only ever persists a real IANA identifier — an invalid value is
     * discarded (empty string) rather than stored, since this feeds
     * directly into `new DateTimeZone()` elsewhere.
     */
    public static function sanitize_timezone(string $value): string
    {
        return in_array($value, DateTimeZone::listIdentifiers(DateTimeZone::ALL), true) ? $value : '';
    }

    public static function timezone_for(int $user_id): DateTimeZone
    {
        $stored = get_user_meta($user_id, self::TIMEZONE_META_KEY, true);
        $identifier = is_string($stored) && $stored !== '' ? $stored : Date_Utility::SITE_TIMEZONE;

        try {
            return new DateTimeZone($identifier);
        } catch (Exception $e) {
            return Date_Utility::timezone();
        }
    }

    public static function set_timezone(int $user_id, string $identifier): void
    {
        $sanitized = self::sanitize_timezone($identifier);

        if ($sanitized !== '') {
            update_user_meta($user_id, self::TIMEZONE_META_KEY, $sanitized);
        }
    }

    public static function wants_email(int $user_id): bool
    {
        return (bool) get_user_meta($user_id, self::OPT_IN_META_KEY, true);
    }

    public static function set_opt_in(int $user_id, bool $opt_in): void
    {
        update_user_meta($user_id, self::OPT_IN_META_KEY, $opt_in);
    }
}
