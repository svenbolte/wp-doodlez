<?php
/**
 * The template for displaying all single question, xword - penguin theme required
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
<div id="primary" style="margin:1em auto;float:none">
	<?php
	while ( have_posts() ) {
		the_post(); // Start the loop.	?>
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
		<?php
		echo '<header class="entry-header">';
		echo '<div class="entry-meta-top">';
		echo meta_icons(); 
		echo '</div>';
		if ( isset($_GET['crossword']) ) {
			if (1 == esc_html($_GET['crossword'])) echo '<h1 class="entry-title">'.__('crossword','WPdoodlez').'</h1>';
			if (2 == esc_html($_GET['crossword'])) echo '<h1 class="entry-title">'. __('wordsearch','WPdoodlez').'</h1>';
			if (3 == esc_html($_GET['crossword'])) echo '<h1 class="entry-title">'. __('hangman','WPdoodlez').'</h1>';
			if (4 == esc_html($_GET['crossword'])) echo '<h1 class="entry-title">'. __('Sudoku','WPdoodlez').'</h1>';
			if (5 == esc_html($_GET['crossword'])) echo '<h1 class="entry-title">'. __('Shuffle','WPdoodlez').'</h1>';
			if (6 == esc_html($_GET['crossword'])) echo '<h1 class="entry-title">'. __('Rebus','WPdoodlez').'</h1>';
			if (7 == esc_html($_GET['crossword'])) echo '<h1 class="entry-title">'. __('syllable puzzle','WPdoodlez').'</h1>';
			if (8 == esc_html($_GET['crossword'])) echo '<h1 class="entry-title">'. __('car quartet','WPdoodlez').'</h1>';
		} else if ( isset($_GET['ende']) ) {
			if (2 == esc_html($_GET['ende'])) echo '<h1 class="entry-title">'. __('personal exam','WPdoodlez').'</h1>';
		} else {
			if ( is_single() ) {
				the_title( '<h1 class="entry-title">', '</h1>' );
			} else {
				the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
			}
		}	
		echo '</header><!-- .entry-header -->';
		?>
		<div class="entry-content">
		<?php
		// lieber Kreuzworträtsel spielen
		if ( isset($_GET['crossword'])) {
			?>
			<span style="float:right">Spielzeit: <input style="text-align:center;height:1.3em;width:70px;font-size:1.3em" type="text" name="zeit" id="zeit"></span>
			<script>var start = new Date();
			function leadingZero(tish) {
			if (tish <= 9) { tish = '0'+tish; }
			return tish;}
			function zeit() {
			var jetzt = new Date();
			sekunden = parseInt((jetzt.getTime() - start.getTime()) / 1000);
			minuten = parseInt(sekunden / 60);
			sekunden = sekunden % 60;
			text = minuten + ":" + leadingZero(sekunden);
			document.getElementById('zeit').value = text;
			timerID=setTimeout("zeit()", 1000);	}
			zeit();</script>
			<?php
			if (1 == esc_html($_GET['crossword'])) echo xwordquiz();
			if (2 == esc_html($_GET['crossword'])) echo xwordpuzzle();
			if (3 == esc_html($_GET['crossword'])) echo xwordhangman();
			if (4 == esc_html($_GET['crossword'])) echo xsudoku();
			if (5 == esc_html($_GET['crossword'])) echo xwordshuffle();
			if (6 == esc_html($_GET['crossword'])) echo xrebus();
			if (7 == esc_html($_GET['crossword'])) echo xsillableshuffle();
			if (8 == esc_html($_GET['crossword'])) echo xautoquartett();
		} else the_content();
		?>
		</div><!-- .entry-content -->
		<footer class="entry-footer">
		</footer><!-- .entry-footer -->
	  </article>
	  <?php
		setPostViews(get_the_ID());
		penguin_post_navigation();
	}		// End the loop.
	?>
</div><!-- #primary -->
</div><!-- #content-area -->
<?php get_footer(); ?>
