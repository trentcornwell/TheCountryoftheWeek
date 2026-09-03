<?php
/**
 * Homepage-only banner promoting the latest video/handout resource.
 * Currently a single hardcoded entry (see
 * templates/resources/state-of-the-world.php and Hooks\Rewrite_Hooks
 * for the page it links to) — not yet a general "resources" system,
 * by explicit request. Update this file directly for the next one
 * unless/until there's a real reason to generalize it.
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<a class="site-banner" href="<?php echo esc_url(home_url('/resources/state-of-the-world/')); ?>">
    <span class="site-banner__label"><?php esc_html_e('Latest Resource', 'country-week'); ?></span>
    <span class="site-banner__text">
        <?php
        printf(
            /* translators: 1: resource title, 2: date, 3: speaker name, 4: speaker title. */
            esc_html__('%1$s — presented %2$s by %3$s, %4$s', 'country-week'),
            esc_html__('"The State of the World"', 'country-week'),
            esc_html__('September 2, 2026', 'country-week'),
            esc_html__('Travis Snode', 'country-week'),
            esc_html__('Director of Vision Baptist Missions', 'country-week')
        );
        ?>
    </span>
    <span class="site-banner__arrow" aria-hidden="true">&rarr;</span>
</a>
