<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TP_Admin_Handlers
 *
 * Maneja todas las acciones POST/GET del admin que modifican datos:
 * guardar, duplicar, exportar, importar, eliminar y redirección del editor nativo.
 */
class TP_Admin_Handlers {

    public static function init() {
        add_action( 'admin_post_tp_save_package',       [ __CLASS__, 'save_package' ] );
        add_action( 'admin_post_tp_duplicate_package',  [ __CLASS__, 'duplicate_package' ] );
        add_action( 'admin_post_tp_export_packages',    [ __CLASS__, 'export_packages' ] );
        add_action( 'admin_post_tp_import_packages',    [ __CLASS__, 'import_packages' ] );
        add_action( 'admin_init',                       [ __CLASS__, 'handle_delete_package' ] );
        add_action( 'admin_init',                       [ __CLASS__, 'redirect_native_editor' ] );
    }

    public static function save_package() {
        if ( ! isset( $_POST['tp_package_nonce'] ) || ! wp_verify_nonce( $_POST['tp_package_nonce'], 'tp_package_save' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'No tienes permisos para guardar este paquete.', 'travel-packages' ) );
        }

        $title = isset( $_POST['tp_title'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_title'] ) ) : '';
        if ( empty( $title ) ) {
            wp_redirect( admin_url( 'admin.php?page=tp-packages&status=error' ) );
            exit;
        }

        $package_id = TP_Package_Data::upsert_post( $_POST );

        if ( is_wp_error( $package_id ) ) {
            wp_die( $package_id->get_error_message() );
        }

        TP_Package_Data::save_from_post( $package_id, $_POST );

        $redirect_url = add_query_arg( 'package_id', $package_id, admin_url( 'admin.php?page=tp-packages&status=success' ) );
        wp_redirect( $redirect_url );
        exit;
    }

    public static function duplicate_package() {
        if ( ! isset( $_GET['tp_duplicate_nonce'] ) || ! wp_verify_nonce( $_GET['tp_duplicate_nonce'], 'tp_duplicate_package' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        $original_id = isset( $_GET['tp_package_id'] ) ? intval( $_GET['tp_package_id'] ) : 0;
        $original    = get_post( $original_id );

        if ( ! $original || $original->post_type !== TP_Package_Data::POST_TYPE ) {
            wp_redirect( admin_url( 'admin.php?page=tp-packages' ) );
            exit;
        }

        $new_slug = $original->post_name ? $original->post_name . '-copia' : '';
        $new_id   = wp_insert_post( [
            'post_type'    => TP_Package_Data::POST_TYPE,
            'post_title'   => $original->post_title . ' (' . __( 'copia', 'travel-packages' ) . ')',
            'post_content' => $original->post_content,
            'post_status'  => $original->post_status,
            'post_name'    => $new_slug,
        ] );

        if ( is_wp_error( $new_id ) ) {
            wp_die( $new_id->get_error_message() );
        }

        TP_Package_Data::copy_to( $original_id, $new_id );

        wp_redirect( admin_url( 'admin.php?page=tp-packages&status=duplicated' ) );
        exit;
    }

    public static function export_packages() {
        if ( ! isset( $_GET['tp_export_nonce'] ) || ! wp_verify_nonce( $_GET['tp_export_nonce'], 'tp_export_packages' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        $packages = get_posts( [
            'post_type'      => TP_Package_Data::POST_TYPE,
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $data = [];
        foreach ( $packages as $p ) {
            $data[] = [
                'title'              => $p->post_title,
                'content'            => $p->post_content,
                'status'             => $p->post_status,
                'short_desc'         => get_post_meta( $p->ID, '_tp_short_desc', true ),
                'price'              => get_post_meta( $p->ID, '_tp_price', true ),
                'price_note'         => get_post_meta( $p->ID, '_tp_price_note', true ),
                'currency'           => get_post_meta( $p->ID, '_tp_currency', true ) ?: 'USD',
                'slider'             => get_post_meta( $p->ID, '_tp_slider', true ),
                'featured'           => get_post_meta( $p->ID, '_tp_featured', true ),
                'destinos'           => TP_Package_Data::get_term_names( $p->ID, TP_Package_Data::TAX_DESTINO ),
                'tipologias'         => TP_Package_Data::get_term_names( $p->ID, TP_Package_Data::TAX_TIPOLOGIA ),
                'featured_image_url' => get_the_post_thumbnail_url( $p->ID, 'full' ) ?: '',
                'gallery_urls'       => TP_Package_Data::get_gallery_urls( $p->ID ),
                'tech_fields'        => get_post_meta( $p->ID, '_tp_tech_fields', true ),
                'dates'              => get_post_meta( $p->ID, '_tp_dates', true ),
                'custom_fields'      => TP_CustomFields::get_package_data( $p->ID ),
            ];
        }

        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="paquetes-de-viaje-' . gmdate( 'Y-m-d' ) . '.json"' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    public static function import_packages() {
        if ( ! isset( $_POST['tp_import_nonce'] ) || ! wp_verify_nonce( $_POST['tp_import_nonce'], 'tp_import_packages' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }
        if ( ! isset( $_FILES['tp_import_file'] ) || $_FILES['tp_import_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_die( __( 'Error al subir el archivo.', 'travel-packages' ) );
        }

        $json = file_get_contents( $_FILES['tp_import_file']['tmp_name'] );
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            wp_die( __( 'Formato JSON inválido.', 'travel-packages' ) );
        }

        $count = 0;
        foreach ( $data as $item ) {
            $title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
            if ( empty( $title ) ) {
                continue;
            }

            $post_id = wp_insert_post( [
                'post_type'    => TP_Package_Data::POST_TYPE,
                'post_title'   => $title,
                'post_content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
                'post_status'  => isset( $item['status'] ) && in_array( $item['status'], [ 'publish', 'draft' ], true ) ? $item['status'] : 'draft',
            ] );
            if ( is_wp_error( $post_id ) ) {
                continue;
            }

            $meta_map = [
                '_tp_short_desc'  => 'short_desc',
                '_tp_price'       => 'price',
                '_tp_price_note'  => 'price_note',
                '_tp_currency'    => 'currency',
                '_tp_slider'      => 'slider',
                '_tp_featured'    => 'featured',
                '_tp_tech_fields' => 'tech_fields',
                '_tp_dates'       => 'dates',
            ];
            foreach ( $meta_map as $meta_key => $item_key ) {
                if ( isset( $item[ $item_key ] ) ) {
                    $val = is_string( $item[ $item_key ] ) ? sanitize_text_field( $item[ $item_key ] ) : $item[ $item_key ];
                    update_post_meta( $post_id, $meta_key, $val );
                }
            }

            if ( isset( $item['custom_fields'] ) && is_array( $item['custom_fields'] ) ) {
                foreach ( $item['custom_fields'] as $cf_key => $cf_val ) {
                    update_post_meta( $post_id, '_tp_cf_' . sanitize_key( $cf_key ), sanitize_text_field( $cf_val ) );
                }
            }

            foreach ( [ TP_Package_Data::TAX_DESTINO => 'destinos', TP_Package_Data::TAX_TIPOLOGIA => 'tipologias' ] as $taxonomy => $item_key ) {
                if ( ! isset( $item[ $item_key ] ) || ! is_array( $item[ $item_key ] ) ) {
                    continue;
                }
                $term_ids = [];
                foreach ( $item[ $item_key ] as $name ) {
                    $term_id = TP_Package_Data::resolve_term_id( $name, $taxonomy );
                    if ( $term_id ) {
                        $term_ids[] = $term_id;
                    }
                }
                if ( $term_ids ) {
                    wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
                }
            }

            $count++;
        }

        wp_redirect( admin_url( 'admin.php?page=tp-packages&status=imported&tp_imported=' . $count ) );
        exit;
    }

    public static function handle_delete_package() {
        if ( ! isset( $_GET['tp_action'] ) || $_GET['tp_action'] !== 'delete_package' ) {
            return;
        }
        if ( ! isset( $_GET['tp_delete_nonce'] ) || ! wp_verify_nonce( $_GET['tp_delete_nonce'], 'tp_delete_package' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_die( __( 'No tienes permisos para eliminar paquetes.', 'travel-packages' ) );
        }

        $post_id = ! empty( $_GET['tp_package_id'] ) ? (int) $_GET['tp_package_id'] : 0;
        $post    = $post_id ? get_post( $post_id ) : null;

        if ( ! $post || $post->post_type !== TP_Package_Data::POST_TYPE ) {
            wp_die( __( 'Paquete no encontrado.', 'travel-packages' ) );
        }

        wp_delete_post( $post_id, true );
        wp_redirect( admin_url( 'admin.php?page=tp-packages&status=deleted' ) );
        exit;
    }

    public static function redirect_native_editor() {
        if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
            return;
        }

        global $pagenow;

        if ( $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === TP_Package_Data::POST_TYPE ) {
            wp_safe_redirect( admin_url( 'admin.php?page=tp-packages' ) );
            exit;
        }

        if ( $pagenow === 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === TP_Package_Data::POST_TYPE ) {
            wp_safe_redirect( admin_url( 'admin.php?page=tp-package-new' ) );
            exit;
        }

        if ( $pagenow === 'post.php' && isset( $_GET['post'] ) ) {
            $post_id = intval( $_GET['post'] );
            $post    = get_post( $post_id );
            if ( $post && $post->post_type === TP_Package_Data::POST_TYPE ) {
                wp_safe_redirect( admin_url( 'admin.php?page=tp-packages&package_id=' . $post_id ) );
                exit;
            }
        }
    }
}
