<?php
/**
 * Schedules the weekly upcoming-country preview send and handles
 * login-free unsubscribe links.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Hooks;

use CountryWeek\Services\Subscriber_Meta_Fields;
use CountryWeek\Services\Subscriber_Notifier;
use CountryWeek\Services\Unsubscribe_Token;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The theme's first cron job. WP-Cron's built-in `hourly` schedule is
 * traffic-driven and imprecise (see docs/SCHEDULER.md) — that's fine
 * here because Subscriber_Notification_Schedule's window is wide
 * enough to self-heal a missed/drifted run rather than needing a
 * precise trigger. This job only ever reads the upcoming country via
 * Country_Repository (through Subscriber_Notifier); it never caches or
 * decides one, so a missed run cannot produce an incorrect answer,
 * only a late email.
 */
class Weekly_Email_Hooks
{
    public const CRON_HOOK = 'country_week_send_weekly_emails';
    private const UNSUBSCRIBE_ACTION = 'country_week_unsubscribe';

    public function register(): void
    {
        add_action('init', [$this, 'ensure_scheduled']);
        add_action(self::CRON_HOOK, [Subscriber_Notifier::class, 'run']);
        add_action('switch_theme', [$this, 'clear_scheduled']);

        add_action('admin_post_' . self::UNSUBSCRIBE_ACTION, [$this, 'handle_unsubscribe']);
        add_action('admin_post_nopriv_' . self::UNSUBSCRIBE_ACTION, [$this, 'handle_unsubscribe']);
    }

    public function ensure_scheduled(): void
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Mirrors Rewrite_Hooks::flush_rewrite_rules()'s use of the same
     * theme-switch lifecycle hook for cleanup hygiene.
     */
    public function clear_scheduled(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Registered against both the logged-in and nopriv admin_post
     * action variants (WordPress routes the two differently for the
     * same request) — unsubscribing must work whether or not the
     * visitor happens to have an active session on the device they
     * opened the email link on.
     */
    public function handle_unsubscribe(): void
    {
        $user_id = isset($_GET['u']) ? absint($_GET['u']) : 0;
        $token = isset($_GET['t']) ? sanitize_text_field(wp_unslash($_GET['t'])) : '';

        if ($user_id > 0 && $token !== '' && Unsubscribe_Token::verify($user_id, $token)) {
            Subscriber_Meta_Fields::set_opt_in($user_id, false);
            wp_safe_redirect(add_query_arg('email_pref', 'unsubscribed', home_url('/')));
            exit;
        }

        wp_safe_redirect(add_query_arg('email_pref', 'invalid', home_url('/')));
        exit;
    }
}
