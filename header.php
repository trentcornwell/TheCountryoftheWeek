<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <a href="<?php echo esc_url(home_url('/')); ?>">
        <h1><?php bloginfo('name'); ?></h1>
    </a>

    <?php
    wp_nav_menu([
        'theme_location' => 'primary',
        'container' => 'nav',
        'fallback_cb' => false,
    ]);
    ?>
</header>
