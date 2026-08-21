<?php
/**
 * Defines and registers the structured data an `mrw_issue` post holds.
 *
 * @package CountryWeek
 */

namespace CountryWeek\CPT;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Small, flat field registry (unlike Country_Meta_Fields' grouped
 * quick-facts model, this CPT only needs identity + provenance fields —
 * everything else displayed is either the title/excerpt or a taxonomy
 * term). No ACF or other forms plugin, same as the rest of the theme.
 */
class Mrw_Meta_Fields
{
    public function register(): void
    {
        add_action('init', [$this, 'register_meta_fields']);
    }

    public function register_meta_fields(): void
    {
        // The stable identifier scripts/import-missionary-review.php
        // upserts by (e.g. "mrw-1888-01"), never the post title — same
        // rationale as `manifest_key` on `country` posts (see
        // docs/decisions/0001-deterministic-weekly-schedule.md).
        register_post_meta(Mrw_Issue_Post_Type::POST_TYPE, 'mrw_issue_key', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_key',
            'auth_callback' => fn () => current_user_can('edit_posts'),
        ]);

        // The original scan lives at cafis.org and is never rehosted on
        // this site (storage/licensing reasons — see the ADR); this is
        // the "read the original issue" link every card/single template
        // renders.
        register_post_meta(Mrw_Issue_Post_Type::POST_TYPE, 'source_pdf_url', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'esc_url_raw',
            'auth_callback' => fn () => current_user_can('edit_posts'),
        ]);

        register_post_meta(Mrw_Issue_Post_Type::POST_TYPE, 'volume_label', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_text_field',
            'auth_callback' => fn () => current_user_can('edit_posts'),
        ]);

        // 'issue' (a monthly number) or 'index' (that year's subject/
        // author index) — see Mrw_Issue_Post_Type's docblock.
        register_post_meta(Mrw_Issue_Post_Type::POST_TYPE, 'issue_kind', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'sanitize_key',
            'auth_callback' => fn () => current_user_can('edit_posts'),
        ]);
    }
}
