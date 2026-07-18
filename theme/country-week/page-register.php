<?php
/**
 * "Create Account" page (applies automatically to a Page with the
 * slug /register/) — the free account required to download slides and
 * printable country sheets.
 *
 * @package CountryWeek
 */

use CountryWeek\Forms\Registration_Form;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/');
?>

<main class="site-main" id="main">
    <header class="page-header">
        <h1><?php the_title(); ?></h1>
        <p><?php esc_html_e('Create a free account to download country slides and printable sheets for your church.', 'country-week'); ?></p>
    </header>

    <?php echo (new Registration_Form())->render($redirect_to); ?>
</main>

<?php get_footer(); ?>
