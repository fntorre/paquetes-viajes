<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Ajax {
    public static function init() {
        add_action( 'wp_ajax_tp_toggle_slider',   [ __CLASS__, 'toggle_slider' ] );
        add_action( 'wp_ajax_tp_toggle_featured', [ __CLASS__, 'toggle_featured' ] );
        add_action( 'wp_ajax_tp_get_image_thumb', [ __CLASS__, 'get_image_thumb' ] );
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
        if ( ! current_user_can('edit_posts') ) wp_send_json_error();

        $id = (int) $_POST['id'];
        if ( ! $id ) wp_send_json_error();

        $url = wp_get_attachment_image_url( $id, 'medium' );
        if ( ! $url ) wp_send_json_error();

        wp_send_json_success([ 'url' => $url ]);
    }
}
