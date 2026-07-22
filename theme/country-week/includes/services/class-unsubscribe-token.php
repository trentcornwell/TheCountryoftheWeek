<?php
/**
 * Signed, login-free unsubscribe links for the weekly preview email.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Built on WordPress's own wp_hash() (itself keyed by wp_salt('auth')),
 * so no new secret needs to be generated or stored. No expiry — an
 * unsubscribe link is expected to keep working indefinitely, and the
 * worst case of a stale/leaked token being replayed is simply "someone
 * gets unsubscribed", which doesn't warrant added complexity.
 */
class Unsubscribe_Token
{
    public static function generate(int $user_id): string
    {
        return wp_hash('country_week_unsubscribe|' . $user_id, 'auth');
    }

    public static function verify(int $user_id, string $token): bool
    {
        return hash_equals(self::generate($user_id), $token);
    }
}
