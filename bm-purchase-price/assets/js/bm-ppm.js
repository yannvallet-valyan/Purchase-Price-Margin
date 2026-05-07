/**
 * BM Purchase Price & Margin — real-time price calculation
 *
 * Works for both simple products and every variation row.
 */
( function () {
    'use strict';

    /**
     * Calculate selling price from purchase price and margin.
     * Returns null when inputs are invalid.
     *
     * @param {string|number} purchase
     * @param {string|number} margin   0–99.99
     * @returns {number|null}
     */
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
    }

    // -----------------------------------------------------------------------
    // Variations — delegate on the variations wrapper because rows are
    // added/removed dynamically by WooCommerce JS.
    // -----------------------------------------------------------------------

    function initVariations() {
        const wrapper = document.getElementById( 'woocommerce-product-data' );
        if ( ! wrapper ) return;

        wrapper.addEventListener( 'input', function ( e ) {
            const target = e.target;
            const isRelevant =
                target.classList.contains( 'bm-ppm-variation-purchase' ) ||
                target.classList.contains( 'bm-ppm-variation-margin' );
            if ( ! isRelevant ) return;

            // Find sibling inputs within the same variation row
            const row = target.closest( '.woocommerce_variation' );
            if ( ! row ) return;

            const purchaseEl = row.querySelector( '.bm-ppm-variation-purchase' );
            const marginEl   = row.querySelector( '.bm-ppm-variation-margin' );
            // WooCommerce names the regular price field with the loop index
            const loop       = target.dataset.loop;
            const priceEl    = row.querySelector( `input[name="variable_regular_price[${loop}]"]` );

            if ( ! purchaseEl || ! marginEl || ! priceEl ) return;

            const result = calcSalePrice( purchaseEl.value, marginEl.value );
            if ( result !== null ) {
                priceEl.value = result.toFixed( 2 );
            }
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
