/**
 * BM Purchase Price & Margin — real-time price calculation
 *
 * Works for both simple products and every variation row.
 */
( function () {
    'use strict';

    function calcSalePrice( purchase, margin ) {
        const p = parseFloat( purchase );
        const m = parseFloat( margin );
        if ( isNaN( p ) || isNaN( m ) ) return null;
        if ( p <= 0 || m < 0 || m >= 100 ) return null;
        return Math.round( ( p / ( 1 - m / 100 ) ) * 100 ) / 100;
    }

    // -----------------------------------------------------------------------
    // Simple product
    // -----------------------------------------------------------------------

    function initSimple() {
        const purchaseInput = document.getElementById( 'bm_purchase_price' );
        const marginInput   = document.getElementById( 'bm_margin_percent' );
        const priceInput    = document.getElementById( '_regular_price' );

        if ( ! purchaseInput || ! marginInput || ! priceInput ) return;

        function update() {
            const result = calcSalePrice( purchaseInput.value, marginInput.value );
            if ( result !== null ) {
                priceInput.value = result.toFixed( 2 );
            }
        }

        purchaseInput.addEventListener( 'input', update );
        marginInput.addEventListener( 'input', update );

        // Pre-fill on load if values already exist (product already saved)
        update();
    }

    // -----------------------------------------------------------------------
    // Variations — delegate on the variations wrapper because rows are
    // added/removed dynamically by WooCommerce JS.
    // -----------------------------------------------------------------------

    function updateVariationRow( row, loop ) {
        const purchaseEl = row.querySelector( '.bm-ppm-variation-purchase' );
        const marginEl   = row.querySelector( '.bm-ppm-variation-margin' );
        const priceEl    = row.querySelector( `input[name="variable_regular_price[${loop}]"]` );
        if ( ! purchaseEl || ! marginEl || ! priceEl ) return;
        const result = calcSalePrice( purchaseEl.value, marginEl.value );
        if ( result !== null ) {
            priceEl.value = result.toFixed( 2 );
        }
    }

    function initVariations() {
        const wrapper = document.getElementById( 'woocommerce-product-data' );
        if ( ! wrapper ) return;

        // Real-time update on input
        wrapper.addEventListener( 'input', function ( e ) {
            const target = e.target;
            if (
                ! target.classList.contains( 'bm-ppm-variation-purchase' ) &&
                ! target.classList.contains( 'bm-ppm-variation-margin' )
            ) return;
            const row = target.closest( '.woocommerce_variation' );
            if ( ! row ) return;
            updateVariationRow( row, target.dataset.loop );
        } );

        // Pre-fill on load for all variation rows already present in the DOM
        document.querySelectorAll( '.woocommerce_variation' ).forEach( function ( row ) {
            const purchaseEl = row.querySelector( '.bm-ppm-variation-purchase' );
            if ( ! purchaseEl ) return;
            updateVariationRow( row, purchaseEl.dataset.loop );
        } );

        // Pre-fill when WooCommerce loads variation rows dynamically
        jQuery( document ).on( 'woocommerce_variations_loaded', function () {
            document.querySelectorAll( '.woocommerce_variation' ).forEach( function ( row ) {
                const purchaseEl = row.querySelector( '.bm-ppm-variation-purchase' );
                if ( ! purchaseEl ) return;
                updateVariationRow( row, purchaseEl.dataset.loop );
            } );
        } );
    }

    // -----------------------------------------------------------------------
    // Boot
    // -----------------------------------------------------------------------

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', function () {
            initSimple();
            initVariations();
        } );
    } else {
        initSimple();
        initVariations();
    }
} )();
