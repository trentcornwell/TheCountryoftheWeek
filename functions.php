<?php

if (!defined('ABSPATH')) {
    exit;
}

function country_week_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    register_nav_menus([
        'primary' => __('Primary Menu', 'country-week'),
    ]);
}

add_action('after_setup_theme', 'country_week_setup');

function country_week_assets(): void
{
    wp_enqueue_style(
        'country-week-style',
        get_stylesheet_uri(),
        [],
        wp_get_theme()->get('Version')
    );
}

add_action('wp_enqueue_scripts', 'country_week_assets');
