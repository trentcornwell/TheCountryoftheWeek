<?php
/**
 * Share + Download PDF action bar. The share buttons are plain links
 * to each network's public share-intent URL (no SDK/embed script), plus
 * a "Share" button that progressively enhances into the native Web
 * Share API on devices that support it (see assets/js/main.js).
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\Services\Coloring_Page_Service;
use CountryWeek\Services\Pdf_Service;
use CountryWeek\Services\Slide_Service;

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

$url = get_permalink($country);
$title = get_the_title($country);
$print_url = Pdf_Service::print_url($country);
$slide_url = Slide_Service::download_url($country);
$coloring_url = Coloring_Page_Service::url($country);
$account_required = !is_user_logged_in();
$lock_hint = $account_required ? ' <span class="country-actions__lock" aria-hidden="true">&#128274;</span>' : '';
?>
<div class="country-actions" data-share-url="<?php echo esc_url($url); ?>" data-share-title="<?php echo esc_attr($title); ?>">
    <a class="country-actions__button country-actions__button--pdf" href="<?php echo esc_url($print_url); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Download PDF', 'country-week'); ?><?php echo wp_kses_post($lock_hint); ?>
    </a>

    <a class="country-actions__button" href="<?php echo esc_url($slide_url); ?>">
        <?php esc_html_e('Slide', 'country-week'); ?><?php echo wp_kses_post($lock_hint); ?>
    </a>

    <a class="country-actions__button" href="<?php echo esc_url($coloring_url); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Coloring Page', 'country-week'); ?>
    </a>

    <button type="button" class="country-actions__button country-actions__share-native" hidden>
        <?php esc_html_e('Share', 'country-week'); ?>
    </button>

    <a class="country-actions__button" href="<?php echo esc_url('https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url)); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Share on Facebook', 'country-week'); ?>
    </a>

    <a class="country-actions__button" href="<?php echo esc_url('https://twitter.com/intent/tweet?url=' . rawurlencode($url) . '&text=' . rawurlencode($title)); ?>" target="_blank" rel="noopener">
        <?php esc_html_e('Share on X', 'country-week'); ?>
    </a>

    <a class="country-actions__button" href="<?php echo esc_url('mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($url)); ?>">
        <?php esc_html_e('Share by Email', 'country-week'); ?>
    </a>
</div>
