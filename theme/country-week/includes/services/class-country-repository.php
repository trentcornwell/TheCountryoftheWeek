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
     */
    public static function get_active(): ?WP_Post
    {
        if (!Rotation_Service::has_started()) {
            return null;
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
        $position = self::cycle_position_of($post->ID);

        return $position === null ? null : Rotation_Service::date_for_index($position, self::count());
    }

    /**
     * The most recent date (at or before today) this country was
     * featured.
     */
    public static function most_recent_date(WP_Post $post): ?\DateTimeImmutable
    {
        $position = self::cycle_position_of($post->ID);

        return $position === null ? null : Rotation_Service::most_recent_date_for_index($position, self::count());
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
    }
}
