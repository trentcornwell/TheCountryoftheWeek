<?php
/**
 * Registers the private `prayer_partner` post type used to store
 * "Join Us in Prayer" signups for admin follow-up.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Each submission from Forms\Prayer_Partner_Form is stored as one of
 * these posts (never public-facing) so the ministry team can see who
 * has joined in praying through the world and follow up with the
 * "additional helpful resources" promised on the signup form.
 */
class Prayer_Partner_Post_Type
{
    public const POST_TYPE = 'prayer_partner';

    public function register(): void
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta_fields']);
    }

    public function register_post_type(): void
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => __('Prayer Partners', 'country-week'),
                'singular_name' => __('Prayer Partner', 'country-week'),
                'all_items' => __('Prayer Partners', 'country-week'),
                'menu_name' => __('Prayer Partners', 'country-week'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-groups',
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public function register_meta_fields(): void
    {
        foreach (['submitter_name', 'church', 'email', 'started_praying', 'resources_sent'] as $key) {
            register_post_meta(self::POST_TYPE, $key, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
            ]);
        }
    }
}
