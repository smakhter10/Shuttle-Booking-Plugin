/**
 * Napoleon Shuttle Booking — Admin JavaScript
 */
/* global nsbAdmin, jQuery */

(function ( $ ) {
    'use strict';

    // -----------------------------------------------------------------------
    // Delete confirmation
    // -----------------------------------------------------------------------
    $( document ).on( 'click', '.nsb-delete-btn', function ( e ) {
        var message = $( this ).data( 'confirm' ) || nsbAdmin.confirmDelete;
        if ( ! window.confirm( message ) ) {
            e.preventDefault();
        }
    } );

    // -----------------------------------------------------------------------
    // Deposit type toggle — show/hide deposit amount row
    // -----------------------------------------------------------------------
    var $depositType   = $( '#nsb_deposit_type' );
    var $depositRow    = $( '#nsb-deposit-amount-row' );
    var $depositSuffix = $( '#nsb-deposit-suffix' );
    var currencySymbol = typeof nsbAdmin !== 'undefined' && nsbAdmin.currencySymbol
        ? nsbAdmin.currencySymbol
        : '$';

    function updateDepositRow() {
        var type = $depositType.val();

        if ( type === 'none' ) {
            $depositRow.hide();
        } else {
            $depositRow.show();
            if ( type === 'percentage' ) {
                $depositSuffix.text( '(%)' );
            } else {
                $depositSuffix.text( '(' + currencySymbol + ')' );
            }
        }
    }

    // Run on page load and on change.
    if ( $depositType.length ) {
        updateDepositRow();
        $depositType.on( 'change', updateDepositRow );
    }


    // -----------------------------------------------------------------------
    // Package image uploader
    // -----------------------------------------------------------------------
    var nsbMediaFrame;

    $( document ).on( 'click', '#nsb-select-package-image', function ( e ) {
        e.preventDefault();

        if ( nsbMediaFrame ) {
            nsbMediaFrame.open();
            return;
        }

        nsbMediaFrame = wp.media( {
            title: 'Select Package Image',
            button: { text: 'Use this image' },
            multiple: false
        } );

        nsbMediaFrame.on( 'select', function () {
            var attachment = nsbMediaFrame.state().get( 'selection' ).first().toJSON();
            var imageUrl = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

            $( '#nsb_package_image_id' ).val( attachment.id );
            $( '#nsb-package-image-preview' )
                .addClass( 'has-image' )
                .html( '<img src="' + imageUrl + '" alt="Package image preview">' );
            $( '#nsb-remove-package-image' ).show();
        } );

        nsbMediaFrame.open();
    } );

    $( document ).on( 'click', '#nsb-remove-package-image', function ( e ) {
        e.preventDefault();
        $( '#nsb_package_image_id' ).val( '' );
        $( '#nsb-package-image-preview' )
            .removeClass( 'has-image' )
            .html( '<span class="dashicons dashicons-format-image"></span><em>No image selected</em>' );
        $( this ).hide();
    } );

    // -----------------------------------------------------------------------
    // Auto-dismiss "is-dismissible" notices (optional quality-of-life)
    // -----------------------------------------------------------------------
    setTimeout( function () {
        $( '.nsb-auto-dismiss' ).fadeOut( 600, function () {
            $( this ).remove();
        } );
    }, 5000 );

} )( jQuery );

(function ( $ ) {
    'use strict';

    // -----------------------------------------------------------------------
    // Bookings bulk select and action helpers
    // -----------------------------------------------------------------------
    $( document ).on( 'change', '.nsb-select-all', function () {
        var checked = $( this ).is( ':checked' );
        $( '.nsb-booking-checkbox' ).prop( 'checked', checked );
        $( '.nsb-select-all' ).prop( 'checked', checked );
    } );

    $( document ).on( 'change', '.nsb-booking-checkbox', function () {
        var total = $( '.nsb-booking-checkbox' ).length;
        var selected = $( '.nsb-booking-checkbox:checked' ).length;
        $( '.nsb-select-all' ).prop( 'checked', total > 0 && total === selected );
    } );

    $( document ).on( 'click', '.nsb-bulk-apply', function ( e ) {
        var action = $( '#nsb-bulk-action-selector-top' ).val();
        var selected = $( '.nsb-booking-checkbox:checked' ).length;

        if ( ! action || action === '-1' ) {
            window.alert( 'Please select a bulk action.' );
            e.preventDefault();
            return;
        }

        if ( selected < 1 ) {
            window.alert( 'Please select at least one booking.' );
            e.preventDefault();
            return;
        }

        if ( action === 'delete' ) {
            var message = $( this ).data( 'confirm-delete' ) || nsbAdmin.confirmDelete;
            if ( ! window.confirm( message ) ) {
                e.preventDefault();
            }
        }
    } );

    $( document ).on( 'click', '.nsb-bulk-apply-bottom', function () {
        $( '#nsb-bulk-action-selector-top' ).val( $( '#nsb-bulk-action-selector-bottom' ).val() );
        $( '.nsb-bulk-apply' ).trigger( 'click' );
    } );

} )( jQuery );

(function ( $ ) {
    'use strict';

    // WordPress color picker for design settings.
    $( function () {
        if ( $.fn.wpColorPicker ) {
            $( '.nsb-color-field' ).wpColorPicker();
        }
    } );

} )( jQuery );
