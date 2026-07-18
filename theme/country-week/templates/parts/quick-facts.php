<?php
/**
 * Quick Facts panel. Iterates Country_Meta_Fields' own field registry
 * so this list never drifts out of sync with what's actually editable
 * in wp-admin — skips any field that's empty for this country rather
 * than showing a blank row.
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

$fields = Country_Meta_Fields::groups()['quick_facts']['fields'];
$rendered_any = false;
?>
<section class="quick-facts" aria-labelledby="quick-facts-heading">
    <h2 id="quick-facts-heading"><?php esc_html_e('Quick Facts', 'country-week'); ?></h2>
    <dl class="quick-facts__list">
        <?php foreach ($fields as $key => $field) :
            $value = get_post_meta($country->ID, $key, true);

            if (!is_string($value) || trim($value) === '') {
                continue;
            }

            $rendered_any = true;
            ?>
            <div class="quick-facts__row">
                <dt><?php echo esc_html($field['label']); ?></dt>
                <dd><?php echo esc_html($value); ?></dd>
            </div>
        <?php endforeach; ?>
    </dl>
    <?php if ($rendered_any) : ?>
        <p class="quick-facts__source">
            <?php esc_html_e('Source: CIA World Factbook (public domain)', 'country-week'); ?>
        </p>
    <?php endif; ?>
</section>
