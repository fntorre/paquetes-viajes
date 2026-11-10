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
                'prices'             => TP_Package_Data::get_prices( $p->ID ),
                'pricing'            => TP_Package_Data::get_pricing( $p->ID ),
                'slider'             => get_post_meta( $p->ID, '_tp_slider', true ),
                'featured'           => get_post_meta( $p->ID, '_tp_featured', true ),
                'tipo_viaje'         => get_post_meta( $p->ID, TP_Package_Data::META_TIPO_VIAJE, true ),
                'destinos'           => TP_Package_Data::get_term_names( $p->ID, TP_Package_Data::TAX_DESTINO ),
                'tipologias'         => TP_Package_Data::get_term_names( $p->ID, TP_Package_Data::TAX_TIPOLOGIA ),
                'similar_destinos'   => TP_Package_Data::get_similar_destino_names( $p->ID ),
                'featured_image_url' => get_the_post_thumbnail_url( $p->ID, 'full' ) ?: '',
                'gallery_urls'       => TP_Package_Data::get_gallery_urls( $p->ID ),
                'tech_fields'        => get_post_meta( $p->ID, '_tp_tech_fields', true ),
                'video_url'          => TP_Package_Data::get_video_url( $p->ID ),
                'map_location'       => TP_Package_Data::get_map_location( $p->ID ),
                'whatsapp'           => get_post_meta( $p->ID, TP_Package_Data::META_WHATSAPP, true ),
                'itinerary'          => TP_Package_Data::get_itinerary( $p->ID ),
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
                '_tp_slider'      => 'slider',
                '_tp_featured'    => 'featured',
                '_tp_tipo_viaje'  => 'tipo_viaje',
                '_tp_tech_fields' => 'tech_fields',
                '_tp_video_url'   => 'video_url',
                '_tp_map_location'=> 'map_location',
            ];
            foreach ( $meta_map as $meta_key => $item_key ) {
                if ( isset( $item[ $item_key ] ) ) {
                    $val = is_string( $item[ $item_key ] ) ? sanitize_text_field( $item[ $item_key ] ) : $item[ $item_key ];
                    update_post_meta( $post_id, $meta_key, $val );
                }
            }

            if ( isset( $item['whatsapp'] ) ) {
                $whatsapp = preg_replace( '/\D+/', '', (string) $item['whatsapp'] );
                if ( $whatsapp !== '' ) {
                    update_post_meta( $post_id, TP_Package_Data::META_WHATSAPP, $whatsapp );
                }
            }

            if ( isset( $item['itinerary'] ) && is_array( $item['itinerary'] ) ) {
                $itinerary = [];
                foreach ( $item['itinerary'] as $row ) {
                    if ( ! is_array( $row ) ) {
                        continue;
                    }
                    $day   = sanitize_text_field( $row['day'] ?? '' );
                    $title = sanitize_text_field( $row['title'] ?? '' );
                    $desc  = sanitize_textarea_field( $row['desc'] ?? '' );
                    if ( $day === '' && $title === '' && $desc === '' ) {
                        continue;
                    }
                    $itinerary[] = [ 'day' => $day, 'title' => $title, 'desc' => $desc ];
                }
                if ( ! empty( $itinerary ) ) {
                    update_post_meta( $post_id, TP_Package_Data::META_ITINERARY, $itinerary );
                }
            }

            if ( isset( $item['dates'] ) ) {
                $departures = TP_Package_Data::sanitize_departures_array( $item['dates'] );
                update_post_meta( $post_id, '_tp_dates', $departures );
            }

            if ( isset( $item['pricing'] ) && is_array( $item['pricing'] ) ) {
                $pricing_in = $item['pricing'];
                $deposit_type = sanitize_key( $pricing_in['deposit_type'] ?? 'none' );
                if ( ! in_array( $deposit_type, [ 'none', 'percent', 'amount' ], true ) ) {
                    $deposit_type = 'none';
                }
                $currency = sanitize_text_field( $pricing_in['currency'] ?? 'USD' ) ?: 'USD';
                if ( ! in_array( $currency, [ 'USD', 'ARS', 'EUR', 'BRL' ], true ) ) {
                    $currency = 'USD';
                }
                $pricing = [
                    'currency'          => $currency,
                    'price_adult'       => sanitize_text_field( $pricing_in['price_adult'] ?? '' ),
                    'price_child'       => sanitize_text_field( $pricing_in['price_child'] ?? '' ),
                    'single_supplement' => sanitize_text_field( $pricing_in['single_supplement'] ?? '' ),
                    'deposit_type'      => $deposit_type,
                    'deposit_value'     => sanitize_text_field( $pricing_in['deposit_value'] ?? '' ),
                    'deposit_note'      => sanitize_text_field( $pricing_in['deposit_note'] ?? '' ),
                ];
                update_post_meta( $post_id, TP_Package_Data::META_PRICING, $pricing );
            }

            if ( isset( $item['prices'] ) && is_array( $item['prices'] ) ) {
                $prices = [];
                foreach ( $item['prices'] as $p ) {
                    $prices[] = [
                        'price'       => sanitize_text_field( $p['price'] ?? '' ),
                        'note'        => sanitize_text_field( $p['note'] ?? '' ),
                        'currency'    => sanitize_text_field( $p['currency'] ?? '' ) ?: 'USD',
                        'highlighted' => ! empty( $p['highlighted'] ),
                    ];
                }
                update_post_meta( $post_id, TP_Package_Data::META_PRICES, $prices );
                if ( ! empty( $prices ) ) {
                    $best = $prices[0];
                    foreach ( $prices as $p ) { if ( ! empty( $p['highlighted'] ) ) { $best = $p; break; } }
                    update_post_meta( $post_id, '_tp_price', $best['price'] );
                    update_post_meta( $post_id, '_tp_price_note', $best['note'] );
                    update_post_meta( $post_id, '_tp_currency', $best['currency'] );
                }
            } elseif ( isset( $item['price'] ) || isset( $item['price_note'] ) || isset( $item['currency'] ) ) {
                $prices = [[
                    'price'       => sanitize_text_field( $item['price'] ?? '' ),
                    'note'        => sanitize_text_field( $item['price_note'] ?? '' ),
                    'currency'    => sanitize_text_field( $item['currency'] ?? '' ) ?: 'USD',
                    'highlighted' => true,
                ]];
                update_post_meta( $post_id, TP_Package_Data::META_PRICES, $prices );
                update_post_meta( $post_id, '_tp_price', $prices[0]['price'] );
                update_post_meta( $post_id, '_tp_price_note', $prices[0]['note'] );
                update_post_meta( $post_id, '_tp_currency', $prices[0]['currency'] );
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

            if ( isset( $item['similar_destinos'] ) && is_array( $item['similar_destinos'] ) ) {
                $similar_ids = [];
                foreach ( $item['similar_destinos'] as $name ) {
                    $term_id = TP_Package_Data::resolve_term_id( $name, TP_Package_Data::TAX_DESTINO );
                    if ( $term_id ) {
                        $similar_ids[] = $term_id;
                    }
                }
                if ( $similar_ids ) {
                    update_post_meta( $post_id, TP_Package_Data::META_SIMILAR_DESTINOS, array_values( array_unique( $similar_ids ) ) );
                }
            }

            if ( ! empty( $item['featured_image_url'] ) ) {
                $thumb_id = self::sideload_image( $item['featured_image_url'], $post_id, $title );
                if ( $thumb_id ) {
                    set_post_thumbnail( $post_id, $thumb_id );
                }
            }

            if ( ! empty( $item['gallery_urls'] ) && is_array( $item['gallery_urls'] ) ) {
                $gallery_ids = [];
                foreach ( $item['gallery_urls'] as $gallery_url ) {
                    $image_id = self::sideload_image( $gallery_url, $post_id, $title );
                    if ( $image_id ) {
                        $gallery_ids[] = $image_id;
                    }
                }
                if ( $gallery_ids ) {
                    update_post_meta( $post_id, '_tp_gallery', implode( ',', $gallery_ids ) );
                }
            }

            $best_price = TP_Package_Data::get_best_price( $post_id );
            if ( $best_price ) {
                update_post_meta( $post_id, '_tp_price', $best_price['price'] );
                update_post_meta( $post_id, '_tp_price_note', $best_price['note'] );
                update_post_meta( $post_id, '_tp_currency', $best_price['currency'] );
            }

            $count++;
        }

        wp_redirect( admin_url( 'admin.php?page=tp-packages&status=imported&tp_imported=' . $count ) );
        exit;
    }

    /**
     * Descarga una imagen remota y la registra en la biblioteca de medios.
     * Devuelve el ID del adjunto o 0 si falla.
     */
    private static function sideload_image( $url, $post_id, $title = '' ) {
        $url = esc_url_raw( $url );
        if ( ! $url ) {
            return 0;
        }

        $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image( $url, $post_id, $title ?: null, 'id' );

        return is_wp_error( $attachment_id ) ? 0 : (int) $attachment_id;
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
