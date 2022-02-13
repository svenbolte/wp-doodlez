<?php

/**
 * The template for displaying all single wpdoodle
 *
 */
wp_enqueue_script( "WPdoodlez", plugins_url( 'WPdoodlez.js', __FILE__ ), array('jquery'), null, true);
wp_enqueue_style( "WPdoodlez", plugins_url( 'WPdoodlez.css', __FILE__ ), array(), null, 'all');
get_header();
?>

<div id="primary" class="content-area" style="max-width:900px;background-color:white;margin:0 auto;">
    <main id="main" class="site-main" role="main">
        <?php
        // Start the loop.
        while ( have_posts() ) : the_post();
            ?>
            <article id = "post-<?php the_ID(); ?>" <?php post_class(); ?>>
                <header class="entry-header">
                    <?php
                    if ( is_single() ) :
                        the_title( '<h1 class="entry-title">', '</h1>' );
                    else :
                        the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' );
                    endif;
                    ?>
                </header><!-- .entry-header -->
				<div class="entry-content">
					<?php echo get_doodlez_content(true); ?>
                </div><!-- .entry-content -->
                <footer class="entry-footer">
				<?php edit_post_link( __( 'Edit','WPdoodlez' ), '<span class="edit-link">', '</span>' ); ?>
                </footer><!-- .entry-footer -->
                <script>
                    var wpdoodle_ajaxurl = '<?php echo admin_url( 'admin-ajax.php', is_ssl() ? 'https' : 'http' ); ?>';
                    var wpdoodle = '<?php echo md5( AUTH_KEY . get_the_ID() ); ?>';
                </script>
            </article>
            <?php
            // If comments are open or we have at least one comment, load up the comment template.
            if ( comments_open() || get_comments_number() ) :
                comments_template();
            endif;
        // End the loop.
        endwhile;
        ?>

    </main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>