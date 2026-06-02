/**
 * BM Purchase Price & Margin — calcul bidirectionnel en temps réel
 *
 * Règles :
 *  - prix_achat + marge    → tarif_régulier = achat / (1 - marge/100)
 *  - prix_achat + tarif    → marge          = (1 - achat/tarif) * 100
 * Au chargement, le champ manquant est déduit si les deux autres existent.
 */
( function () {
    'use strict';

    function calcSalePrice( purchase, margin ) {
        const p = parseFloat( purchase );
        const m = parseFloat( margin );
        if ( isNaN( p ) || isNaN( m ) || p <= 0 || m < 0 || m >= 100 ) return null;
        return Math.round( ( p / ( 1 - m / 100 ) ) * 100 ) / 100;
    }

    function calcMargin( purchase, salePrice ) {
        const p = parseFloat( purchase );
        const s = parseFloat( salePrice );
        if ( isNaN( p ) || isNaN( s ) || p <= 0 || s <= 0 || p >= s ) return null;
        return Math.round( ( 1 - p / s ) * 10000 ) / 100; // 2 décimales
    }

    // -----------------------------------------------------------------------
    // Produit simple
    // -----------------------------------------------------------------------

    function initSimple() {
        const purchaseInput = document.getElementById( 'bm_purchase_price' );
        const marginInput   = document.getElementById( 'bm_margin_percent' );
        const priceInput    = document.getElementById( '_regular_price' );

        if ( ! purchaseInput || ! marginInput || ! priceInput ) return;

        // Marge ou prix de vente changé → recalcule l'autre
        function onMarginChange() {
            const result = calcSalePrice( purchaseInput.value, marginInput.value );
            if ( result !== null ) priceInput.value = result.toFixed( 2 );
        }

        function onPriceChange() {
            const result = calcMargin( purchaseInput.value, priceInput.value );
            if ( result !== null ) marginInput.value = result.toFixed( 2 );
        }

        function onPurchaseChange() {
            // Priorité : si la marge est renseignée, recalcule le prix
            if ( marginInput.value !== '' ) {
                onMarginChange();
            } else if ( priceInput.value !== '' ) {
                // Sinon, si le prix de vente est renseigné, recalcule la marge
                onPriceChange();
            }
        }

        marginInput.addEventListener( 'input', onMarginChange );
        priceInput.addEventListener( 'input', onPriceChange );
        purchaseInput.addEventListener( 'input', onPurchaseChange );

        // Pré-calcul au chargement : complète le champ manquant
        if ( purchaseInput.value !== '' ) {
            if ( marginInput.value !== '' && priceInput.value === '' ) {
                onMarginChange();
            } else if ( priceInput.value !== '' && marginInput.value === '' ) {
                onPriceChange();
            }
        }
    }

    // -----------------------------------------------------------------------
    // Variations
    // -----------------------------------------------------------------------

    function updateVariationRow( row, loop ) {
        const purchaseEl = row.querySelector( '.bm-ppm-variation-purchase' );
        const marginEl   = row.querySelector( '.bm-ppm-variation-margin' );
        const priceEl    = row.querySelector( `input[name="variable_regular_price[${loop}]"]` );
        if ( ! purchaseEl || ! marginEl || ! priceEl ) return;

        if ( purchaseEl.value !== '' ) {
            if ( marginEl.value !== '' && priceEl.value === '' ) {
                const r = calcSalePrice( purchaseEl.value, marginEl.value );
                if ( r !== null ) priceEl.value = r.toFixed( 2 );
            } else if ( priceEl.value !== '' && marginEl.value === '' ) {
                const r = calcMargin( purchaseEl.value, priceEl.value );
                if ( r !== null ) marginEl.value = r.toFixed( 2 );
            }
        }
    }

    function initVariations() {
        const wrapper = document.getElementById( 'woocommerce-product-data' );
        if ( ! wrapper ) return;

        wrapper.addEventListener( 'input', function ( e ) {
            const target = e.target;
            const row    = target.closest( '.woocommerce_variation' );
            if ( ! row ) return;

            const purchaseEl = row.querySelector( '.bm-ppm-variation-purchase' );
            const marginEl   = row.querySelector( '.bm-ppm-variation-margin' );
            if ( ! purchaseEl || ! marginEl ) return;

            const loop    = purchaseEl.dataset.loop;
            const priceEl = row.querySelector( `input[name="variable_regular_price[${loop}]"]` );
            if ( ! priceEl ) return;

            if ( target.classList.contains( 'bm-ppm-variation-margin' ) ) {
                // Marge modifiée → recalcule prix de vente
                const r = calcSalePrice( purchaseEl.value, marginEl.value );
                if ( r !== null ) priceEl.value = r.toFixed( 2 );

            } else if ( target.name === `variable_regular_price[${loop}]` ) {
                // Prix de vente modifié → recalcule marge
                const r = calcMargin( purchaseEl.value, priceEl.value );
                if ( r !== null ) marginEl.value = r.toFixed( 2 );

            } else if ( target.classList.contains( 'bm-ppm-variation-purchase' ) ) {
                // Prix d'achat modifié
                if ( marginEl.value !== '' ) {
                    const r = calcSalePrice( purchaseEl.value, marginEl.value );
                    if ( r !== null ) priceEl.value = r.toFixed( 2 );
                } else if ( priceEl.value !== '' ) {
                    const r = calcMargin( purchaseEl.value, priceEl.value );
                    if ( r !== null ) marginEl.value = r.toFixed( 2 );
                }
            }
        } );

        function prefillAll() {
            document.querySelectorAll( '.woocommerce_variation' ).forEach( function ( row ) {
                const purchaseEl = row.querySelector( '.bm-ppm-variation-purchase' );
                if ( purchaseEl ) updateVariationRow( row, purchaseEl.dataset.loop );
            } );
        }

        prefillAll();
        jQuery( document ).on( 'woocommerce_variations_loaded', prefillAll );
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
