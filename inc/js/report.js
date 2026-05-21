jQuery( $ => {
    // console.log( 'File Manager Report JS Loaded...' );

    const trackingLoggedOutUsers = eriflReport.tracking_logged_out_users;
    const defaultCountsType = trackingLoggedOutUsers ? 'all' : 'logged_in';

    function setLoadingState( isLoading ) {
        var disabled = !! isLoading;
        $( '#erifl-report' ).toggleClass( 'is-loading', disabled );
        $( '#erifl-start-date, #erifl-end-date, #erifl-apply-date-filter, #erifl-quarter-range, #erifl-apply-range-filter, #erifl-clear-filters, #erifl-export-counts-btn button, #erifl-counts-type, #erifl-counts-filter-apply' ).prop( 'disabled', disabled );
    }

    function applyFilter( startDate, endDate, countsType ) {
        setLoadingState( true );

        $.post( eriflReport.ajaxUrl, {
            action: 'erifl_filter_reports',
            nonce: eriflReport.nonce,
            start_date: startDate,
            end_date: endDate,
            counts_type: countsType
        } )
        .done( function ( response ) {
            if ( response.success ) {
                $( '#erifl-report' ).html( response.data.html );
                $( '#erifl-export-start-date' ).val( startDate );
                $( '#erifl-export-end-date' ).val( endDate );
                $( '#erifl-export-counts-type' ).val( countsType );

                // Update last ran info
                var lastRanInfo = $( '#erifl-last-ran-info' );

                if ( lastRanInfo.length ) {
                    // Update existing spans or add them
                    if ( lastRanInfo.find( '.erifl-last-ran-datetime' ).length ) {
                        lastRanInfo.find( '.erifl-last-ran-datetime' ).text( response.data.last_ran_datetime );
                    } else {
                        lastRanInfo.html( lastRanInfo.html().replace(
                            /(Last ran on ).*( by )/,
                            '$1<span class="erifl-last-ran-datetime">' + response.data.last_ran_datetime + '</span>$2'
                        ) );
                    }

                    if ( lastRanInfo.find( '.erifl-last-ran-user' ).length ) {
                        lastRanInfo.find( '.erifl-last-ran-user' ).text( response.data.last_ran_user );
                    } else {
                        lastRanInfo.html( lastRanInfo.html().replace(
                            /( by ).*$/,
                            '$1<span class="erifl-last-ran-user">' + response.data.last_ran_user + '</span>'
                        ) );
                    }

                } else {
                    // If paragraph doesn't exist, create it
                    $( '#erifl-report' ).before(
                        '<p id="erifl-last-ran-info">' +
                            'Last ran on <span class="erifl-last-ran-datetime">' + response.data.last_ran_datetime + '</span> ' +
                            'by <span class="erifl-last-ran-user">' + response.data.last_ran_user + '</span>' +
                        '</p>'
                    );
                }
            }
        } )
        .always( function () {
            setLoadingState( false );
            handleCountsChange();
        } );
    }

    function handleCountsChange() {
        var countsType = $( '#erifl-counts-type' ).val();

        if ( countsType === 'all' && ! trackingLoggedOutUsers ) {
            // Clear and disable date filters
            $( '#erifl-start-date, #erifl-end-date, #erifl-apply-date-filter, #erifl-quarter-range, #erifl-apply-range-filter' ).val( '' ).prop( 'disabled', true );
            $( '#erifl-export-start-date, #erifl-export-end-date' ).val( '' );
        } else {
            // Enable date filters if logged_in
            $( '#erifl-start-date, #erifl-end-date, #erifl-apply-date-filter, #erifl-quarter-range, #erifl-apply-range-filter' ).prop( 'disabled', false );
        }
    }

    // Trigger on counts type change
    $( '#erifl-counts-type' ).on( 'change', handleCountsChange );

    // Apply counts filter button
    $( '#erifl-counts-filter-apply' ).on( 'click', function () {
        var countsType = $( '#erifl-counts-type' ).val();
        var startDate = $( '#erifl-start-date' ).val();
        var endDate = $( '#erifl-end-date' ).val();
        applyFilter( startDate, endDate, countsType );
    } );

    $( '#erifl-apply-date-filter' ).on( 'click', function () {
        const countsType = $( '#erifl-counts-type' ).val();
        const startDate = $( '#erifl-start-date' ).val();
        const endDate = $( '#erifl-end-date' ).val();

        if ( startDate && endDate ) {
            let matched = false;
            $( '#erifl-quarter-range option' ).each( function () {
                const val = $( this ).val();
                if ( val === `${startDate}|${endDate}` ) {
                    $( '#erifl-quarter-range' ).val( val );
                    matched = true;
                    return false;
                }
            } );

            if ( ! matched ) {
                $( '#erifl-quarter-range' ).val( '' );
            }
        } else {
            $( '#erifl-quarter-range' ).val( '' );
        }

        applyFilter( startDate, endDate, countsType );
    } );

    $( '#erifl-apply-range-filter' ).on( 'click', function () {
        const countsType = $( '#erifl-counts-type' ).val();
        const rangeVal = $( '#erifl-quarter-range' ).val();

        if ( rangeVal ) {
            const range = rangeVal.split( '|' );
            $( '#erifl-start-date' ).val( range[0] );
            $( '#erifl-end-date' ).val( range[1] );
            applyFilter( range[0], range[1], countsType );
        } else {
            // Placeholder selected
            $( '#erifl-start-date, #erifl-end-date' ).val( '' );
            applyFilter( '', '', countsType );
        }
    } );

    $( '#erifl-clear-filters' ).on( 'click', function() {
        $( '#erifl-start-date, #erifl-end-date, #erifl-quarter-range' ).val( '' );
        $( '#erifl-export-start-date, #erifl-export-end-date' ).val( '' );
        $( '#erifl-counts-type' ).val( defaultCountsType );
        $( '#erifl-export-counts-type' ).val( defaultCountsType );
        handleCountsChange();
        applyFilter( '', '', defaultCountsType );
    } );

} );
