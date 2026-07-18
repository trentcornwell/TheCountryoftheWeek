<?php
/**
 * "Log In" page (applies automatically to a Page with the slug
 * /login/). Uses WordPress core's own wp_login_form() rather than a
 * hand-rolled authentication form — no reason to reimplement password
 * verification when core already provides a themeable form for it.
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$redirect_to = isset($_GET['redirect_to']) ? esc_url_raw(wp_unslash($_GET['redirect_to'])) : home_url('/');

if (is_user_logged_in()) {
    wp_safe_redirect($redirect_to);
    exit;
}
?>

<main class="site-main" id="main">
    <header class="page-header">
        <h1><?php the_title(); ?></h1>
        <p><?php esc_html_e('Log in to download country slides and printable sheets.', 'country-week'); ?></p>
    </header>

    <div class="account-form">
        <?php
        wp_login_form([
            'redirect' => $redirect_to,
            'label_username' => __('Username or Email', 'country-week'),
            'label_password' => __('Password', 'country-week'),
            'label_log_in' => __('Log In', 'country-week'),
            'remember' => true,
        ]);
        ?>
        <p class="account-form__alt-link">
            <?php
            printf(
                /* translators: %s: register link. */
                esc_html__('Need an account? %s', 'country-week'),
                '<a href="' . esc_url(add_query_arg('redirect_to', $redirect_to, home_url('/register/'))) . '">' . esc_html__('Create one', 'country-week') . '</a>'
            );
            ?>
        </p>
        <p class="account-form__alt-link">
            <a href="<?php echo esc_url(wp_lostpassword_url($redirect_to)); ?>"><?php esc_html_e('Forgot your password?', 'country-week'); ?></a>
        </p>
    </div>
</main>

<?php get_footer(); ?>
