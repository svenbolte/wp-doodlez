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
	<?php penguin_entry_top(); ?>
	<header class="entry-header">

	<div class="greybox">
	<?php
	$categories_list = get_the_category_list( esc_html__( ', ', 'penguin' ) );
	if ( $categories_list && penguin_categorized_blog() ) : ?>
		<i title="<?php esc_html_e( 'Categories icon', 'penguin' ) ?>" class="fa fa-folder-open"></i>
		<?php echo $categories_list; ?>
	<?php endif; // End if categories ?>

		<?php
		/* translators: used between list items, there is a space after the comma */
		$tags_list = get_the_tag_list( '', esc_html_x( ', ', 'list item separator', 'penguin' ) );
		if ( $tags_list ) :
		?>
		&nbsp;<i title="<?php esc_html_e( 'Tags icon', 'penguin' ) ?>" class="fa fa-tag"></i>
		<?php echo '<span style="font-size:0.8em;">' . $tags_list .'</span>'; 
		endif; // End if $tags_list ?>
	</div>	
		
		<?php
		if ( has_post_thumbnail() == false ) :
		$category = get_the_category(); 
		if ( z_taxonomy_image_url($category[0]->term_id) != NULL ) {
			$cbild = z_taxonomy_image_url($category[0]->term_id);
			echo ('<div class="post-thumbnail"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">');
			echo ('<img src="' . $cbild . '" class="attachment-Penguin800X400 size-Penguin800X400 wp-post-image" style="max-height:220px" /></a></div>');	
		} else {
			$cbild = '';
     		echo ('<br>');
		}
		endif;
		?>

		<?php if ( has_post_thumbnail() ) : ?>
		<?php get_template_part( 'template-parts/the_post_thumbnail' ); ?>
		<?php endif; ?>

		<?php get_template_part( 'template-parts/meta', 'top' ); ?>
	
		<?php
			if ( is_single() ) :
				the_title( '<h1 class="entry-title">', '</h1>' );
			else :
				the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' );
			endif;
		?>
	</header><!-- .entry-header -->

					<div class="entry-content">
                    <?php
					/* translators: %s: Name of current post */
                    the_content();
                    $suggestions = $votes_cout  = [ ];
                    $customs     = get_post_custom( get_the_ID() );
                    foreach ( $customs as $key => $value ) {
                        if ( !preg_match( '/^_/is', $key ) ) {
                            $suggestions[ $key ] = $value;
                            $votes_cout[ $key ]  = 0;
                        }
                    }
                     // print_r($suggestions);
					// admin Details link für polls
					if (is_user_logged_in()) {
						if (!isset($_GET['admin']) ) {
							echo '<span style="float:right"><a href="'.add_query_arg( array('admin'=>'1' ), $wp->request ).'">' . wpd_translate( 'poll details' ) . '</a>';	
						} else {
							echo '<span style="float:right"><a href="'.$wp->request .'">' . wpd_translate( 'poll results' ) . '</a>';	
						}	
					}	
						
                    /* password protected? */
                    if ( !post_password_required() ) {
						// Wenn Feldnamen vote1...20, dann eine Umfrage machen, sonst eine Terminabstimmung
						$polli = array_key_exists('vote1', $suggestions);
						if (  $polli  && !isset($_GET['admin']) ) {
							$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
							foreach ( $votes as $name => $vote ) {
								foreach ( $suggestions as $key => $value ) {
									if ($key != "post_views_count" && $key != "likes") {
										if ( !empty($vote[ $key ]) ) {	$votes_cout[ $key ] ++; }
									}	
								}
							}	
                            $pielabel = ''; $piesum = '';
							foreach ( $votes_cout as $key => $value ) {
								if ($key != "post_views_count" && $key != "likes" ) {
									$pielabel.=$key.','; $piesum .= $value.','; 
								}
							}
							$hashuser = substr(md5(time()),1,20) . '-' . get_the_user_ip();
							echo '<br><table id="pollselect"><thead><th colspan=3>' . wpd_translate( 'your choice' ) . '</th></thead>';	
							foreach ( $suggestions as $key => $value ) {
								 if ($key != "post_views_count" && $key != "likes" ) {
                                        echo'<tr><td><label><input type="checkbox" name="'.$key.'" class="wpdoodlez-input"></td><td>';
										echo $value[ 0 ] .'</label></td><td>'.$votes_cout[ $key ].'</td></tr>';
								 }	
							 }
							echo '<tr><td colspan=3><input type="hidden" id="wpdoodlez-name" value="'.$hashuser.'">';
							echo '<button id="wpdoodlez_poll">' . wpd_translate( 'Vote!' ) . '</button></td></tr>';
							echo '</table>';
						} else {
						// Dies nur ausführen, wenn Feldnamen nicht vote1...20
							?>
							<h4><?php echo wpd_translate( 'Voting' ); ?></h4>
							<table>
								<thead>
									<tr>
										<th><?php echo wpd_translate( 'User name' ); ?></th>
										<?php
											foreach ( $suggestions as $key => $value ) {
												if ($key != "post_views_count" && $key != "likes" ) {
													?><th><?php echo $key; ?></th><?php
												}	
											}
											?>
										<th><?php echo wpd_translate( 'Manage vote' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$myname = $_COOKIE[ 'wpdoodlez-' . md5( AUTH_KEY . get_the_ID() ) ];
									?>
									<tr id="wpdoodlez-form">
										<td><input type="text" 
												   placeholder="<?php echo wpd_translate( 'Your name' ) ?>" 
												   class="wpdoodlez-input"
												   id="wpdoodlez-name" size="10"></td>
											<?php
											foreach ( $suggestions as $key => $value ) {
												if ($key != "post_views_count" && $key != "likes"  ) {
													?><td><label> <input type="checkbox" name="<?php echo $key; ?>" class="wpdoodlez-input">
											<?php
													echo $value[ 0 ]; ?></label>
											</td><?php
												}
										}
										?><td>
											<button id="wpdoodlez_vote"><?php echo wpd_translate( 'Vote!' ); ?>
											</button></td>
									</tr>
									<?php
									$votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
									foreach ( $votes as $name => $vote ) {
										?><tr id="<?php echo 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ) . '-' . md5( $name ); ?>" 
												class="<?php echo $myname == $name ? 'myvote' : '';  ?>">
												<?php
												echo '<td>' . substr($name,0,20);
												// Wenn ipflag plugin aktiv und user angemeldet
												if( class_exists( 'ipflag' ) && is_user_logged_in() ) {
													global $ipflag;
													$nameip = substr($name,21,strlen($name)-21);
													if(isset($ipflag) && is_object($ipflag)){
														if(($info = $ipflag->get_info($nameip)) != false){
															echo ' '.$info->code .  ' ' .$info->name. ' ' . $ipflag->get_flag($info, '') ;
														} else { echo ' '. $ipflag->get_flag($info, '') . ' '; }
													} 
												}	
											echo '</td>';
											foreach ( $suggestions as $key => $value ) {
												if ($key != "post_views_count" && $key != "likes") {
													?><td>
														<?php
														if ( !empty($vote[ $key ]) ) {
															$votes_cout[ $key ] ++;
															?>
														<label 
															data-key="<?php echo $key; ?>"
															><?php echo $value[ 0 ]; ?></label><?php
														} else {
															?>
														<label></label><?php }
														?>
												</td><?php
												}
											}	
											?>
									<td><?php
												if ( current_user_can( 'delete_published_posts' ) ) {
													?>
													<button class="wpdoodlez-delete" 
															data-vote="<?php echo md5( $name ); ?>" 
															data-realname="<?php echo $name; ?>"
															><?php echo wpd_translate( 'delete' ); ?></button><?php
														}
														if ( $myname == $name ) {
															?>
													<button class="wpdoodlez-edit" 
															data-vote="<?php echo md5( $name ); ?>" 
															data-realname="<?php echo $name; ?>"
															><?php echo wpd_translate( 'edit' ); ?></button><?php
												}
												?></td>
										</tr><?php
									}
									?>
								</tbody>
                            <tfoot>
                                <tr>
                                    <th><?php echo wpd_translate( 'total votes' ); ?></th>
                                    <?php
                                        $pielabel = ''; $piesum = '';
										foreach ( $votes_cout as $key => $value ) {
											if ($key != "post_views_count" && $key != "likes" ) {
	                                            ?><th id="total-<?php echo $key; ?>"><?php echo $value;  $pielabel.=$key.','; $piesum .= $value.','; ?></th><?php
											} }
                                        ?>
                                    <td></td>
                                </tr>
                            </tfoot>
							<?php   
							}     //    Ende Terminabstimmung oder Umfrage, nun Fusszeile
							?>
								
                        </table>
                        <?php
						// Chart Pie anzeigen zu den Ergebnissen
						$piesum = rtrim($piesum, ",");
						$pielabel = rtrim($pielabel, ",");
						if( class_exists( 'PB_ChartsCodes' ) && !empty($pielabel) ) {
							echo do_shortcode('[chartscodes accentcolor="1" title="' . wpd_translate( 'votes pie chart' ) . '" values="'.$piesum.'" labels="'.$pielabel.'" absolute="1"]');
						}	
					
					}
                    /* END password protected? */
                    ?>
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
