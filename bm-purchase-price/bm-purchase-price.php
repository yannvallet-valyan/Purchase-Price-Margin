<?php
/**
 * Plugin Name: BM Purchase Price & Margin
 * Plugin URI:  https://example.com/bm-purchase-price
 * Description: Gère les prix d'achat, les marges et les prix de vente pour les produits simples et les variations WooCommerce.
 * Version:     1.0.2
 * Author:      BM
 * Text Domain: bm-ppm
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * WC requires at least: 7.0
 */

defined( 'ABSPATH' ) || exit;

// ---------------------------------------------------------------------------
// Enqueue admin script
// ---------------------------------------------------------------------------

add_action( 'admin_enqueue_scripts', 'bm_ppm_enqueue_scripts' );
function bm_ppm_enqueue_scripts( string $hook ): void {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'product' ) {
        return;
    }
    wp_enqueue_script(
        'bm-ppm',
        plugin_dir_url( __FILE__ ) . 'assets/js/bm-ppm.js',
        [ 'jquery' ],
        '1.0.2',
        true
    );
}

add_action( 'admin_head', 'bm_ppm_admin_css' );
function bm_ppm_admin_css(): void {
    $screen = get_current_screen();
    if ( ! $screen ) return;
    if ( $screen->id === 'edit-product' ) {
        echo '<style>
            .column-bm_purchase_price,
            .column-bm_margin_percent { white-space: nowrap; width: 90px; }
        </style>';
    }
    if ( $screen->post_type === 'product' ) {
        echo '<style>
            .bm-ppm-variation-group { display: flex; gap: 12px; flex-wrap: wrap;
                clear: both; padding: 6px 9px; border-top: 1px solid #eee; margin-top: 4px; }
            .bm-ppm-variation-group .form-row { margin: 0; flex: 1 1 140px; }
            .bm-ppm-variation-group label { display: block; font-weight: 600;
                margin-bottom: 3px; font-size: 12px; }
            .bm-ppm-variation-group input[type="number"] { width: 100%; }
        </style>';
    }
}

// ---------------------------------------------------------------------------
// Simple product — General tab fields
// ---------------------------------------------------------------------------

add_action( 'woocommerce_product_options_general_product_data', 'bm_ppm_simple_fields' );
function bm_ppm_simple_fields(): void {
    global $post;
    $purchase = get_post_meta( $post->ID, '_bm_purchase_price', true );
    $margin   = get_post_meta( $post->ID, '_bm_margin_percent', true );
    ?>
    <div class="options_group bm-ppm-group">
        <p class="form-field bm_purchase_price_field">
            <label for="bm_purchase_price"><?php esc_html_e( 'Prix d\'achat (€)', 'bm-ppm' ); ?></label>
            <input
                type="number"
                id="bm_purchase_price"
                name="bm_purchase_price"
                class="short bm-ppm-purchase"
                step="0.01"
                min="0"
                value="<?php echo esc_attr( $purchase ); ?>"
                data-target="#_regular_price"
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
                value="<?php echo esc_attr( $margin ); ?>"
                data-target="#_regular_price"
            />
        </p>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Simple product — Save
// ---------------------------------------------------------------------------

add_action( 'woocommerce_process_product_meta', 'bm_ppm_save_simple_fields' );
function bm_ppm_save_simple_fields( int $post_id ): void {
    $purchase = ( isset( $_POST['bm_purchase_price'] ) && $_POST['bm_purchase_price'] !== '' )
        ? (float) $_POST['bm_purchase_price'] : null;
    $margin = ( isset( $_POST['bm_margin_percent'] ) && $_POST['bm_margin_percent'] !== '' )
        ? (float) $_POST['bm_margin_percent'] : null;

    if ( $purchase !== null ) {
        update_post_meta( $post_id, '_bm_purchase_price', $purchase );
    }
    if ( $margin !== null ) {
        update_post_meta( $post_id, '_bm_margin_percent', $margin );
    }

    // Recalculate only when both values are valid
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
// Variation fields
// ---------------------------------------------------------------------------

add_action( 'woocommerce_variation_options_pricing', 'bm_ppm_variation_fields', 10, 3 );
function bm_ppm_variation_fields( int $loop, array $variation_data, WP_Post $variation ): void {
    $purchase = get_post_meta( $variation->ID, '_bm_purchase_price', true );
    $margin   = get_post_meta( $variation->ID, '_bm_margin_percent', true );
    ?>
    <div class="bm-ppm-variation-group">
        <div class="form-row">
            <label><?php esc_html_e( 'Prix d\'achat (€)', 'bm-ppm' ); ?></label>
            <input
                type="number"
                name="bm_purchase_price[<?php echo esc_attr( $loop ); ?>]"
                class="bm-ppm-purchase bm-ppm-variation-purchase"
                step="0.01"
                min="0"
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
                value="<?php echo esc_attr( $margin ); ?>"
                data-loop="<?php echo esc_attr( $loop ); ?>"
            />
        </div>
    </div>
    <?php
}

// ---------------------------------------------------------------------------
// Variation — Save
// ---------------------------------------------------------------------------

add_action( 'woocommerce_save_product_variation', 'bm_ppm_save_variation_fields', 10, 2 );
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
// Products list — columns
// ---------------------------------------------------------------------------

add_filter( 'manage_edit-product_columns', 'bm_ppm_add_columns' );
function bm_ppm_add_columns( array $columns ): array {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'price' ) {
            $new['bm_purchase_price'] = __( 'Prix d\'achat', 'bm-ppm' );
            $new['bm_margin_percent'] = __( 'Marge', 'bm-ppm' );
        }
    }
    return $new;
}

add_action( 'manage_product_posts_custom_column', 'bm_ppm_render_columns', 10, 2 );
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
