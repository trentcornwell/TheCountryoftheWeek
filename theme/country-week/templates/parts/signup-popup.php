<?php
/**
 * A once-per-session welcome popup inviting logged-out visitors to
 * create a free account. Rendered site-wide from footer.php, but the
 * markup is only output at all when the visitor isn't logged in —
 * logged-in visitors never receive it, not even hidden in the DOM.
 *
 * Shown/dismissed logic lives in assets/js/main.js (sessionStorage-based,
 * so it reappears on a visitor's next visit but not on every page view
 * within the same session).
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

if (is_user_logged_in()) {
    return;
}
?>
<dialog id="signup-popup" class="signup-popup">
    <form method="dialog" class="signup-popup__close-form">
        <button type="submit" class="signup-popup__close" aria-label="<?php esc_attr_e('Close', 'country-week'); ?>">&times;</button>
    </form>

    <p class="signup-popup__eyebrow"><?php esc_html_e('Sign Up and Join Us', 'country-week'); ?></p>
    <h2><?php esc_html_e('Get Access to Slides, PDFs, and More', 'country-week'); ?></h2>
    <p class="signup-popup__body">
        <?php esc_html_e('Create a free account and you\'ll be able to download this week\'s presentation slide, the printable country sheet, and other resources for your church.', 'country-week'); ?>
    </p>
    <p class="signup-popup__tagline">
        <?php esc_html_e('Our obedient response to Jesus\'s request to pray for laborers.', 'country-week'); ?>
    </p>

    <div class="signup-popup__actions">
        <a class="country-actions__button country-actions__button--pdf" href="<?php echo esc_url(home_url('/register/')); ?>">
            <?php esc_html_e('Create Free Account', 'country-week'); ?>
        </a>
        <a class="signup-popup__login-link" href="<?php echo esc_url(home_url('/login/')); ?>">
            <?php esc_html_e('Already have an account? Log in', 'country-week'); ?>
        </a>
    </div>
</dialog>
