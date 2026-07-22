<?php
/**
 * Registers the private `country_adoption` post type used to store
 * "Adopt This Country" requests for admin moderation.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Each submission from Forms\Adoption_Form is stored as one of these
 * posts (never public-facing) so a moderator can review and approve
 * volunteers before they're treated as the steward of a country's page.
 * Approving one here is a manual decision — nothing about content
 * editing rights changes automatically just because a request exists.
 */
class Country_Adoption_Post_Type
{
    public const POST_TYPE = 'country_adoption';

    public const STATUS_META_KEY = 'adoption_status';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta_fields']);
    }

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Country Adoptions', 'country-week'),
                'singular_name' => __('Country Adoption', 'country-week'),
                'all_items' => __('Country Adoptions', 'country-week'),
                'menu_name' => __('Country Adoptions', 'country-week'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-flag',
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public function register_meta_fields(): void
    {
        foreach (['submitter_name', 'submitter_email', 'country_post_id', 'message', 'bio', self::STATUS_META_KEY] as $key) {
            register_post_meta(self::POST_TYPE, $key, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
            ]);
        }
    }

    /**
     * Country post IDs with a pending OR approved adoption — i.e. not
     * available to choose again. A rejected request frees the country
     * back up. Used to filter the "Adopt a Country" dropdown.
     *
     * @return int[]
     */
    public static function taken_country_ids(): array
    {
        $adoptions = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => self::STATUS_META_KEY,
                    'value' => [self::STATUS_PENDING, self::STATUS_APPROVED],
                    'compare' => 'IN',
                ],
            ],
        ]);

        $country_ids = [];

        foreach ($adoptions as $adoption_id) {
            $country_id = (int) get_post_meta($adoption_id, 'country_post_id', true);

            if ($country_id) {
                $country_ids[$country_id] = true;
            }
        }

        return array_keys($country_ids);
    }

    /**
     * Whether $country_id already has a pending or approved adoption —
     * the server-side counterpart to taken_country_ids(), used to
     * reject a submission for a country that became unavailable
     * between page load and form submit.
     */
    public static function is_taken(int $country_id): bool
    {
        return in_array($country_id, self::taken_country_ids(), true);
    }

    /**
     * The approved adoption for a country, if any — what
     * templates/parts/adopt-cta.php displays as "Adopted by ...".
     * Approval is a deliberate moderator action (see
     * Admin\Submission_Moderation), so a merely-pending request never
     * appears here.
     */
    public static function find_approved_for_country(int $country_id): ?WP_Post
    {
        $posts = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'meta_query' => [
                ['key' => 'country_post_id', 'value' => (string) $country_id],
                ['key' => self::STATUS_META_KEY, 'value' => self::STATUS_APPROVED],
            ],
        ]);

        return $posts[0] ?? null;
    }
}
