<?php
/**
 * Standalone, print-optimized country sheet — no header.php/footer.php
 * site chrome. This is the entire "Download PDF" feature: the visitor's
 * own browser turns this page into a PDF via window.print(), so there
 * is no PDF-generation library dependency (see Services\Pdf_Service).
 *
 * Loaded via the /print/ rewrite endpoint; see Hooks\Rewrite_Hooks.
 *
 * @package CountryWeek
 */

use CountryWeek\CPT\Country_Meta_Fields;
use CountryWeek\Services\Pdf_Service;
use CountryWeek\Utilities\Asset_Loader;

if (!defined('ABSPATH')) {
    exit;
}

the_post();
$country = get_post();
$quick_facts = Country_Meta_Fields::groups()['quick_facts']['fields'];
$flag_id = (int) get_post_meta($country->ID, 'flag_image_id', true);
$qr_data_uri = Pdf_Service::qr_code_data_uri($country);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title><?php echo esc_html(get_the_title($country)); ?> — <?php bloginfo('name'); ?></title>
    <link rel="stylesheet" href="<?php echo esc_url(Asset_Loader::print_stylesheet_url()); ?>">
</head>
<body class="print-sheet">
    <button type="button" class="print-sheet__print-button no-print" onclick="window.print()">
        <?php esc_html_e('Print / Save as PDF', 'country-week'); ?>
    </button>

    <main class="print-sheet__page">
        <header class="print-sheet__header">
            <?php if ($flag_id) : ?>
                <?php echo wp_get_attachment_image($flag_id, 'medium', false, ['class' => 'print-sheet__flag', 'alt' => '']); ?>
            <?php endif; ?>
            <h1><?php echo esc_html(get_the_title($country)); ?></h1>
            <p class="print-sheet__tagline"><?php bloginfo('name'); ?></p>
        </header>

        <div class="print-sheet__map">
            <img src="<?php echo esc_url(country_week_get_map_url($country)); ?>" alt="" width="1000" height="1000">
        </div>

        <section class="print-sheet__facts">
            <h2><?php esc_html_e('Quick Facts', 'country-week'); ?></h2>
            <dl>
                <?php foreach ($quick_facts as $key => $field) :
                    $value = get_post_meta($country->ID, $key, true);

                    if (!is_string($value) || trim($value) === '') {
                        continue;
                    }
                    ?>
                    <dt><?php echo esc_html($field['label']); ?></dt>
                    <dd><?php echo esc_html($value); ?></dd>
                <?php endforeach; ?>
            </dl>
            <p class="print-sheet__source"><?php esc_html_e('Source: CIA World Factbook (public domain)', 'country-week'); ?></p>
        </section>

        <?php if (has_excerpt($country)) : ?>
            <section class="print-sheet__summary">
                <h2><?php esc_html_e('Overview', 'country-week'); ?></h2>
                <p><?php echo esc_html(get_the_excerpt($country)); ?></p>
            </section>
        <?php endif; ?>

        <?php
        $prayer_intro = get_post_meta($country->ID, 'prayer_intro', true);
        $prayer_points = Country_Meta_Fields::lines($country->ID, 'prayer_points');
        $prayer_source = get_post_meta($country->ID, 'prayer_source', true);
        ?>
        <?php if ($prayer_intro !== '' || !empty($prayer_points)) : ?>
            <section class="print-sheet__prayer">
                <h2><?php esc_html_e('Pray for This Country', 'country-week'); ?></h2>
                <?php if ($prayer_intro !== '') : ?>
                    <p><?php echo esc_html($prayer_intro); ?></p>
                <?php endif; ?>
                <?php if (!empty($prayer_points)) : ?>
                    <ul>
                        <?php foreach ($prayer_points as $point) : ?>
                            <li><?php echo esc_html($point); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if (is_string($prayer_source) && $prayer_source !== '') : ?>
                    <p class="print-sheet__source">
                        <?php
                        printf(
                            /* translators: %s: source name. */
                            esc_html__('Source: %s', 'country-week'),
                            esc_html($prayer_source)
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <footer class="print-sheet__footer">
            <div class="print-sheet__qr">
                <img src="<?php echo esc_attr($qr_data_uri); ?>" alt="<?php esc_attr_e('QR code linking to this page online', 'country-week'); ?>">
            </div>
            <div class="print-sheet__footer-text">
                <p>
                    &copy; <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?>
                </p>
                <p><?php echo esc_html(home_url('/')); ?></p>
            </div>
        </footer>
    </main>
</body>
</html>
