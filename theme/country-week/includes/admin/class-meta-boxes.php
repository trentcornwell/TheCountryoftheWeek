<?php
/**
 * Admin editing UI for every Country_Meta_Fields group, plus the
 * flag/map media pickers and gallery manager.
 *
 * @package CountryWeek
 */

namespace CountryWeek\Admin;

use CountryWeek\CPT\Country_Meta_Fields;
use CountryWeek\CPT\Country_Post_Type;
use WP_Post;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deliberately plain, native meta boxes rather than ACF or a custom
 * fields framework: Country_Meta_Fields::groups() is the single
 * definition of what fields exist, and this class just loops over it
 * to render inputs and save whatever comes back. Adding a new field to
 * the content model (in Country_Meta_Fields) automatically gets an
 * editing UI here with no further changes needed.
 */
class Meta_Boxes
{
    private const NONCE_ACTION = 'country_week_save_meta';
    private const NONCE_FIELD = 'country_week_meta_nonce';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . Country_Post_Type::POST_TYPE, [$this, 'save']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_media_scripts']);
    }

    public function add_meta_boxes(): void
    {
        foreach (Country_Meta_Fields::groups() as $group_id => $group) {
            add_meta_box(
                'country_week_' . $group_id,
                $group['label'],
                fn (WP_Post $post) => $this->render_group($post, $group_id, $group),
                Country_Post_Type::POST_TYPE,
                'normal',
                'default'
            );
        }

        add_meta_box(
            'country_week_gallery',
            __('Photo Gallery', 'country-week'),
            [$this, 'render_gallery'],
            Country_Post_Type::POST_TYPE,
            'side',
            'default'
        );
    }

    public function enqueue_media_scripts(string $hook): void
    {
        global $post_type;

        if (in_array($hook, ['post.php', 'post-new.php'], true) && $post_type === Country_Post_Type::POST_TYPE) {
            wp_enqueue_media();
            wp_enqueue_script(
                'country-week-admin-media',
                get_theme_file_uri('assets/js/admin-media.js'),
                ['jquery'],
                wp_get_theme()->get('Version'),
                true
            );
            wp_localize_script('country-week-admin-media', 'countryWeekAdminMedia', [
                'galleryTitle' => __('Select Gallery Images', 'country-week'),
            ]);
        }
    }

    private function render_group(WP_Post $post, string $group_id, array $group): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);
        echo '<table class="form-table country-week-meta-table">';

        foreach ($group['fields'] as $key => $field) {
            $value = get_post_meta($post->ID, $key, true);
            $input_id = 'country_week_' . $key;

            echo '<tr><th><label for="' . esc_attr($input_id) . '">' . esc_html($field['label']) . '</label></th><td>';

            if ($field['type'] === Country_Meta_Fields::TYPE_TEXTAREA || $field['type'] === Country_Meta_Fields::TYPE_LIST) {
                $rows = $field['type'] === Country_Meta_Fields::TYPE_LIST ? 5 : 4;
                printf(
                    '<textarea id="%1$s" name="%1$s" rows="%2$d" class="large-text">%3$s</textarea>',
                    esc_attr($input_id),
                    (int) $rows,
                    esc_textarea((string) $value)
                );
            } elseif ($field['type'] === Country_Meta_Fields::TYPE_ATTACHMENT) {
                $this->render_attachment_field($input_id, (int) $value);
            } else {
                printf(
                    '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="regular-text">',
                    esc_attr($input_id),
                    esc_attr((string) $value)
                );
            }

            if (!empty($field['description'])) {
                echo '<p class="description">' . esc_html($field['description']) . '</p>';
            }

            echo '</td></tr>';
        }

        echo '</table>';
    }

    private function render_attachment_field(string $input_id, int $attachment_id): void
    {
        $preview_url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
        ?>
        <div class="country-week-media-field" data-input-id="<?php echo esc_attr($input_id); ?>">
            <input type="hidden" id="<?php echo esc_attr($input_id); ?>" name="<?php echo esc_attr($input_id); ?>" value="<?php echo esc_attr((string) $attachment_id); ?>">
            <div class="country-week-media-field__preview">
                <?php if ($preview_url) : ?>
                    <img src="<?php echo esc_url($preview_url); ?>" alt="" style="max-width:100px;height:auto;display:block;">
                <?php endif; ?>
            </div>
            <button type="button" class="button country-week-media-select"><?php esc_html_e('Select Image', 'country-week'); ?></button>
            <button type="button" class="button country-week-media-remove" <?php echo $attachment_id ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove', 'country-week'); ?></button>
        </div>
        <?php
    }

    public function render_gallery(WP_Post $post): void
    {
        $ids = Country_Meta_Fields::gallery_ids($post->ID);
        ?>
        <div class="country-week-gallery-field">
            <ul class="country-week-gallery-field__list">
                <?php foreach ($ids as $id) :
                    $thumb = wp_get_attachment_image_url($id, 'thumbnail');
                    if (!$thumb) {
                        continue;
                    }
                    ?>
                    <li>
                        <img src="<?php echo esc_url($thumb); ?>" alt="" style="width:60px;height:60px;object-fit:cover;">
                        <input type="hidden" name="gallery_image_id[]" value="<?php echo esc_attr((string) $id); ?>">
                    </li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="button country-week-gallery-add"><?php esc_html_e('Add Images', 'country-week'); ?></button>
        </div>
        <?php
    }

    public function save(int $post_id): void
    {
        if (!isset($_POST[self::NONCE_FIELD]) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        foreach (Country_Meta_Fields::all_fields() as $key => $field) {
            $input_id = 'country_week_' . $key;

            if (!isset($_POST[$input_id])) {
                continue;
            }

            $raw = wp_unslash($_POST[$input_id]);

            $sanitized = match ($field['type']) {
                Country_Meta_Fields::TYPE_TEXTAREA, Country_Meta_Fields::TYPE_LIST => sanitize_textarea_field($raw),
                Country_Meta_Fields::TYPE_ATTACHMENT => absint($raw),
                default => sanitize_text_field($raw),
            };

            update_post_meta($post_id, $key, $sanitized);
        }

        // Gallery: replace the full repeating meta set with whatever was
        // submitted, preserving the chosen order.
        delete_post_meta($post_id, 'gallery_image_id');

        if (!empty($_POST['gallery_image_id']) && is_array($_POST['gallery_image_id'])) {
            foreach (wp_unslash($_POST['gallery_image_id']) as $attachment_id) {
                $attachment_id = absint($attachment_id);

                if ($attachment_id) {
                    add_post_meta($post_id, 'gallery_image_id', $attachment_id);
                }
            }
        }
    }
}
