<?php
/**
 * "Related Countries" — same continent/region, computed automatically
 * via Country_Repository::get_related() rather than manually curated.
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\Services\Country_Repository;

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

$related = Country_Repository::get_related($country, 4);

if (empty($related)) {
    return;
}
?>
<section class="related-countries" aria-labelledby="related-countries-heading">
    <h2 id="related-countries-heading"><?php esc_html_e('Related Countries', 'country-week'); ?></h2>
    <ul class="related-countries__list">
        <?php foreach ($related as $related_country) : ?>
            <li>
                <a href="<?php echo esc_url(get_permalink($related_country)); ?>">
                    <?php
                    $flag_id = (int) get_post_meta($related_country->ID, 'flag_image_id', true);

                    if ($flag_id) {
                        echo wp_get_attachment_image($flag_id, 'thumbnail', false, [
                            'loading' => 'lazy',
                            'decoding' => 'async',
                            'alt' => '',
                        ]);
                    }
                    ?>
                    <span><?php echo esc_html(get_the_title($related_country)); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
