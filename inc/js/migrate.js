jQuery( $ => {
    // console.log( 'File Manager Migrate JS Loaded...' );

    const defaultChunkSize = ERIFL_Migrate.chunk_size || 10;

    function runChunk( action, batch, progressEl, buttonEl, offset = 0, totalProcessed = 0, totalInserted = 0 ) {
        $( buttonEl ).prop( 'disabled', true );

        const perRequest = batch > 0 ? batch : defaultChunkSize;

        $.post( ERIFL_Migrate.ajax_url, {
            action: action,
            batch: perRequest,
            offset: offset,
            nonce: ERIFL_Migrate.nonce
        }, function( response ) {

            if ( response.success ) {

                const termsProcessed = response.data.terms_processed || 0;
                const rowsInserted   = response.data.rows_inserted || 0;

                totalProcessed += termsProcessed;
                totalInserted  += rowsInserted;

                $( progressEl ).html(
                    'Processed ' + totalProcessed + ' terms. Inserted ' + totalInserted + ' records.'
                );

                if ( termsProcessed > 0 ) {
                    runChunk(
                        action,
                        batch,
                        progressEl,
                        buttonEl,
                        offset + termsProcessed,
                        totalProcessed,
                        totalInserted
                    );
                } else {
                    $( progressEl ).html(
                        'All done. Terms processed: ' + totalProcessed + '. Records inserted: ' + totalInserted
                    );
                    $( buttonEl ).prop( 'disabled', false );
                }

            } else {
                $( progressEl ).html( 'Error: ' + response.data );
                $( buttonEl ).prop( 'disabled', false );
            }

        } ).fail( function( jqXHR, textStatus ) {
            $( progressEl ).html( 'AJAX failed: ' + textStatus );
            $( buttonEl ).prop( 'disabled', false );
        } );
    }

    $( '#erifl-run-downloads' ).on( 'click', function() {
        $( '#erifl-downloads-progress' ).text( 'Starting…' );
        runChunk( 'erifl_migrate_downloads', 0, '#erifl-downloads-progress', this );
    } );

    function runPostMigration( batch, progressEl, buttonEl ) {
        $( buttonEl ).prop( 'disabled', true );
        $( progressEl ).text( 'Starting…' );

        $.post( ERIFL_Migrate.ajax_url, {
            action: 'erifl_migrate_posts',
            batch: batch,
            nonce: ERIFL_Migrate.nonce
        }, function( response ) {

            if ( response.success ) {
                $( progressEl ).html(
                    'Migration complete. Posts migrated: ' + response.data.migrated
                );
            } else {
                $( progressEl ).html( 'Error: ' + response.data );
            }

            $( buttonEl ).prop( 'disabled', false );

        } ).fail( function( jqXHR, textStatus ) {
            $( progressEl ).html( 'AJAX failed: ' + textStatus );
            $( buttonEl ).prop( 'disabled', false );
        } );
    }

    $( '#erifl-run-posts' ).on( 'click', function() {
        const batch = parseInt( $( '#erifl-posts-batch' ).val(), 10 ) || 0;
        runPostMigration( batch, '#erifl-posts-progress', this );
    } );

} );
