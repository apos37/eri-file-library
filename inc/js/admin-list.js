jQuery( $ => {
    // console.log( 'Requirements WP List Table JS Loaded...' );

    /**
     * BULK EDIT
     */

    // Create a copy of the WP inline edit post function
    const wp_inline_edit = inlineEditPost.edit;

    // Overwrite the function with our own code
    inlineEditPost.edit = function( post_id ) {
        
        // Call the original WP edit function
        wp_inline_edit.apply( this, arguments );

        // Get the post ID
        if ( typeof ( post_id ) == 'object' ) {
            post_id = parseInt( this.getId( post_id ) );
        }
        if ( post_id > 0 ) {
            // Define the edit row
            const edit_row = $( '#edit-' + post_id );
            const post_row = $( '#post-' + post_id );
            

            // Get the current roles from the united column
            const selectedRolesSpan = $( '.column-requirements .selected-roles', post_row );
            var selectedRoles = selectedRolesSpan.data( 'roles' );
            if ( selectedRoles ) {
                selectedRoles = selectedRoles.split( ',' ).map( role => role.trim() );
            }
            
            // Populate the checkboxes in quick edit
            if ( selectedRoles.length > 0 ) {
                selectedRoles.forEach( role => {
                    $( `input[name="required_roles[]"][value="${ role }"]`, edit_row ).prop( 'checked', true );
                } );
            }

            // Get the current meta key
            const selectedMetaKeySpan = $( '.column-requirements .meta-key code', post_row );
            var metaKey = selectedMetaKeySpan.text();
            console.log( metaKey );

            // Populate the text field in quick edit
            if ( metaKey ) {
                console.log( 'exists' );
                $( `input[name="required_meta_key"]`, edit_row ).val( metaKey );
            }
        }
    };

    // Save the bulk edit fields
    $( '#bulk_edit' ).on( 'click', function ( event ) {
        const bulk_row = $( '#bulk-edit' );

        // Get the selected post ids that are being edited.
        var post_ids = [];
        var requirementsRoles = [];
        var requiredMetaKey = '';

        // Get post IDs from the bulk_edit ID. .ntdelbutton is the class that holds the post ID.
        bulk_row.find( '#bulk-titles-list .ntdelbutton' ).each( function () {
            post_ids.push( $( this ).attr( 'id' ).replace( /^(_)/i, '' ) );
        } );

        // Collect selected roles from checkboxes
        $( 'input[name="required_roles[]"]:checked', bulk_row ).each( function() {
            requirementsRoles.push( $( this ).val() );
        } );

        // Collect the meta key from the text box
        requiredMetaKey = $( 'input[name="required_meta_key"]', bulk_row ).val();

        // Convert all post_ids to integer
        post_ids.map( function ( value, index, array ) {
            array[ index ] = parseInt( value );
        } );

        // Save the data
        $.ajax( {
            url: ajaxurl,
            type: 'POST',
            async: false,
            cache: false,
            data: {
                action: 'erifl_save_bulk_edit',
                post_ids: post_ids,
                required_roles: requirementsRoles,
                required_meta_key: requiredMetaKey,
                nonce: erifl_quick_bulk_edit.nonce
            }
        } );
    } );


    /**
     * Copy to Clipboard
     */
    $( document ).on( 'click', '.click-to-copy', function( e ) {
        e.preventDefault();
        var $this = $( this );
        var shortcode = $this.text();
        var $copiedSpan = $this.siblings( '.click-to-copy-copied' );
    
        navigator.clipboard.writeText( shortcode )
            .then( function() {
                $copiedSpan.fadeIn( 200 ).delay( 2000 ).fadeOut( 200 );
            } )
            .catch( function( err ) {
                console.error( 'Failed to copy text: ', err );
            } );
    } );

} );
