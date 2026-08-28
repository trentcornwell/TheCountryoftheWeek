<?php
/**
 * Query helpers that connect WordPress's `country` posts to the pure
 * date math in Rotation_Service.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Services;

use CountryWeek\CPT\Country_Post_Type;
use CountryWeek\CPT\Country_Taxonomies;
use WP_Post;
use WP_Query;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Rotation_Service only knows about dates and list positions; this
 * class supplies the list itself and translates between WP_Post
 * objects and rotation indexes. Results are cached for the duration of
 * the request since the ordered list is read on nearly every template.
 *
 * Order comes from Country_Manifest, NOT from live-sorting post
 * titles — see docs/decisions/0001-deterministic-weekly-schedule.md.
 * Editing a country's title in wp-admin only changes its display name;
 * its position in the rotation is pinned by its `manifest_key` meta,
 * set once at import time and never re-derived from the title.
 */
class Country_Repository
{
    /** @var WP_Post[]|null */
    private static ?array $ordered_cache = null;

    private static ?int $launch_offset_cache = null;

    private static bool $active_computed = false;

    private static ?WP_Post $active_cache = null;

    /**
     * Every published country with a manifest_key, ordered to match
     * Country_Manifest::entries() (the frozen, versioned order) —
     * NOT ordered by live post_title. This is the exact list
     * Rotation_Service::active_index() indexes into.
     *
     * @return WP_Post[]
     */
    public static function get_all_ordered(): array
    {
        if (self::$ordered_cache !== null) {
            return self::$ordered_cache;
        }

        $query = new WP_Query([
            'post_type' => Country_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'meta_key' => Country_Manifest::meta_key(),
        ]);

        $posts_by_manifest_key = [];

        foreach ($query->posts as $post) {
            $key = get_post_meta($post->ID, Country_Manifest::meta_key(), true);

            if (is_string($key) && $key !== '') {
                $posts_by_manifest_key[$key] = $post;
            }
        }

        $ordered = [];

        foreach (Country_Manifest::entries() as $entry) {
            if (isset($posts_by_manifest_key[$entry['key']])) {
                $ordered[] = $posts_by_manifest_key[$entry['key']];
            }
        }

        self::$ordered_cache = $ordered;

        return self::$ordered_cache;
    }

    public static function count(): int
    {
        return count(self::get_all_ordered());
    }

    /**
     * The currently-active country, or null if the rotation has not
     * started yet or no countries are published.
     *
     * Checks Services\Schedule_Override first: a bounded, explicit,
     * git-tracked exception list (see docs/decisions/0006) that can pin
     * a specific week to a specific country regardless of what the
     * alphabetical schedule would otherwise select, without touching
     * Rotation_Service's pure math or the manifest itself. Cached for
     * the request since this is called from templates in a loop (see
     * templates/parts/country-card.php) and, when an override is
     * active, resolving it costs a query.
     */
    public static function get_active(): ?WP_Post
    {
        if (self::$active_computed) {
            return self::$active_cache;
        }

        self::$active_computed = true;
        self::$active_cache = self::resolve_active();

        return self::$active_cache;
    }

    private static function resolve_active(): ?WP_Post
    {
        if (!Rotation_Service::has_started()) {
            return null;
        }

        $week_start = Rotation_Service::current_week_start();
        $override_key = Schedule_Override::key_for_week($week_start);

        if ($override_key !== null) {
            $overridden = self::find_by_key($override_key);

            if ($overridden instanceof WP_Post) {
                return $overridden;
            }

            // The override points at a country/key whose post isn't
            // published yet (e.g. content not authored yet for a
            // one-off feature week). Never invent a substitute — but
            // don't take the whole homepage down either; fall back to
            // the natural schedule and make the gap loggable.
            error_log(sprintf(
                '[country-week] schedule override for week of %s ("%s") has no published Country post; falling back to the natural rotation.',
                $week_start->format('Y-m-d'),
                $override_key
            ));
        }

        $countries = self::get_all_ordered();
        $count = count($countries);

        if ($count === 0) {
            return null;
        }

        $cycle_position = Rotation_Service::active_index($count);
        $index = (self::launch_offset() + $cycle_position) % $count;

        return $countries[$index] ?? null;
    }

    /**
     * A published Country post by its manifest_key meta, independent of
     * manifest membership — resolves both ordinary rotation countries
     * and one-off, non-manifest posts like Martinique/Mayotte (see
     * data/one-off-features.json) the same way. Not cached beyond
     * get_active()'s own cache, since it's currently only used to
     * resolve schedule overrides (at most one lookup per request).
     */
    public static function find_by_key(string $key): ?WP_Post
    {
        $query = new WP_Query([
            'post_type' => Country_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'meta_key' => Country_Manifest::meta_key(),
            'meta_value' => $key,
        ]);

        return $query->posts[0] ?? null;
    }

    /**
     * The position of Country_Manifest's anchor country (Kiribati) in
     * get_all_ordered() — the point that converts an abstract rotation
     * position (0 = launch country) into a real index. Resolved by
     * manifest_key, not by title, so it stays correct even if the
     * launch country's post is later renamed. Falls back to 0 if the
     * launch country isn't published yet, so the site degrades
     * gracefully rather than fatally erroring.
     */
    public static function launch_offset(): int
    {
        if (self::$launch_offset_cache !== null) {
            return self::$launch_offset_cache;
        }

        $anchor_key = Country_Manifest::anchor_key();

        foreach (self::get_all_ordered() as $index => $country) {
            if (get_post_meta($country->ID, Country_Manifest::meta_key(), true) === $anchor_key) {
                self::$launch_offset_cache = $index;

                return $index;
            }
        }

        self::$launch_offset_cache = 0;

        return 0;
    }

