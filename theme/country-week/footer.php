<footer class="site-footer">
    <nav class="site-footer__nav" aria-label="<?php esc_attr_e('Footer', 'country-week'); ?>">
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'country-week'); ?></a>
        <a href="<?php echo esc_url((string) get_post_type_archive_link('country')); ?>"><?php esc_html_e('Countries', 'country-week'); ?></a>
        <a href="<?php echo esc_url(home_url('/schedule/')); ?>"><?php esc_html_e('Schedule', 'country-week'); ?></a>
        <a href="<?php echo esc_url(home_url('/about/')); ?>"><?php esc_html_e('About', 'country-week'); ?></a>
        <a href="<?php echo esc_url(home_url('/join-us-in-prayer/')); ?>"><?php esc_html_e('Join Us in Prayer', 'country-week'); ?></a>
        <a href="<?php echo esc_url(home_url('/adopt-a-country/')); ?>"><?php esc_html_e('Adopt a Country', 'country-week'); ?></a>
        <a href="<?php echo esc_url(home_url('/suggest-an-edit/')); ?>"><?php esc_html_e('Suggest an Edit', 'country-week'); ?></a>
        <a href="<?php echo esc_url(home_url('/register/')); ?>"><?php esc_html_e('Log In / Register', 'country-week'); ?></a>
    </nav>
    <p class="site-footer__copyright">
        &copy; <?php echo esc_html(wp_date('Y')); ?>
        <?php bloginfo('name'); ?>. <?php esc_html_e('All rights reserved.', 'country-week'); ?>
    </p>
</footer>

<?php get_template_part('templates/parts/signup-popup'); ?>

<?php wp_footer(); ?>
</body>
</html>
