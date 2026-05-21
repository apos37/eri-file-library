jQuery( $ => {
    // console.log( 'File Manager Edit JS Loaded...' );
    
    const fileInput = $( "#files-upload" );
    const warning = $( "#special-characters-warning" );
    const titleInput = $( "#titlewrap input" );
    const titleLabel = $( "#title-prompt-text" );

    fileInput.on( 'change', function () {
        let fileName = fileInput.val();
        let sanitizedFileName = fileName.replace( /\s|\(|\)|\&/g, "_" );

        // Show or hide the warning
        warning.toggle( fileName !== sanitizedFileName );

        // Auto-fill title if empty
        if ( titleInput.val() === "" ) {
            titleLabel.addClass( "screen-reader-text" );
            let titleName = sanitizedFileName.split( "\\" ).pop(); // Get only the filename
            titleName = titleName.substring( 0, titleName.lastIndexOf( "." ) ) || titleName;

            // Replace _ and - with spaces, then collapse multiple spaces into one
            titleName = titleName.replace( /[_-]+/g, " " ).replace( /\s+/g, " " ).trim();

            // Capitalize first letters
            titleName = titleName.replace( /(^\w{1})|(\s+\w{1})/g, letter => letter.toUpperCase() );

            if ( fileName.toLowerCase().endsWith( "mp3" ) ) {
                titleName += " Audio Only";
            }

            titleInput.val( titleName );
        }
    } );


    /**
     * Copy to Clipboard
     */
    $( document ).on( 'click', '.click-to-copy', function( e ) {
        e.preventDefault();
        console.log( 'Clicked to copy' );
        var $this = $( this );
        var textToCopy = $this.text();
        var $copiedSpan = $this.next( '.click-to-copy-copied' );

        navigator.clipboard.writeText( textToCopy )
            .then( function( ) {
                $copiedSpan.fadeIn( 200 ).delay( 2000 ).fadeOut( 200 );
            } )
            .catch( function( err ) {
                console.error( 'Failed to copy text: ', err );
            } );
    } );

} );