    /**
     * This country's position in the rotation cycle (0 = the launch
     * country, increasing alphabetically with wraparound) — distinct
     * from its plain alphabetical index, which is what index_of() and
     * get_all_ordered() use for browsing.
     */
    public static function cycle_position_of(int $post_id): ?int
    {
        $alphabetical_index = self::index_of($post_id);

        if ($alphabetical_index === null) {
            return null;
        }

        $count = self::count();

        return ($alphabetical_index - self::launch_offset() + $count) % $count;
    }

    /**
     * The 1-based overall rotation week number a country was (or will
     * next be) featured on, based on its cycle position.
     */
    public static function week_number_for(WP_Post $post): ?int
    {
        $position = self::cycle_position_of($post->ID);

        if ($position === null) {
            return null;
        }

        $count = self::count();
        $current_position = Rotation_Service::active_index($count);
        $current_week = Rotation_Service::week_number();

        return $current_week + (($position - $current_position + $count) % $count);
    }

    /**
     * The next upcoming (or currently active, if today) date this
     * country is featured.
     */
    public static function next_scheduled_date(WP_Post $post): ?\DateTimeImmutable
    {
        return self::schedule_date_for($post, false);
    }

    /**
     * The most recent date (at or before today) this country was
     * featured.
     */
    public static function most_recent_date(WP_Post $post): ?\DateTimeImmutable
    {
        return self::schedule_date_for($post, true);
    }

    /**
     * Override-aware version of the plain position math above. Three
     * cases, in order:
     *
     * 1. This country itself has a schedule override (see
     *    Services\Schedule_Override) — that date is authoritative for
     *    this cycle, whether it lands earlier or later than the
     *    country's natural alphabetical slot.
     * 2. This country has no override, but its natural slot this cycle
     *    was handed to a different country — its own turn is pushed
     *    back exactly one full rotation length (it is never dropped,
     *    just delayed to its next natural occurrence).
     * 3. No override touches this country at all — unchanged natural
     *    math, exactly as before Schedule_Override existed.
     */
    private static function schedule_date_for(WP_Post $post, bool $most_recent): ?\DateTimeImmutable
    {
        $key = get_post_meta($post->ID, Country_Manifest::meta_key(), true);
        $own_override = is_string($key) && $key !== '' ? Schedule_Override::week_for_key($key) : null;

        if ($own_override !== null) {
            return $own_override;
        }

        $position = self::cycle_position_of($post->ID);

        if ($position === null) {
            return null;
        }

        $count = self::count();
        $natural = $most_recent
            ? Rotation_Service::most_recent_date_for_index($position, $count)
            : Rotation_Service::date_for_index($position, $count);

        if (Schedule_Override::key_for_week($natural) !== null) {
            $shift = new \DateInterval('P' . ($count * 7) . 'D');

            return $most_recent ? $natural->sub($shift) : $natural->add($shift);
        }

        return $natural;
    }

    /**
     * The alphabetical rotation index of a given country post, or null
     * if it isn't in the published list.
     */
    public static function index_of(int $post_id): ?int
    {
        foreach (self::get_all_ordered() as $index => $country) {
            if ($country->ID === $post_id) {
                return $index;
            }
        }

        return null;
    }

    /**
     * The country $offset positions away from $post in the alphabetical
     * list, wrapping around forever (offset -1 = previous, 1 = next).
     */
    public static function get_by_offset(WP_Post $post, int $offset): ?WP_Post
    {
        $countries = self::get_all_ordered();
        $count = count($countries);

        if ($count === 0) {
            return null;
        }

        $index = self::index_of($post->ID);

        if ($index === null) {
            return null;
        }

        $target = (($index + $offset) % $count + $count) % $count;

        return $countries[$target];
    }

    /**
     * Countries sharing a continent or region with $post, most relevant
     * first, excluding $post itself.
     *
     * @return WP_Post[]
     */
    public static function get_related(WP_Post $post, int $limit = 4): array
    {
        $term_ids = wp_get_post_terms(
            $post->ID,
            [Country_Taxonomies::CONTINENT, Country_Taxonomies::REGION],
            ['fields' => 'ids']
        );

        if (empty($term_ids) || is_wp_error($term_ids)) {
            return [];
        }

        $query = new WP_Query([
            'post_type' => Country_Post_Type::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $limit,
            'post__not_in' => [$post->ID],
            'orderby' => 'title',
            'order' => 'ASC',
            'no_found_rows' => true,
            'ignore_sticky_posts' => true,
            'tax_query' => [
                [
                    'taxonomy' => Country_Taxonomies::CONTINENT,
                    'field' => 'term_id',
                    'terms' => $term_ids,
                    'operator' => 'IN',
                ],
            ],
        ]);

        return $query->posts;
    }

    /**
     * Reset the request-scoped cache. Only needed by tools like the
     * import script that create many posts in a single process.
     */
    public static function flush_cache(): void
    {
        self::$ordered_cache = null;
        self::$launch_offset_cache = null;
        self::$active_computed = false;
        self::$active_cache = null;
    }
}
