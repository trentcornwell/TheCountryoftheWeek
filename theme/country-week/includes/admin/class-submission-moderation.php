<?php
/**
 * Shared "Status" column + Approve/Reject quick-action links for any
 * submission-style post type (edit suggestions, country adoptions).
 *
 * @package CountryWeek
 */

namespace CountryWeek\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Instantiated once per moderated post type (see Theme::register_modules()).
 * This is what makes "suggested edits come to a moderator for approval"
 * concrete rather than just implicit in "it's stored somewhere admins
 * can see" — every submission list shows its current status, and a
 * moderator can flip it with one click, the same Approve/Reject-style
 * pattern WordPress itself uses for comments.
 *
 * Status labels are deliberately computed lazily inside the hook
 * callbacks below (add_status_column(), render_status_column()) rather
 * than passed in as already-translated strings at construction time:
 * Theme::register_modules() runs during theme bootstrap, before
 * WordPress has loaded text domains, so any __() call made there
 * directly (rather than deferred into a later hook) triggers a
 * "translation loading triggered too early" notice.
 */
class Submission_Moderation
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_APPROVED = 'approved';
    private const STATUS_REJECTED = 'rejected';

    private string $post_type;
    private string $status_meta_key;

    public function __construct(string $post_type, string $status_meta_key)
    {
        $this->post_type = $post_type;
        $this->status_meta_key = $status_meta_key;
    }

    public function register(): void
    {
        add_filter("manage_{$this->post_type}_posts_columns", [$this, 'add_status_column']);
        add_action("manage_{$this->post_type}_posts_custom_column", [$this, 'render_status_column'], 10, 2);
        add_action('admin_post_country_week_moderate_' . $this->post_type, [$this, 'handle_status_change']);
        add_action('admin_head-edit.php', [$this, 'print_status_styles']);
    }

    /**
     * @return array<string, string> status value => label. A method
     *                                rather than a class constant so
     *                                the translation happens only when
     *                                actually rendering, never at
     *                                bootstrap time.
     */
    private function status_labels(): array
    {
        return [
            self::STATUS_PENDING => __('Pending', 'country-week'),
            self::STATUS_APPROVED => __('Approved', 'country-week'),
            self::STATUS_REJECTED => __('Rejected', 'country-week'),
        ];
    }

    public function print_status_styles(): void
    {
        global $typenow;

        if ($typenow !== $this->post_type) {
            return;
        }
        ?>
        <style>
            .country-week-status { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
            .country-week-status--pending { background: #fdf0d5; color: #8a5a00; }
            .country-week-status--approved { background: #d9f2e0; color: #145c2c; }
            .country-week-status--rejected { background: #fbdada; color: #7a1c1c; }
            .country-week-status-link { font-size: 12px; }
        </style>
        <?php
    }

    public function add_status_column(array $columns): array
    {
        $columns['country_week_status'] = __('Status', 'country-week');

        return $columns;
    }

    public function render_status_column(string $column, int $post_id): void
    {
        if ($column !== 'country_week_status') {
            return;
        }

        $labels = $this->status_labels();
        $status = get_post_meta($post_id, $this->status_meta_key, true);
        $status = is_string($status) && isset($labels[$status]) ? $status : self::STATUS_PENDING;

        printf('<span class="country-week-status country-week-status--%s">%s</span>', esc_attr($status), esc_html($labels[$status]));

        echo '<br>';

        foreach ($labels as $target_status => $target_label) {
            if ($target_status === $status) {
                continue;
            }

            $url = wp_nonce_url(
                add_query_arg([
                    'action' => 'country_week_moderate_' . $this->post_type,
                    'post_id' => $post_id,
                    'status' => $target_status,
                ], admin_url('admin-post.php')),
                'country_week_moderate_' . $post_id
            );

            printf(
                '<a href="%s" class="country-week-status-link">%s</a> ',
                esc_url($url),
                esc_html(sprintf(
                    /* translators: %s: target status label, e.g. "Approved". */
                    __('Mark as %s', 'country-week'),
                    $target_label
                ))
            );
        }
    }

    public function handle_status_change(): void
    {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';

        if (
            !$post_id
            || !isset($this->status_labels()[$status])
            || !isset($_GET['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'country_week_moderate_' . $post_id)
        ) {
            wp_die(esc_html__('Invalid moderation request.', 'country-week'));
        }

        $post = get_post($post_id);

        if (!$post || $post->post_type !== $this->post_type || !current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('You are not allowed to do that.', 'country-week'));
        }

        update_post_meta($post_id, $this->status_meta_key, $status);

        wp_safe_redirect(wp_get_referer() ?: admin_url('edit.php?post_type=' . $this->post_type));
        exit;
    }
}
