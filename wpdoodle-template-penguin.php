<?php

/**
 * The template for displaying all single wpdoodle - penguin theme required
 *
 */
wp_enqueue_script( "jquery" );
wp_enqueue_script( "WPdoodlez", plugins_url( 'WPdoodlez.js', __FILE__ ), array('jquery'), null, true);
wp_enqueue_style( "WPdoodlez", plugins_url( 'WPdoodlez.css', __FILE__ ), array(), null, 'all');
get_header();
?>

<div id="content-area">
	<div id="primary">
    <main id="main" class="site-main" role="main">
	<?php
	// Start the loop.
	while ( have_posts() ) : the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php 
		penguin_entry_top(); ?>
		<header class="entry-header">
		<?php
		get_template_part( 'template-parts/meta', 'top' );
		if ( is_single() ) :
			the_title( '<h1 class="entry-title">', '</h1>' );
		else :
			the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
		endif;
		?>
				<div class="entry-content">
					<?php get_doodlez_content(); ?>
                </div><!-- .entry-content -->
                <footer class="entry-footer">
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
			setPostViews(get_the_ID());
			// End the loop.
        endwhile;
        ?>

    </main><!-- .site-main -->
	</div><!-- #primary -->

<?php get_sidebar(); ?>
</div><!-- #content-area -->
<?php get_footer(); ?>
