<?php
/**
 * Photo gallery grid. Every image beyond the first is lazy-loaded;
 * clicking an image opens the full-size original in a new tab rather
 * than pulling in a lightbox library, keeping this dependency-free.
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\CPT\Country_Meta_Fields;

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

$ids = Country_Meta_Fields::gallery_ids($country->ID);

if (empty($ids)) {
    return;
}
?>
<section class="photo-gallery" aria-labelledby="photo-gallery-heading">
    <h2 id="photo-gallery-heading"><?php esc_html_e('Photo Gallery', 'country-week'); ?></h2>
    <div class="photo-gallery__grid">
        <?php foreach ($ids as $index => $attachment_id) :
            $full_url = wp_get_attachment_image_url($attachment_id, 'full');

            if (!$full_url) {
                continue;
            }
            ?>
            <a href="<?php echo esc_url($full_url); ?>" target="_blank" rel="noopener" class="photo-gallery__item">
                <?php
                echo wp_get_attachment_image($attachment_id, 'medium', false, [
                    'loading' => $index === 0 ? 'eager' : 'lazy',
                    'decoding' => 'async',
                    'alt' => '',
                ]);
                ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
