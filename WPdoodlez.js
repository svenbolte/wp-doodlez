jQuery(function(){
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
                wpdoodle_ajaxurl,
                {
                    'action': 'wpdoodlez_save',
                    'data': { 'wpdoodle': wpdoodle, 'name': name, 'vote': answers.serializeArray() }
                },
                function ( response ) {
                    if ( response.save == true ) {
                        document.location.reload( true );
                    }
                    else {
                        window.alert(response.msg);
                    }
                },
                'json'
                );
        }
    } );
	
    /* save poll vote */
    jQuery( '#wpdoodlez_poll' ).on( 'click', function ( event ) {
        var xtable = jQuery( event.target ).closest( 'table' );
        var answers = xtable.find( 'td > label > input' );
        var name = xtable.find( '#wpdoodlez-name' ).val();
		//if ( name.match( /[a-zA-Z0-9]+/g ) ) {
            jQuery.post(
                wpdoodle_ajaxurl,
                {
                    'action': 'wpdoodlez_save_poll',
                    'data': { 'wpdoodle': wpdoodle, 'name': name, 'vote': answers.serializeArray() }
                },
                function ( response ) {
                    if ( response.save == true ) {
                        document.location.reload( true );
                    }
                    else {
                        window.alert(response.msg);
                    }
                },
                'json'
                );
		//}
	} );
	
	
    /* delete vote */
    jQuery( '.wpdoodlez-delete' ).on( 'click', function ( event ) {
        var name = jQuery( this ).data( 'vote' );
        var realname = jQuery( this ).data( 'realname' );
        jQuery.post(
            wpdoodle_ajaxurl,
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
});