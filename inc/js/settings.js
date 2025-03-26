jQuery( $ => {
    // console.log( 'File Manager Settings JS Loaded...' );

    // Clicking on the example link
    $( '#example' ). on( 'click', function( e ) {
        alert( erifl_settings.example_alert );
    } );;
    
    // Update the pre title
    $( '#erifl_pre_title' ).on( 'keyup change', function() {
        $( '#example-pre' ).html( this.value );
    } );

    // Update the post title
    $( '#erifl_post_title' ).on( 'keyup change', function() {
        $( '#example-post' ).html( this.value );
    } );

    // Toggle the formats
    $( '#erifl_btn_hide_format' ).change( function() {
        $( '#example-format' ).toggle();
    } );

    // Change the shortcode type param
    $( '#erifl_admin_param' ).change( function() {
        var type = $( this ).val();
        $( '#shortcode-type' ).text( type );
    } );

    // Change the folder path folder name
    $( '#erifl_folder' ).on( 'keyup change', function() {
        var name = $( this ).val();
        $( '#erifl-example-folder-name' ).text( name );
    } );

} )