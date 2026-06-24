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

    function calcMargin( purchase, price ) {
        const p  = parseFloat( purchase );
        const pr = parseFloat( price );
        if ( isNaN( p ) || isNaN( pr ) ) return null;
        if ( p < 0 || pr <= 0 || p > pr ) return null;
        return Math.round( ( 1 - p / pr ) * 100 * 100 ) / 100;
    }

    // -----------------------------------------------------------------------
    // Simple product
    // -----------------------------------------------------------------------

    function initSimple() {
        const purchaseInput = document.getElementById( 'bm_purchase_price' );
        const marginInput   = document.getElementById( 'bm_margin_percent' );
        const priceInput    = document.getElementById( '_regular_price' );

        if ( ! purchaseInput || ! marginInput || ! priceInput ) return;

        // Editing the purchase price keeps the existing sale price and
        // derives the margin from it.
        purchaseInput.addEventListener( 'input', function () {
            const margin = calcMargin( purchaseInput.value, priceInput.value );
            if ( margin !== null ) {
                marginInput.value = margin.toFixed( 2 );
            }
        } );

        // Editing the margin recalculates the sale price.
        marginInput.addEventListener( 'input', function () {
            const result = calcSalePrice( purchaseInput.value, marginInput.value );
            if ( result !== null ) {
                priceInput.value = result.toFixed( 2 );
            }
        } );

        // Pre-fill on load if values already exist (product already saved)
        if ( marginInput.value !== '' ) {
            const result = calcSalePrice( purchaseInput.value, marginInput.value );
            if ( result !== null ) {
                priceInput.value = result.toFixed( 2 );
            }
        } else if ( purchaseInput.value !== '' ) {
            const margin = calcMargin( purchaseInput.value, priceInput.value );
            if ( margin !== null ) {
                marginInput.value = margin.toFixed( 2 );
            }
        }
    }

    // -----------------------------------------------------------------------
    // Variations — delegate on the variations wrapper because rows are
    // added/removed dynamically by WooCommerce JS.
    // -----------------------------------------------------------------------

    function getVariationEls( row, loop ) {
        const purchaseEl = row.querySelector( '.bm-ppm-variation-purchase' );
        const marginEl   = row.querySelector( '.bm-ppm-variation-margin' );
        const priceEl    = row.querySelector( `input[name="variable_regular_price[${loop}]"]` );
        if ( ! purchaseEl || ! marginEl || ! priceEl ) return null;
        return { purchaseEl, marginEl, priceEl };
    }

    // Pre-fill on load: use whichever field already has a value.
    function updateVariationRow( row, loop ) {
        const els = getVariationEls( row, loop );
        if ( ! els ) return;
        const { purchaseEl, marginEl, priceEl } = els;
        if ( marginEl.value !== '' ) {
            const result = calcSalePrice( purchaseEl.value, marginEl.value );
            if ( result !== null ) priceEl.value = result.toFixed( 2 );
        } else if ( purchaseEl.value !== '' ) {
            const margin = calcMargin( purchaseEl.value, priceEl.value );
            if ( margin !== null ) marginEl.value = margin.toFixed( 2 );
        }
    }

    function initVariations() {
        const wrapper = document.getElementById( 'woocommerce-product-data' );
        if ( ! wrapper ) return;

        // Real-time update on input
        wrapper.addEventListener( 'input', function ( e ) {
            const target = e.target;
            const row = target.closest( '.woocommerce_variation' );
            if ( ! row ) return;
            const els = getVariationEls( row, target.dataset.loop );
            if ( ! els ) return;
            const { purchaseEl, marginEl, priceEl } = els;

            if ( target.classList.contains( 'bm-ppm-variation-purchase' ) ) {
                const margin = calcMargin( purchaseEl.value, priceEl.value );
                if ( margin !== null ) marginEl.value = margin.toFixed( 2 );
            } else if ( target.classList.contains( 'bm-ppm-variation-margin' ) ) {
                const result = calcSalePrice( purchaseEl.value, marginEl.value );
                if ( result !== null ) priceEl.value = result.toFixed( 2 );
            }
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
