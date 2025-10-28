jQuery( $ => {
    // console.log( 'ERI File Library JS Loaded...' );

    $( 'a.erifl-file, div.erifl-file a' ).on( 'click', function( e ) {
        e.preventDefault();

        // Gather data
        let fileID, type, fileElement;

        if ( $( this ).is( 'a.erifl-file' ) ) {
            fileID = $( this ).data( 'file' );
            type = $( this ).data( 'type' );
            fileElement = $( this );
        } else {
            fileID = $( this ).closest( '.erifl-file' ).data( 'file' );
            type = $( this ).closest( '.erifl-file' ).data( 'type' );
            fileElement = $( this ).closest( '.erifl-file' );
        }

        let nonce = eri_file_library.nonce;
        let href = $( this ).attr( 'href' );

        // Track it
        $.ajax( {
            type: 'post',
            dataType: 'json',
            url: eri_file_library.ajaxurl,
            data: { 
                action: 'erifl_file',
                fileID: fileID,
                nonce: nonce,
                actionName: eri_file_library.action
            },
            success: function( response ) {
                if ( response.success && response.data.type ) {
                    var redirect = response.data.url;
                    // console.log( 'Direct link: ' + redirect );

                    if ( href && href !== '#' ) {
                        window.open( href );
                    } else {
                        window.open( redirect );
                    }
                    
                } else {
                    console.log( 'Failed to download: ' + JSON.stringify( response ) );
                    if ( href && href !== '#' ) {
                        window.open( href );
                    } else {
                        alert( response.data.message || 'An unknown error occurred.' );
                    }
                }
            },
            error: function( jqXHR, textStatus, errorThrown ) {
                console.log( 'AJAX error:', textStatus, errorThrown, jqXHR );

                let message = 'An unexpected error occurred. Please try again.';

                if ( jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message ) {
                    message = jqXHR.responseJSON.data.message;
                } else if ( jqXHR.responseText ) {
                    // Fallback: try to parse JSON from responseText
                    try {
                        let resp = JSON.parse( jqXHR.responseText );
                        if ( resp.data && resp.data.message ) {
                            message = resp.data.message;
                        }
                    } catch ( e ) {
                        message = jqXHR.responseText;
                    }
                }

                if ( href && href !== '#' ) {
                    window.open( href );
                } else {
                    alert( message );
                }
            },
            complete: function() {

                // Increase count in the UI
                let countElement;

                let typesToIncrease = [ 'link', 'button', 'full', 'post' ];
                if  ( !typesToIncrease.includes( type ) ) {
                    return;
                }

                if ( type === 'post' ) {
                    countElement = fileElement.find( '.erifl-count' );
                    if ( countElement && countElement.length ) {
                        let countText = countElement.text();
                        let countMatch = countText.match( /(\d+)/ );
                        if ( countMatch && countMatch[ 0 ] ) {
                            let newCount = parseInt( countMatch[ 0 ], 10 ) + 1;
                            countElement.text( countText.replace( countMatch[ 0 ], newCount ) );
                        }
                    }
                } else {
                    countElement = fileElement.closest( 'li' ).find( '.erifl-downloads strong' );
                    if ( countElement && countElement.length ) {
                        let currentCount = parseInt( countElement.text(), 10 ) || 0;
                        countElement.text( currentCount + 1 );
                    }
                }
            }
        } );
    } );
} );
