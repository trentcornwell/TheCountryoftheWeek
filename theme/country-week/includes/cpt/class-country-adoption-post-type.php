<?php
/**
 * Registers the private `country_adoption` post type used to store
 * "Adopt This Country" requests for admin moderation.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

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
        foreach (['submitter_name', 'submitter_email', 'country_post_id', 'message', self::STATUS_META_KEY] as $key) {
            register_post_meta(self::POST_TYPE, $key, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
            ]);
        }
    }
}
