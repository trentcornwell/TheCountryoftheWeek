<?php
/**
 * "Email Preferences" page (applies automatically to a Page with the
 * slug /email-preferences/) — lets a subscriber change their weekly
 * preview opt-in and time zone, or land here after an unsubscribe link.
 *
 * @package CountryWeek
 */

use CountryWeek\Forms\Email_Preferences_Form;

if (!defined('ABSPATH')) {
    exit;
}

$redirect_to = home_url('/email-preferences/');

if (!is_user_logged_in()) {
    wp_safe_redirect(add_query_arg('redirect_to', $redirect_to, home_url('/login/')));
    exit;
}

get_header();
?>

<main class="site-main" id="main">
    <header class="page-header">
        <h1><?php the_title(); ?></h1>
        <p><?php esc_html_e('Choose whether to receive a weekly email previewing the upcoming Country of the Week, and set your time zone so it arrives around midday on Saturday.', 'country-week'); ?></p>
    </header>

    <?php echo (new Email_Preferences_Form())->render(); ?>
</main>

<?php get_footer(); ?>
