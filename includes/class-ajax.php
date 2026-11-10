<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Ajax {
    public static function init() {
        add_action( 'wp_ajax_tp_toggle_slider',   [ __CLASS__, 'toggle_slider' ] );
        add_action( 'wp_ajax_tp_toggle_featured', [ __CLASS__, 'toggle_featured' ] );
        add_action( 'wp_ajax_tp_get_image_thumb', [ __CLASS__, 'get_image_thumb' ] );
        add_action( 'wp_ajax_tp_search_suggestions',        [ __CLASS__, 'search_suggestions' ] );
        add_action( 'wp_ajax_nopriv_tp_search_suggestions', [ __CLASS__, 'search_suggestions' ] );
    }

    public static function toggle_slider() {
        check_ajax_referer( 'tp_admin_nonce', 'nonce' );
        if ( ! current_user_can('edit_posts') ) wp_send_json_error();

        $post_id = (int) $_POST['post_id'];
        $current = get_post_meta( $post_id, '_tp_slider', true );
        $new_val = $current === '1' ? '0' : '1';
        update_post_meta( $post_id, '_tp_slider', $new_val );
        wp_send_json_success([ 'value' => $new_val ]);
    }

    public static function toggle_featured() {
        check_ajax_referer( 'tp_admin_nonce', 'nonce' );
        if ( ! current_user_can('edit_posts') ) wp_send_json_error();

        $post_id = (int) $_POST['post_id'];
        $current = get_post_meta( $post_id, '_tp_featured', true );
        $new_val = $current === '1' ? '0' : '1';
        update_post_meta( $post_id, '_tp_featured', $new_val );
        wp_send_json_success([ 'value' => $new_val ]);
    }

    public static function get_image_thumb() {
        check_ajax_referer( 'tp_admin_nonce', 'nonce' );
        if ( ! current_user_can('edit_posts') ) wp_send_json_error();

        $id = (int) $_POST['id'];
        if ( ! $id ) wp_send_json_error();

        $url = wp_get_attachment_image_url( $id, 'medium' );
        if ( ! $url ) wp_send_json_error();

        wp_send_json_success([ 'url' => $url ]);
    }

    /** Sugerencias públicas del buscador de paquetes. */
    public static function search_suggestions() {
        check_ajax_referer( 'tp_public_nonce', 'nonce' );

        $term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
        $len  = function_exists( 'mb_strlen' ) ? mb_strlen( $term ) : strlen( $term );

        if ( $len < 2 ) {
            wp_send_json_success([ 'items' => [], 'total' => 0 ]);
        }

        $ids   = TP_Package_Data::search_package_ids( $term, 100 );
        $items = [];

        foreach ( array_slice( $ids, 0, 8 ) as $id ) {
            $best_price = TP_Package_Data::get_best_price( $id );
            $destinos   = TP_Package_Data::get_term_names( $id, TP_Package_Data::TAX_DESTINO );
            $thumb      = get_the_post_thumbnail_url( $id, 'thumbnail' );

            $items[] = [
                'id'          => (int) $id,
                'title'       => get_the_title( $id ),
                'url'         => get_permalink( $id ),
                'thumb'       => $thumb ? $thumb : '',
                'destination' => ! empty( $destinos ) ? implode( ', ', $destinos ) : '',
                'price'       => $best_price
                    ? TP_Package_Data::currency_symbol( $best_price['currency'] ) . ' ' . number_format( (float) $best_price['price'], 0, ',', '.' )
                    : '',
            ];
        }

        wp_send_json_success([
            'items' => $items,
            'total' => count( $ids ),
        ]);
    }
}
