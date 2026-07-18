<?php
/**
 * Suggested Reading list. Each source line is stored as
 * "Title | https://example.com" and parsed here; a line with no "|"
 * is shown as plain text with no link.
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

$lines = Country_Meta_Fields::lines($country->ID, 'suggested_reading');

if (empty($lines)) {
    return;
}
?>
<section class="suggested-reading" aria-labelledby="suggested-reading-heading">
    <h2 id="suggested-reading-heading"><?php esc_html_e('Suggested Reading', 'country-week'); ?></h2>
    <ul class="suggested-reading__list">
        <?php foreach ($lines as $line) :
            $parsed = Country_Meta_Fields::parse_title_url_line($line);
            ?>
            <li>
                <?php if ($parsed['url'] !== '') : ?>
                    <a href="<?php echo esc_url($parsed['url']); ?>" rel="noopener" target="_blank">
                        <?php echo esc_html($parsed['title']); ?>
                    </a>
                <?php else : ?>
                    <?php echo esc_html($parsed['title']); ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
