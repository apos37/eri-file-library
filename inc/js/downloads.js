jQuery( $ => {
    // console.log( 'File Manager Downloads JS Loaded...' );

    // Remove all empty query string parameters so it can be copied and shared
    const url = new URL( window.location.href );
    const searchParams = new URLSearchParams( url.search );
    let modified = false;

    for ( const [ key, value ] of [ ...searchParams.entries() ] ) {
        if ( value.trim() === '' ) {
            searchParams.delete( key );
            modified = true;
        }
    }

    if ( modified ) {
        url.search = searchParams.toString();
        window.history.replaceState( null, '', url.toString() );
    } 
} )