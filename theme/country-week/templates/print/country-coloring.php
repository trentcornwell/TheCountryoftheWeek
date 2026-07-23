<?php
/**
 * Standalone, printable black-and-white coloring page featuring the
 * country's own outline map — no header.php/footer.php site chrome,
 * same pattern as templates/print/country-print.php. Reached via the
 * /coloring/ rewrite endpoint; see Hooks\Rewrite_Hooks. Deliberately
 * NOT gated behind login (unlike /print/ and /slide/) — free for
 * anyone, by explicit request.
 *
 * @package CountryWeek
 */

use CountryWeek\Utilities\Asset_Loader;
use CountryWeek\Utilities\Map_Asset;

if (!defined('ABSPATH')) {
    exit;
}

the_post();
$country = get_post();
$map_markup = Map_Asset::inline_markup_for($country);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <title>
        <?php
        printf(
            /* translators: %s: country name. */
            esc_html__('%s Coloring Page', 'country-week'),
            esc_html(get_the_title($country))
        );
        ?>
         — <?php bloginfo('name'); ?>
    </title>
    <link rel="stylesheet" href="<?php echo esc_url(Asset_Loader::print_stylesheet_url()); ?>">
</head>
<body class="print-sheet">
    <button type="button" class="print-sheet__print-button no-print" onclick="window.print()">
        <?php esc_html_e('Print / Save as PDF', 'country-week'); ?>
    </button>

    <main class="print-sheet__page coloring-page">
        <h1 class="coloring-page__title"><?php echo esc_html(get_the_title($country)); ?></h1>
        <p class="coloring-page__caption"><?php esc_html_e('Color in the shape of this country!', 'country-week'); ?></p>
        <div class="coloring-page__art">
            <?php
            // Trusted, pipeline-generated markup — see
            // Map_Asset::inline_markup_for()'s docblock.
            echo $map_markup;
            ?>
        </div>
    </main>
</body>
</html>
