<?php
/**
 * Share + Download PDF action bar. A single "Share" button: the native
 * Web Share API on devices that support it, falling back to copying
 * the link to the clipboard everywhere else (see assets/js/main.js) —
 * no per-network share links.
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\Hooks\Rewrite_Hooks;
use CountryWeek\Services\Coloring_Page_Service;
use CountryWeek\Services\Pdf_Service;
use CountryWeek\Services\Slide_Service;
use CountryWeek\Services\Slideshow_Service;

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
$account_required = Rewrite_Hooks::RESOURCES_REQUIRE_ACCOUNT && !is_user_logged_in();
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

    <?php if (Slideshow_Service::has_slideshow($country)) : ?>
        <a class="country-actions__button" href="<?php echo esc_url(Slideshow_Service::viewer_url($country)); ?>">
            <?php esc_html_e('Slideshow', 'country-week'); ?>
        </a>
    <?php endif; ?>

    <button
        type="button"
        class="country-actions__button country-actions__share-native"
        data-copied-label="<?php esc_attr_e('Link Copied!', 'country-week'); ?>"
    >
        <?php esc_html_e('Share', 'country-week'); ?>
    </button>
</div>
