<?php
/**
 * The template for displaying all single question, xword and hangman - penguin theme required
 *
 */
get_header();
?>
<div id="content-area">
<div id="primary" style="margin:1em auto;float:none">
	<?php
	while ( have_posts() ) : the_post(); // Start the loop.	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php
		echo '<header class="entry-header">';
		echo '<div class="entry-meta-top">';
		echo meta_icons(); 
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
				echo do_shortcode('[xwordquiz]');
			} else the_content( penguin_read_more_text() );
		?>
		</div><!-- .entry-content -->
		<footer class="entry-footer">
		</footer><!-- .entry-footer -->
	  </article>
	  <?php
		if ( !isset($_GET['hangman'])) setPostViews(get_the_ID());
	  // End the loop.
	endwhile; ?>
</div><!-- #primary -->
</div><!-- #content-area -->
<?php get_footer(); ?>
