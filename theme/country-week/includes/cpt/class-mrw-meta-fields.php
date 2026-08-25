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
 * quick-facts model, this CPT only needs identity + provenance fields
 * plus `article_headings`, a lightweight index into `post_content`).
 * No ACF or other forms plugin, same as the rest of the theme.
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

        // A JSON array of {id, title} pairs, one per automatically
        // detected article within the issue (see
        // scripts/mrw-fetch-extract.php's split_into_articles() and
        // docs/decisions/0005-mrw-full-text-articles.md). Lets the
        // single-issue template render a table of contents without
        // re-parsing post_content's heading markup on every request.
        // The full article text itself lives in post_content, each
        // article as an <h2 id="{id}"> the table of contents links to.
        register_post_meta(Mrw_Issue_Post_Type::POST_TYPE, 'article_headings', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => [$this, 'sanitize_article_headings'],
            'auth_callback' => fn () => current_user_can('edit_posts'),
        ]);
    }

    public function sanitize_article_headings(string $value): string
    {
        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return '[]';
        }

        $clean = [];

        foreach ($decoded as $item) {
            if (!is_array($item) || empty($item['id']) || empty($item['title'])) {
                continue;
            }

            $clean[] = ['id' => sanitize_key($item['id']), 'title' => sanitize_text_field($item['title'])];
        }

        // JSON_UNESCAPED_UNICODE avoids "\uXXXX" escapes in the stored
        // string: WordPress's meta-storage layer strips literal
        // backslashes from meta values (its magic-quotes-era unslashing
        // behavior), which otherwise corrupted "·" into "u00b7"
        // for any non-ASCII character (confirmed by hand against a real
        // OCR'd title before this fix).
        return wp_json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    /**
     * @return array<array{id: string, title: string}>
     */
    public static function article_headings(int $post_id): array
    {
        $raw = get_post_meta($post_id, 'article_headings', true);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }
}
