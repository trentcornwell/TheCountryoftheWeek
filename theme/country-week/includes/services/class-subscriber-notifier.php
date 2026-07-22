<?php
/**
 * Orchestrates the weekly upcoming-country preview send: finds who
 * should be emailed right now and sends to them.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use DateTimeImmutable;
use DateTimeZone;
use WP_Post;
use WP_User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Called hourly by Hooks\Weekly_Email_Hooks. Only ever *reads* the
 * upcoming country via Country_Repository — it never caches or decides
 * one itself (see docs/decisions/0002-per-subscriber-timezone-weekly-email.md
 * for why that distinction matters). The per-user
 * LAST_NOTIFIED_META_KEY is send-idempotency bookkeeping, not a
 * rotation-correctness value: if it were ever lost or wrong, the worst
 * outcome is one subscriber getting a duplicate or missed email, never
 * an incorrect "current country" for anyone.
 */
class Subscriber_Notifier
{
    private const LAST_NOTIFIED_META_KEY = 'country_week_last_notified_week';

    public static function run(): void
    {
        $active = Country_Repository::get_active();

        if ($active === null) {
            return;
        }

        $upcoming = Country_Repository::get_by_offset($active, 1);

        if ($upcoming === null) {
            return;
        }

        $boundary = Country_Repository::next_scheduled_date($upcoming);

        if ($boundary === null) {
            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $cycle_key = $boundary->format('Y-m-d');

        foreach (get_users(['role' => 'subscriber']) as $user) {
            self::maybe_notify($user, $upcoming, $boundary, $now, $cycle_key);
        }
    }

    private static function maybe_notify(WP_User $user, WP_Post $upcoming, DateTimeImmutable $boundary, DateTimeImmutable $now, string $cycle_key): void
    {
        if (!Subscriber_Meta_Fields::wants_email($user->ID)) {
            return;
        }

        $already_notified = get_user_meta($user->ID, self::LAST_NOTIFIED_META_KEY, true) === $cycle_key;
        $subscriber_timezone = Subscriber_Meta_Fields::timezone_for($user->ID);

        if (!Subscriber_Notification_Schedule::should_notify($now, $subscriber_timezone, $boundary, $already_notified)) {
            return;
        }

        if (self::send_to($user, $upcoming)) {
            update_user_meta($user->ID, self::LAST_NOTIFIED_META_KEY, $cycle_key);
        }
    }

    private static function send_to(WP_User $user, WP_Post $country): bool
    {
        $subject = Weekly_Preview_Email::subject($country);
        $html = Weekly_Preview_Email::html_body($country, $user->ID);
        $slide_path = wp_tempnam(Slide_Service::filename($country));

        try {
            file_put_contents($slide_path, Slide_Service::generate($country));

            add_filter('wp_mail_content_type', [self::class, 'html_content_type']);

            try {
                return wp_mail($user->user_email, $subject, $html, [], [$slide_path]);
            } finally {
                remove_filter('wp_mail_content_type', [self::class, 'html_content_type']);
            }
        } finally {
            if (file_exists($slide_path)) {
                unlink($slide_path);
            }
        }
    }

    public static function html_content_type(): string
    {
        return 'text/html';
    }
}
