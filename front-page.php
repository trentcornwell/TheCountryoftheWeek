<?php get_header(); ?>

<main class="site-main">
    <section>
        <p>Explore the world one country at a time.</p>

        <h2><?php bloginfo('name'); ?></h2>

        <?php
        while (have_posts()) :
            the_post();
            the_content();
        endwhile;
        ?>
    </section>
</main>

<?php get_footer(); ?>
