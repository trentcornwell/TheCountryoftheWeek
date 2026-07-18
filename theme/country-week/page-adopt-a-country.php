<?php
/**
 * "Adopt a Country" page (applies automatically to a Page with the
 * slug /adopt-a-country/). Reached either directly from the nav, or
 * from the "Adopt This Country" link at the bottom of a specific
 * country page (which passes ?country_id= to lock the selection).
 *
 * @package CountryWeek
 */

use CountryWeek\Forms\Adoption_Form;

if (!defined('ABSPATH')) {
    exit;
}

get_header();

$country = null;

if (isset($_GET['country_id'])) {
    $candidate = get_post(absint($_GET['country_id']));

    if ($candidate instanceof WP_Post && $candidate->post_type === 'country') {
        $country = $candidate;
    }
}
?>

<main class="site-main" id="main">
    <header class="page-header">
        <h1><?php the_title(); ?></h1>
        <p><?php esc_html_e('Adopting a country means committing to help us keep its page accurate and up to date — checking facts, suggesting edits, and being a point of contact for that country.', 'country-week'); ?></p>
    </header>

    <?php echo (new Adoption_Form())->render($country); ?>
</main>

<?php get_footer(); ?>
