<?php
/**
 * The template for displaying all single question
 *
 */
add_action('wp_enqueue_scripts', 'cpz_setup_wdscript');
function cpz_setup_wdscript() {
	wp_register_style('crossword-style', plugins_url('crossword.min.css', __FILE__) );
	wp_register_script('crossword-script', plugins_url('crossword.min.js', __FILE__), array('jquery'), false, '', true);
	if ( isset($_GET['crossword']) && 2 == esc_html($_GET['crossword']) ) {
		wp_register_style('wordsearch-style', plugins_url('wordsearch.min.css', __FILE__),'','',false );
		wp_register_script('wordsearch-script', plugins_url('wordsearch.min.js', __FILE__),'','',false );
		wp_enqueue_style('wordsearch-style');
		wp_enqueue_script('wordsearch-script');
	}	
}

get_header();
?>
<div id="content-area">
<div id="primary">
	<?php
	while ( have_posts() ) : the_post(); // Start the loop.	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php
		echo '<header class="entry-header">';
		echo '<div class="entry-meta-top">';
		edit_post_link( __( 'Edit','WPdoodlez' ), '<span class="edit-link">', '</span>' );
		echo '</div>';
		if ( is_single() ) {
			the_title( '<h1 class="entry-title">', '</h1>' );
		} else {
			the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
		}
		echo '</header><!-- .entry-header -->';
		?>
		<div class="entry-content">
		<?php
			// lieber Kreuzworträtsel spielen
			if ( isset($_GET['crossword'])) {
				if (1 == esc_html($_GET['crossword'])) echo xwordquiz();
				if (2 == esc_html($_GET['crossword'])) echo xwordpuzzle();
				if (3 == esc_html($_GET['crossword'])) echo xwordhangman();
			} else the_content( );
		?>
		</div><!-- .entry-content -->
		<footer class="entry-footer">
		</footer><!-- .entry-footer -->
	  </article>
	  <?php
	  // End the loop.
	endwhile; ?>
</div><!-- #primary -->
</div><!-- #content-area -->
<?php get_footer(); ?>
