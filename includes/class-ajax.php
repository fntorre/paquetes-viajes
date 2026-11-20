<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Ajax {
    public static function init() {
        add_action( 'wp_ajax_tp_toggle_slider',   [ __CLASS__, 'toggle_slider' ] );
        add_action( 'wp_ajax_tp_toggle_featured', [ __CLASS__, 'toggle_featured' ] );
        add_action( 'wp_ajax_tp_get_image_thumb', [ __CLASS__, 'get_image_thumb' ] );
        add_action( 'wp_ajax_tp_save_promo_config', [ __CLASS__, 'save_promo_config' ] );
        add_action( 'wp_ajax_tp_save_promo_template', [ __CLASS__, 'save_promo_template' ] );
        add_action( 'wp_ajax_tp_delete_promo_template', [ __CLASS__, 'delete_promo_template' ] );
        add_action( 'wp_ajax_tp_search_suggestions',        [ __CLASS__, 'search_suggestions' ] );
        add_action( 'wp_ajax_nopriv_tp_search_suggestions', [ __CLASS__, 'search_suggestions' ] );
    }

    public static function toggle_slider() {
        check_ajax_referer( 'tp_admin_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! self::can_edit_package( $post_id ) ) wp_send_json_error();

        $current = get_post_meta( $post_id, '_tp_slider', true );
        $new_val = $current === '1' ? '0' : '1';
        update_post_meta( $post_id, '_tp_slider', $new_val );
        wp_send_json_success([ 'value' => $new_val ]);
    }

    public static function toggle_featured() {
        check_ajax_referer( 'tp_admin_nonce', 'nonce' );

        $post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
        if ( ! self::can_edit_package( $post_id ) ) wp_send_json_error();

        $current = get_post_meta( $post_id, '_tp_featured', true );
        $new_val = $current === '1' ? '0' : '1';
        update_post_meta( $post_id, '_tp_featured', $new_val );
        wp_send_json_success([ 'value' => $new_val ]);
    }

    /** Comprueba que el post sea un paquete y que el usuario pueda editarlo. */
    private static function can_edit_package( $post_id ) {
        $post_id = (int) $post_id;
        if ( $post_id <= 0 || get_post_type( $post_id ) !== TP_Package_Data::POST_TYPE ) {
            return false;
        }
        return current_user_can( 'edit_post', $post_id );
    }

    public static function get_image_thumb() {
        check_ajax_referer( 'tp_admin_nonce', 'nonce' );
        if ( ! current_user_can('edit_posts') ) wp_send_json_error();

        $id = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
        if ( ! $id || get_post_type( $id ) !== 'attachment' ) wp_send_json_error();

        $url = wp_get_attachment_image_url( $id, 'medium' );
        if ( ! $url ) wp_send_json_error();

        wp_send_json_success([ 'url' => $url ]);
    }

    public static function save_promo_config() {
        check_ajax_referer( 'tp_admin_nonce', 'nonce' );
        $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
        if ( ! self::can_edit_package( $post_id ) ) {
            wp_send_json_error([ 'message' => __( 'No tenés permiso para guardar el diseño de este paquete.', 'travel-packages' ) ], 403);
        }
        $config = isset( $_POST['config'] ) && is_scalar( $_POST['config'] ) ? (string) $_POST['config'] : '';
        if ( $config === '' ) {
            wp_send_json_error([ 'message' => __( 'La configuración del diseño está vacía.', 'travel-packages' ) ], 400);
        }
        TP_Package_Data::save_promo_config( $post_id, $config );
        wp_send_json_success([ 'message' => __( 'Diseño guardado para este paquete.', 'travel-packages' ) ]);
    }

    public static function save_promo_template() {
        check_ajax_referer( 'tp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error([ 'message' => __( 'No tenés permiso para guardar plantillas de diseño.', 'travel-packages' ) ], 403);
        }
        $name = isset( $_POST['name'] ) && is_scalar( $_POST['name'] ) ? (string) $_POST['name'] : '';
        $config = isset( $_POST['config'] ) && is_scalar( $_POST['config'] ) ? (string) $_POST['config'] : '';
        if ( $config === '' ) {
            wp_send_json_error([ 'message' => __( 'La configuración del diseño está vacía.', 'travel-packages' ) ], 400);
        }
        $result = TP_Package_Data::save_promo_template( $name, $config );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error([ 'message' => $result->get_error_message() ], 400);
        }
        wp_send_json_success( $result );
    }

    public static function delete_promo_template() {
        check_ajax_referer( 'tp_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error([ 'message' => __( 'No tenés permiso para eliminar plantillas de diseño.', 'travel-packages' ) ], 403);
        }
        $id = isset( $_POST['template_id'] ) && is_scalar( $_POST['template_id'] ) ? (string) $_POST['template_id'] : '';
        $result = TP_Package_Data::delete_promo_template( $id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error([ 'message' => $result->get_error_message() ], 400);
        }
        wp_send_json_success( $result );
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
