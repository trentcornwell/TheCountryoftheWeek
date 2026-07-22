<?php
/**
 * Theme bootstrap orchestrator.
 *
 * @package CountryWeek
 */

namespace CountryWeek;

use CountryWeek\Admin\Admin_Columns;
use CountryWeek\Admin\Meta_Boxes;
use CountryWeek\Admin\Schedule_Dashboard_Widget;
use CountryWeek\Admin\Submission_Moderation;
use CountryWeek\CPT\Country_Adoption_Post_Type;
use CountryWeek\CPT\Country_Meta_Fields;
use CountryWeek\CPT\Country_Post_Type;
use CountryWeek\CPT\Country_Taxonomies;
use CountryWeek\CPT\Edit_Suggestion_Post_Type;
use CountryWeek\CPT\Prayer_Partner_Post_Type;
use CountryWeek\Forms\Adoption_Form;
use CountryWeek\Forms\Email_Preferences_Form;
use CountryWeek\Forms\Prayer_Partner_Form;
use CountryWeek\Forms\Registration_Form;
use CountryWeek\Forms\Suggest_Edit_Form;
use CountryWeek\Hooks\Performance_Hooks;
use CountryWeek\Hooks\Rewrite_Hooks;
use CountryWeek\Hooks\Weekly_Email_Hooks;
use CountryWeek\Seo\Schema_Generator;
use CountryWeek\Seo\Seo_Fields;
use CountryWeek\Seo\Social_Meta;
use CountryWeek\Services\Country_Manifest;
use CountryWeek\Services\Subscriber_Meta_Fields;
use CountryWeek\Shortcodes\Shortcodes;
use CountryWeek\Utilities\Asset_Loader;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The single place that knows the full list of modules this theme is
 * made of. functions.php only requires this file and calls Theme::boot();
 * every other class is self-contained and registers its own hooks via a
 * register() method, so adding a new module means: create the class,
 * require it below, add one line to boot(). Nothing else in the theme
 * needs to change.
 */
class Theme
{
    public static function boot(): void
    {
        self::load_files();
        self::register_modules();

        add_action('after_setup_theme', [self::class, 'setup_theme_support']);
    }

    private static function load_files(): void
    {
        $includes = __DIR__;

        require_once $includes . '/utilities/class-date-utility.php';
        require_once $includes . '/utilities/class-asset-loader.php';
        require_once $includes . '/utilities/class-map-asset.php';

        require_once $includes . '/services/class-rotation-service.php';
        require_once $includes . '/services/class-country-manifest.php';
        require_once $includes . '/services/class-country-repository.php';
        require_once $includes . '/services/class-qr-code-service.php';
        require_once $includes . '/services/class-pdf-service.php';
        require_once $includes . '/services/class-slide-service.php';
        require_once $includes . '/services/class-subscriber-meta-fields.php';
        require_once $includes . '/services/class-unsubscribe-token.php';
        require_once $includes . '/services/class-subscriber-notification-schedule.php';
        require_once $includes . '/services/class-weekly-preview-email.php';
        require_once $includes . '/services/class-subscriber-notifier.php';

        require_once $includes . '/cpt/class-country-post-type.php';
        require_once $includes . '/cpt/class-country-taxonomies.php';
        require_once $includes . '/cpt/class-country-meta-fields.php';
        require_once $includes . '/cpt/class-edit-suggestion-post-type.php';
        require_once $includes . '/cpt/class-prayer-partner-post-type.php';
        require_once $includes . '/cpt/class-country-adoption-post-type.php';

        require_once $includes . '/forms/class-suggest-edit-form.php';
        require_once $includes . '/forms/class-prayer-partner-form.php';
        require_once $includes . '/forms/class-registration-form.php';
        require_once $includes . '/forms/class-adoption-form.php';
        require_once $includes . '/forms/class-email-preferences-form.php';

        require_once $includes . '/seo/class-seo-fields.php';
        require_once $includes . '/seo/class-social-meta.php';
        require_once $includes . '/seo/class-schema-generator.php';

        require_once $includes . '/admin/class-admin-columns.php';
        require_once $includes . '/admin/class-meta-boxes.php';
        require_once $includes . '/admin/class-schedule-dashboard-widget.php';
        require_once $includes . '/admin/class-submission-moderation.php';

        require_once $includes . '/shortcodes/class-shortcodes.php';

        require_once $includes . '/hooks/class-performance-hooks.php';
        require_once $includes . '/hooks/class-rewrite-hooks.php';
        require_once $includes . '/hooks/class-weekly-email-hooks.php';
    }

    private static function register_modules(): void
    {
        $modules = [
            new Country_Post_Type(),
            new Country_Taxonomies(),
            new Country_Meta_Fields(),
            new Country_Manifest(),
            new Edit_Suggestion_Post_Type(),
            new Suggest_Edit_Form(),
            new Prayer_Partner_Post_Type(),
            new Prayer_Partner_Form(),
            new Registration_Form(),
            new Country_Adoption_Post_Type(),
            new Adoption_Form(),
            new Subscriber_Meta_Fields(),
            new Email_Preferences_Form(),
            new Weekly_Email_Hooks(),
            new Seo_Fields(),
            new Social_Meta(),
            new Schema_Generator(),
            new Performance_Hooks(),
            new Rewrite_Hooks(),
            new Asset_Loader(),
            new Shortcodes(),
        ];

        if (is_admin()) {
            $modules[] = new Admin_Columns();
            $modules[] = new Meta_Boxes();
            $modules[] = new Schedule_Dashboard_Widget();

            $modules[] = new Submission_Moderation(Edit_Suggestion_Post_Type::POST_TYPE, Edit_Suggestion_Post_Type::STATUS_META_KEY);
            $modules[] = new Submission_Moderation(Country_Adoption_Post_Type::POST_TYPE, Country_Adoption_Post_Type::STATUS_META_KEY);
        }

        foreach ($modules as $module) {
            $module->register();
        }
    }

    public static function setup_theme_support(): void
    {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('automatic-feed-links');
        add_theme_support('align-wide');
        add_theme_support('responsive-embeds');
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

    /**
     * A sensible default navigation so the site is fully usable the
     * moment the theme is activated, before an administrator has built
     * a menu in Appearance > Menus. Matches the Home / Countries /
     * Schedule / About / Suggest an Edit structure in the project spec.
     * Used as wp_nav_menu()'s fallback_cb in header.php.
     */
    public static function render_default_menu(): void
    {
        $links = [
            ['label' => __('Home', 'country-week'), 'url' => home_url('/')],
            ['label' => __('Countries', 'country-week'), 'url' => get_post_type_archive_link('country')],
            ['label' => __('Schedule', 'country-week'), 'url' => home_url('/schedule/')],
            ['label' => __('About', 'country-week'), 'url' => home_url('/about/')],
            ['label' => __('Join Us in Prayer', 'country-week'), 'url' => home_url('/join-us-in-prayer/')],
            ['label' => __('Suggest an Edit', 'country-week'), 'url' => home_url('/suggest-an-edit/')],
        ];

        echo '<ul class="primary-menu">';

        foreach ($links as $link) {
            if (!$link['url']) {
                continue;
            }

            printf(
                '<li><a href="%s">%s</a></li>',
                esc_url($link['url']),
                esc_html($link['label'])
            );
        }

        echo '</ul>';
    }
}
