<?php

/**
 * The template for displaying all single wpdoodle
 *
 */
wp_enqueue_script( "jquery" );
get_header();
?>

<div id="primary" class="content-area">
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
                    //print_r($suggestions);
                    /* password protected? */
                    if ( !post_password_required() ) {
                        ?>
                        <table>
                            <caption><?php echo __wpd( 'Voting' ); ?></caption>
                            <thead>
                                <tr>
                                    <th><?php echo __wpd( 'User name' ); ?></th>
                                    <?php
                                        foreach ( $suggestions as $key => $value ) {
                                            ?><th><?php echo $key; ?></th><?php
                                        }
                                        ?>
                                    <th><?php echo __wpd( 'Manage vote' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $myname = $_COOKIE[ 'wpdoodlez-' . md5( AUTH_KEY . get_the_ID() ) ];
                                ?>
                                <tr id="wpdoodlez-form">
                                    <td><input type="text" 
                                               placeholder="<?php echo __wpd( 'Your name' ) ?>" 
                                               class="wpdoodlez-input"
                                               id="wpdoodlez-name"></td>
                                        <?php
                                        foreach ( $suggestions as $key => $value ) {
                                            ?><td>
                                            <label>
                                                <input type="checkbox" 
                                                       name="<?php echo $key; ?>" 
                                                       class="wpdoodlez-input">
                                        <?php echo $value[ 0 ]; ?></label>
                                        </td><?php
                                    }
                                    ?><td>
                                        <button id="wpdoodlez_vote"><?php echo __wpd( 'Vote!' ); ?>
                                        </button></td><?php ?>
                                </tr>
                                <?php
                                $votes = get_option( 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ), array() );
                                foreach ( $votes as $name => $vote ) {
                                    ?><tr id="<?php echo 'wpdoodlez_' . md5( AUTH_KEY . get_the_ID() ) . '-' . md5( $name ); ?>">
                                        <td><?php
                                            echo $name;
                                            ?></td>
                                        <?php
                                        foreach ( $suggestions as $key => $value ) {
                                            ?><td>
                                                    <?php
                                                    if ( $vote[ $key ] ) {
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
                                        ?><td><?php
                                            if ( current_user_can( 'delete_published_posts' ) ) {
                                                ?>
                                                <button class="wpdoodlez-delete" 
                                                        data-vote="<?php echo md5( $name ); ?>" 
                                                        data-realname="<?php echo $name; ?>"
                                                        ><?php echo __wpd( 'delete' ); ?></button><?php
                                                    }
                                                    if ( $myname == $name ) {
                                                        ?>
                                                <button class="wpdoodlez-edit" 
                                                        data-vote="<?php echo md5( $name ); ?>" 
                                                        data-realname="<?php echo $name; ?>"
                                                        ><?php echo __wpd( 'edit' ); ?></button><?php
                                            }
                                            ?></td>
                                    </tr><?php
                                }
                                ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th><?php echo __wpd( 'total votes' ); ?></th>
                                    <?php
                                        foreach ( $votes_cout as $key => $value ) {
                                            ?><th id="total-<?php echo $key; ?>"><?php echo $value; ?></th><?php }
                                        ?>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <?php
                    }
                    /* END password protected? */
                    ?>
                </div><!-- .entry-content -->

                <footer class="entry-footer">
    <?php edit_post_link( __wpd( 'Edit' ), '<span class="edit-link">', '</span>' ); ?>
                </footer><!-- .entry-footer -->
                <script>
                    var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
                    var wpdoodle = '<?php echo md5( AUTH_KEY . get_the_ID() ); ?>';
                    /* disable insert/edit form after a vote -> line with edit button */
                    if ( jQuery( 'button.wpdoodlez-edit' ).length > 0 ) {
                        jQuery( '#wpdoodlez-form' ).hide();
                    }
                    /* save vote */
                    jQuery( '#wpdoodlez_vote' ).on( 'click', function ( event ) {
                        var row = jQuery( event.target ).closest( 'tr' );
                        var answers = row.find( 'td > label > input' );
                        var name = row.find( '#wpdoodlez-name' ).val();

                        if ( name.match( /[a-zA-Z0-9]+/g ) ) {
                            jQuery.post(
                                ajaxurl,
                                {
                                    'action': 'wpdoodlez_save',
                                    'data': { 'wpdoodle': wpdoodle, 'name': name, 'vote': answers.serializeArray() }
                                },
                                function ( response ) {
                                    if ( response.save == true ) {
                                        document.location.reload( true );
                                    }
                                },
                                'json'
                                );
                        }
                    } );
                    /* delete vote */
                    jQuery( '.wpdoodlez-delete' ).on( 'click', function ( event ) {
                        var name = jQuery( this ).data( 'vote' );
                        var realname = jQuery( this ).data( 'realname' );
                        jQuery.post(
                            ajaxurl,
                            {
                                'action': 'wpdoodlez_delete',
                                'data': { 'wpdoodle': wpdoodle, 'name': realname }
                            },
                            function ( response ) {
                                if ( response.delete == true ) {
                                    jQuery( '#wpdoodlez_' + wpdoodle + '-' + name + ' td label' ).each( function ( i, e ) {
                                        if ( jQuery( e ).text() != '' ) {
                                            jQuery( '#total-' + jQuery( e ).data( 'key' ) ).text(
                                                ( jQuery( '#total-' + jQuery( e ).data( 'key' ) ).text() - 1 )
                                                );
                                        }
                                    } );
                                    jQuery( '#wpdoodlez_' + wpdoodle + '-' + name ).fadeOut();
                                }
                            },
                            'json'
                            );
                    } );
                    /* edit own vote */
                    jQuery( '.wpdoodlez-edit' ).on( 'click', function ( event ) {
                        var name = jQuery( this ).data( 'vote' );
                        jQuery( '#wpdoodlez-name' ).val(
                            jQuery( '#wpdoodlez_' + wpdoodle + '-' + name + ' td' ).first().text()
                            );
                        jQuery( '#wpdoodlez_' + wpdoodle + '-' + name + ' td label' ).each( function ( i, e ) {
                            if ( jQuery( e ).text() != '' ) {
                                jQuery( '[name="' + jQuery( e ).data( 'key' ) + '"]' ).attr( 'checked', 1 );
                            }
                        } );
                        jQuery( '#wpdoodlez_' + wpdoodle + '-' + name ).replaceWith( jQuery( '#wpdoodlez-form' ) );
                        jQuery( '#wpdoodlez-form' ).show();
                    } );
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