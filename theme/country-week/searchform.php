<?php
/**
 * Search form, reused by header.php and search.php.
 *
 * @package CountryWeek
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url(home_url('/')); ?>">
    <label class="screen-reader-text" for="search-form-input"><?php esc_html_e('Search for:', 'country-week'); ?></label>
    <input type="search" id="search-form-input" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="<?php esc_attr_e('Search countries&hellip;', 'country-week'); ?>">
    <button type="submit"><?php esc_html_e('Search', 'country-week'); ?></button>
</form>
