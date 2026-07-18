<?php
/**
 * Site header: skip link, branding, and primary navigation.
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e('Skip to content', 'country-week'); ?></a>

<header class="site-header">
    <div class="site-header__row">
        <a class="site-header__brand" href="<?php echo esc_url(home_url('/')); ?>">
            <?php bloginfo('name'); ?>
        </a>

        <button type="button" class="site-header__menu-toggle" aria-expanded="false" aria-controls="primary-menu">
            <span class="screen-reader-text"><?php esc_html_e('Menu', 'country-week'); ?></span>
            <span aria-hidden="true">&#9776;</span>
        </button>

        <nav class="site-header__nav" id="primary-menu" aria-label="<?php esc_attr_e('Primary', 'country-week'); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'container' => false,
                'menu_class' => 'primary-menu',
                'fallback_cb' => ['CountryWeek\\Theme', 'render_default_menu'],
            ]);
            ?>
            <form role="search" method="get" class="site-header__search" action="<?php echo esc_url(home_url('/')); ?>">
                <label class="screen-reader-text" for="site-search"><?php esc_html_e('Search', 'country-week'); ?></label>
                <input type="search" id="site-search" name="s" placeholder="<?php esc_attr_e('Search countries&hellip;', 'country-week'); ?>" value="<?php echo esc_attr(get_search_query()); ?>">
            </form>

            <div class="site-header__account">
                <?php if (is_user_logged_in()) : ?>
                    <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Log Out', 'country-week'); ?></a>
                <?php else : ?>
                    <a href="<?php echo esc_url(home_url('/register/')); ?>"><?php esc_html_e('Log In / Register', 'country-week'); ?></a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</header>
