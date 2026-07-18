<?php
/**
 * Generic bulleted-list section, reused for both "Interesting Facts"
 * and "Did You Know".
 *
 * Expects $args['country'] (WP_Post), $args['meta_key'] (string),
 * $args['heading'] (string), $args['heading_id'] (string).
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

$items = Country_Meta_Fields::lines($country->ID, $args['meta_key'] ?? '');

if (empty($items)) {
    return;
}

$heading_id = $args['heading_id'] ?? 'facts-list';
?>
<section class="facts-list" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
    <h2 id="<?php echo esc_attr($heading_id); ?>"><?php echo esc_html($args['heading'] ?? ''); ?></h2>
    <ul class="facts-list__items">
        <?php foreach ($items as $item) : ?>
            <li><?php echo esc_html($item); ?></li>
        <?php endforeach; ?>
    </ul>
</section>
