<?php
/**
 * The "Suggest an Edit" trigger button + native <dialog> modal
 * containing Forms\Suggest_Edit_Form's markup. Opened/closed with a
 * few lines of vanilla JS in assets/js/main.js — no modal library.
 *
 * Expects $args['country'] (WP_Post).
 *
 * @package CountryWeek
 */

use CountryWeek\Forms\Suggest_Edit_Form;

if (!defined('ABSPATH')) {
    exit;
}

$country = $args['country'] ?? null;

if (!$country instanceof WP_Post) {
    return;
}

$dialog_id = 'suggest-edit-' . $country->ID;
?>
<div class="suggest-edit-trigger">
    <button type="button" class="country-actions__button" data-dialog-target="<?php echo esc_attr($dialog_id); ?>">
        <?php esc_html_e('Suggest an Edit', 'country-week'); ?>
    </button>

    <dialog id="<?php echo esc_attr($dialog_id); ?>" class="suggest-edit-dialog">
        <form method="dialog" class="suggest-edit-dialog__close-form">
            <button type="submit" class="suggest-edit-dialog__close" aria-label="<?php esc_attr_e('Close', 'country-week'); ?>">&times;</button>
        </form>
        <h2><?php esc_html_e('Suggest an Edit', 'country-week'); ?></h2>
        <?php echo (new Suggest_Edit_Form())->render($country); ?>
    </dialog>
</div>
