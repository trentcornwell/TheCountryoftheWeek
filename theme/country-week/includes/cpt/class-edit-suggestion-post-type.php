<?php
/**
 * Registers the private `edit_suggestion` post type used to store
 * "Suggest an Edit" submissions for admin moderation.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Each submission from Forms\Suggest_Edit_Form is stored as one of
 * these posts (never public-facing) so admins can review and approve
 * or reject corrections from the ordinary wp-admin list table rather
 * than only from email — see Admin\Submission_Moderation, which adds
 * the Status column and Approve/Reject quick links for this post type.
 */
class Edit_Suggestion_Post_Type
{
    public const POST_TYPE = 'edit_suggestion';

    public const STATUS_META_KEY = 'suggestion_status';
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
                'name' => __('Edit Suggestions', 'country-week'),
                'singular_name' => __('Edit Suggestion', 'country-week'),
                'all_items' => __('Edit Suggestions', 'country-week'),
                'menu_name' => __('Edit Suggestions', 'country-week'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => false,
            'menu_icon' => 'dashicons-edit-page',
            'supports' => ['title'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public function register_meta_fields(): void
    {
        $string_fields = [
            'submitter_name',
            'submitter_email',
            'country_post_id',
            'correction',
            'source',
            'source_url',
            self::STATUS_META_KEY,
        ];

        foreach ($string_fields as $key) {
            register_post_meta(self::POST_TYPE, $key, [
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
            ]);
        }
    }
}
