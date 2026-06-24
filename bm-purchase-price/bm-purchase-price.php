<?php
/**
 * Plugin Name: BM Purchase Price & Margin
 * Plugin URI:  https://example.com/bm-purchase-price
 * Description: Gère les prix d'achat, les marges et les prix de vente pour les produits simples et les variations WooCommerce.
 * Version:     1.2.0
 * Author:      BM
 * Text Domain: bm-ppm
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

// Ne rien faire si WooCommerce n'est pas actif.
add_action( 'plugins_loaded', 'bm_ppm_init' );
function bm_ppm_init(): void {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }
    bm_ppm_register_hooks();
}

function bm_ppm_register_hooks(): void {
    // Admin — scripts et CSS (un seul hook, une seule vérification de page)
    add_action( 'admin_enqueue_scripts', 'bm_ppm_enqueue_assets' );

    // Produit simple — affichage et sauvegarde
    add_action( 'woocommerce_product_options_general_product_data', 'bm_ppm_simple_fields' );
    add_action( 'woocommerce_process_product_meta', 'bm_ppm_save_simple_fields' );

    // Variations — affichage et sauvegarde
    add_action( 'woocommerce_variation_options_pricing', 'bm_ppm_variation_fields', 10, 3 );
    add_action( 'woocommerce_save_product_variation', 'bm_ppm_save_variation_fields', 10, 2 );

    // Liste des produits — colonnes
    add_filter( 'manage_edit-product_columns', 'bm_ppm_add_columns' );
    add_action( 'manage_product_posts_custom_column', 'bm_ppm_render_columns', 10, 2 );
}

// ---------------------------------------------------------------------------
// Assets — JS + CSS inline, uniquement sur les pages produit
// ---------------------------------------------------------------------------

function bm_ppm_enqueue_assets( string $hook ): void {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'product' ) {
        return;
    }

    // CSS liste produits
    if ( $screen->id === 'edit-product' ) {
        wp_register_style( 'bm-ppm', false );
        wp_enqueue_style( 'bm-ppm' );
        wp_add_inline_style( 'bm-ppm', '
            .column-bm_purchase_price,
            .column-bm_margin_percent { white-space: nowrap; width: 90px; }
        ' );
        return;
    }

    // CSS + JS page édition produit
    if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        wp_enqueue_script(
            'bm-ppm',
            plugin_dir_url( __FILE__ ) . 'assets/js/bm-ppm.js',
            [ 'jquery' ],
            '1.2.0',
            true
        );
        wp_add_inline_style( 'woocommerce_admin_styles', '
            .bm-ppm-variation-group { display: flex; gap: 12px; flex-wrap: wrap;
                clear: both; padding: 6px 9px; border-top: 1px solid #eee; margin-top: 4px; }
            .bm-ppm-variation-group .form-row { margin: 0; flex: 1 1 140px; }
            .bm-ppm-variation-group label { display: block; font-weight: 600;
                margin-bottom: 3px; font-size: 12px; }
            .bm-ppm-variation-group input[type="number"] { width: 100%; }
        ' );
    }
}

// ---------------------------------------------------------------------------
// Produit simple — champs onglet Général
// ---------------------------------------------------------------------------

function bm_ppm_simple_fields(): void {
    global $post;
    $purchase = get_post_meta( $post->ID, '_bm_purchase_price', true );
    $margin   = get_post_meta( $post->ID, '_bm_margin_percent', true );
    ?>
    <div class="options_group bm-ppm-group">
        <p class="form-field bm_purchase_price_field">
            <label for="bm_purchase_price"><?php esc_html_e( "Prix d'achat (€)", 'bm-ppm' ); ?></label>
            <input
                type="number"
                id="bm_purchase_price"
                name="bm_purchase_price"
                class="short bm-ppm-purchase"
                step="0.01"
                min="0"
                autocomplete="off"
                value="<?php echo esc_attr( $purchase ); ?>"
            />
        </p>
        <p class="form-field bm_margin_percent_field">
            <label for="bm_margin_percent"><?php esc_html_e( 'Marge (%)', 'bm-ppm' ); ?></label>
            <input
                type="number"
                id="bm_margin_percent"
                name="bm_margin_percent"
                class="short bm-ppm-margin"
                step="0.01"
                min="0"
                max="99.99"
                autocomplete="off"
                value="<?php echo esc_attr( $margin ); ?>"
            />
        </p>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Produit simple — sauvegarde
// ---------------------------------------------------------------------------

function bm_ppm_save_simple_fields( int $post_id ): void {
    // Les produits variables soumettent à la fois le champ scalaire caché
    // (bm_purchase_price vide) et les champs tableau des variations
    // (bm_purchase_price[N]). PHP résout le conflit en tableau ; (float)array
    // vaut 1.0 et corromprait _regular_price. On ignore les produits variables.
    $product_type = isset( $_POST['product-type'] ) ? sanitize_key( $_POST['product-type'] ) : '';
    if ( $product_type === 'variable' ) {
        return;
    }

    $raw_purchase = $_POST['bm_purchase_price'] ?? '';
    $raw_margin   = $_POST['bm_margin_percent'] ?? '';

    if ( is_array( $raw_purchase ) || is_array( $raw_margin ) ) {
        return;
    }

    $purchase = ( $raw_purchase !== '' ) ? (float) $raw_purchase : null;
    $margin   = ( $raw_margin !== '' )   ? (float) $raw_margin   : null;

    if ( $purchase !== null ) {
        update_post_meta( $post_id, '_bm_purchase_price', $purchase );
    }
    if ( $margin !== null ) {
        update_post_meta( $post_id, '_bm_margin_percent', $margin );
    }

    if (
        $purchase !== null && $margin !== null &&
        $purchase > 0 && $margin >= 0 && $margin < 100
    ) {
        $sale_price = round( $purchase / ( 1 - $margin / 100 ), 2 );
        update_post_meta( $post_id, '_regular_price', $sale_price );
        update_post_meta( $post_id, '_price', $sale_price );
    }
}

// ---------------------------------------------------------------------------
// Variations — champs
// ---------------------------------------------------------------------------

function bm_ppm_variation_fields( int $loop, array $variation_data, WP_Post $variation ): void {
    $purchase = get_post_meta( $variation->ID, '_bm_purchase_price', true );
    $margin   = get_post_meta( $variation->ID, '_bm_margin_percent', true );
    ?>
    <div class="bm-ppm-variation-group">
        <div class="form-row">
            <label><?php esc_html_e( "Prix d'achat (€)", 'bm-ppm' ); ?></label>
            <input
                type="number"
                name="bm_purchase_price[<?php echo esc_attr( $loop ); ?>]"
                class="bm-ppm-purchase bm-ppm-variation-purchase"
                step="0.01"
                min="0"
                autocomplete="off"
                value="<?php echo esc_attr( $purchase ); ?>"
                data-loop="<?php echo esc_attr( $loop ); ?>"
            />
        </div>
        <div class="form-row">
            <label><?php esc_html_e( 'Marge (%)', 'bm-ppm' ); ?></label>
            <input
                type="number"
                name="bm_margin_percent[<?php echo esc_attr( $loop ); ?>]"
                class="bm-ppm-margin bm-ppm-variation-margin"
                step="0.01"
                min="0"
                max="99.99"
                autocomplete="off"
                value="<?php echo esc_attr( $margin ); ?>"
                data-loop="<?php echo esc_attr( $loop ); ?>"
            />
        </div>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Variations — sauvegarde
// ---------------------------------------------------------------------------

function bm_ppm_save_variation_fields( int $variation_id, int $loop ): void {
    $purchase = ( isset( $_POST['bm_purchase_price'][ $loop ] ) && $_POST['bm_purchase_price'][ $loop ] !== '' )
        ? (float) $_POST['bm_purchase_price'][ $loop ] : null;
    $margin = ( isset( $_POST['bm_margin_percent'][ $loop ] ) && $_POST['bm_margin_percent'][ $loop ] !== '' )
        ? (float) $_POST['bm_margin_percent'][ $loop ] : null;

    if ( $purchase !== null ) {
        update_post_meta( $variation_id, '_bm_purchase_price', $purchase );
    }
    if ( $margin !== null ) {
        update_post_meta( $variation_id, '_bm_margin_percent', $margin );
    }

    if (
        $purchase !== null && $margin !== null &&
        $purchase > 0 && $margin >= 0 && $margin < 100
    ) {
        $sale_price = round( $purchase / ( 1 - $margin / 100 ), 2 );
        update_post_meta( $variation_id, '_regular_price', $sale_price );
        update_post_meta( $variation_id, '_price', $sale_price );
    }
}

// ---------------------------------------------------------------------------
// Liste produits — colonnes
// ---------------------------------------------------------------------------

function bm_ppm_add_columns( array $columns ): array {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'price' ) {
            $new['bm_purchase_price'] = __( "Prix d'achat", 'bm-ppm' );
            $new['bm_margin_percent'] = __( 'Marge', 'bm-ppm' );
        }
    }
    return $new;
}

function bm_ppm_render_columns( string $column, int $post_id ): void {
    if ( $column === 'bm_purchase_price' ) {
        $val = get_post_meta( $post_id, '_bm_purchase_price', true );
        echo $val !== '' ? esc_html( number_format( (float) $val, 2, ',', ' ' ) ) . '&nbsp;€' : '—';
    }
    if ( $column === 'bm_margin_percent' ) {
        $val = get_post_meta( $post_id, '_bm_margin_percent', true );
        echo $val !== '' ? esc_html( number_format( (float) $val, 2, ',', ' ' ) ) . '&nbsp;%' : '—';
    }
}
