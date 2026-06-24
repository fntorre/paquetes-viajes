<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Templates {

    public static function init() {
        add_filter( 'the_content', [ __CLASS__, 'render_package_detail' ] );
    }

    public static function render_package_detail( $content ) {
        if ( ! is_singular( 'paquete_viaje' ) || ! in_the_loop() ) {
            return $content;
        }

        // Ensure public assets are loaded for the single package view
        if ( function_exists('wp_enqueue_style') ) {
            // These handles are registered in TP_Shortcodes::enqueue_assets
            wp_enqueue_style( 'tp-public' );
            wp_enqueue_script( 'tp-public' );
        }

        global $post;
        $price       = get_post_meta( $post->ID, '_tp_price', true );
        $currency    = get_post_meta( $post->ID, '_tp_currency', true ) ?: 'USD';
        $price_note  = get_post_meta( $post->ID, '_tp_price_note', true );
        $short_desc  = get_post_meta( $post->ID, '_tp_short_desc', true );
        $tech_fields = get_post_meta( $post->ID, '_tp_tech_fields', true );
        $dates       = get_post_meta( $post->ID, '_tp_dates', true );
        $gallery_ids = get_post_meta( $post->ID, '_tp_gallery', true );
        $thumb_url   = get_the_post_thumbnail_url( $post->ID, 'full' );

        $custom_field_defs = TP_CustomFields::get_definitions();
        $custom_field_values = TP_CustomFields::get_all_values( $post->ID );

        ob_start();
        include TP_PLUGIN_DIR . 'templates/single-paquete.php';
        return ob_get_clean();
    }
}
