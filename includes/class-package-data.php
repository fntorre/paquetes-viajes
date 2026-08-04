<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TP_Package_Data
 *
 * Única fuente de verdad para leer y escribir los datos de un paquete:
 * meta, galería, fechas, ficha técnica, campos personalizados, imagen destacada
 * y taxonomías (destino / tipología).
 */
class TP_Package_Data {

    const POST_TYPE      = 'paquete_viaje';
    const TAX_DESTINO    = 'destino';
    const TAX_TIPOLOGIA  = 'tipologia';
    const META_TIPO_VIAJE = '_tp_tipo_viaje';

    /** Mapeo campo de formulario -> meta key. */
    const META_MAP = [
        'tp_short_desc' => '_tp_short_desc',
        'tp_price'      => '_tp_price',
        'tp_price_note' => '_tp_price_note',
        'tp_currency'   => '_tp_currency',
        'tp_gallery'    => '_tp_gallery',
    ];

    /* =========================================================
     * LECTURA
     * ========================================================= */

    public static function get_meta( $post_id ) {
        $data = [];
        foreach ( self::META_MAP as $field => $meta_key ) {
            $data[ $field ] = get_post_meta( $post_id, $meta_key, true );
        }
        $data['tp_slider']   = get_post_meta( $post_id, '_tp_slider', true );
        $data['tp_featured'] = get_post_meta( $post_id, '_tp_featured', true );
        return $data;
    }

    public static function get_gallery_ids( $post_id ) {
        $raw = get_post_meta( $post_id, '_tp_gallery', true );
        return $raw ? array_values( array_filter( array_map( 'intval', explode( ',', $raw ) ) ) ) : [];
    }

    /** Devuelve [ ['id'=>..,'url'=>..], ... ] para la galería. */
    public static function get_gallery_items( $post_id, $size = 'thumbnail' ) {
        $items = [];
        foreach ( self::get_gallery_ids( $post_id ) as $gallery_id ) {
            $url = wp_get_attachment_image_url( $gallery_id, $size );
            if ( $url ) {
                $items[] = [ 'id' => $gallery_id, 'url' => $url ];
            }
        }
        return $items;
    }

    public static function get_gallery_urls( $post_id, $size = 'full' ) {
        $urls = [];
        foreach ( self::get_gallery_ids( $post_id ) as $gallery_id ) {
            $url = wp_get_attachment_image_url( $gallery_id, $size );
            if ( $url ) {
                $urls[] = $url;
            }
        }
        return $urls;
    }

    /** Slugs de una taxonomía para un paquete. */
    public static function get_term_slugs( $post_id, $taxonomy ) {
        $terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
        return is_wp_error( $terms ) ? [] : $terms;
    }

    /** Nombres de una taxonomía para un paquete. */
    public static function get_term_names( $post_id, $taxonomy ) {
        $terms = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );
        return is_wp_error( $terms ) ? [] : $terms;
    }

    /**
     * Datos completos del paquete en el formato que espera el gestor admin
     * (JSON de la card y prefill del formulario).
     */
    public static function get_admin_data( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return [];
        }

        return [
            'ID'               => $post->ID,
            'post_title'       => $post->post_title,
            'post_content'     => $post->post_content,
            'post_status'      => $post->post_status,
            'tp_short_desc'    => get_post_meta( $post->ID, '_tp_short_desc', true ),
            'tp_price'         => get_post_meta( $post->ID, '_tp_price', true ),
            'tp_price_note'    => get_post_meta( $post->ID, '_tp_price_note', true ),
            'tp_currency'      => get_post_meta( $post->ID, '_tp_currency', true ) ?: 'USD',
            'tp_slider'        => get_post_meta( $post->ID, '_tp_slider', true ),
            'tp_featured'      => get_post_meta( $post->ID, '_tp_featured', true ),
            'tp_featured_image'=> get_post_thumbnail_id( $post->ID ) ?: '',
            'tp_gallery'       => self::get_gallery_items( $post->ID ),
            'tp_dates'         => get_post_meta( $post->ID, '_tp_dates', true ),
            'tp_tech_fields'   => get_post_meta( $post->ID, '_tp_tech_fields', true ),
            'tp_custom_fields' => TP_CustomFields::get_package_data( $post->ID ),
            'tp_tipo_viaje'    => get_post_meta( $post->ID, self::META_TIPO_VIAJE, true ),
            'destinos'         => self::get_term_slugs( $post->ID, self::TAX_DESTINO ),
            'tipologias'       => self::get_term_slugs( $post->ID, self::TAX_TIPOLOGIA ),
        ];
    }

    /* =========================================================
     * ESCRITURA
     * ========================================================= */

    /**
     * Guarda todos los campos de un paquete a partir de $_POST.
     * Usado tanto por TP_Admin_Handlers::save_package() como por los meta boxes.
     */
    public static function save_from_post( $post_id, $input ) {
        if ( ! $post_id || $post_id < 1 ) {
            return;
        }

        self::save_meta_fields( $post_id, $input );

        $tech = self::sanitize_tech_fields( $input );
        update_post_meta( $post_id, '_tp_tech_fields', $tech );

        $dates = self::sanitize_dates( $input );
        update_post_meta( $post_id, '_tp_dates', $dates );

        TP_CustomFields::save_post_fields( $post_id );

        self::save_featured_image( $post_id, $input );

        self::save_taxonomy_term( $post_id, self::TAX_DESTINO, $input['tp_destino'] ?? '', $input['tp_new_destino'] ?? '' );
        self::save_taxonomy_term( $post_id, self::TAX_TIPOLOGIA, $input['tp_tipologia'] ?? '', $input['tp_new_tipologia'] ?? '' );
    }

    private static function save_meta_fields( $post_id, $input ) {
        foreach ( self::META_MAP as $field => $meta_key ) {
            if ( isset( $input[ $field ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( $input[ $field ] ) ) );
            }
        }

        update_post_meta( $post_id, '_tp_slider',   empty( $input['tp_slider'] )   ? '0' : '1' );
        update_post_meta( $post_id, '_tp_featured', empty( $input['tp_featured'] ) ? '0' : '1' );

        $tipo_viaje = isset( $input['tp_tipo_viaje'] ) ? sanitize_text_field( $input['tp_tipo_viaje'] ) : '';
        if ( in_array( $tipo_viaje, [ 'nacional', 'internacional' ], true ) ) {
            update_post_meta( $post_id, self::META_TIPO_VIAJE, $tipo_viaje );
        } else {
            delete_post_meta( $post_id, self::META_TIPO_VIAJE );
        }
    }

    private static function save_featured_image( $post_id, $input ) {
        if ( isset( $input['tp_featured_image'] ) && $input['tp_featured_image'] !== '' ) {
            $fid = intval( $input['tp_featured_image'] );
            if ( $fid ) {
                set_post_thumbnail( $post_id, $fid );
            }
        }
    }

    /** Ficha técnica: pares clave/valor desde arrays paralelos. */
    private static function sanitize_tech_fields( $input ) {
        $keys = isset( $input['tp_tech_key'] ) ? (array) $input['tp_tech_key'] : [];
        $vals = isset( $input['tp_tech_val'] ) ? (array) $input['tp_tech_val'] : [];
        $tech = [];

        foreach ( $keys as $index => $key ) {
            $k = sanitize_text_field( $key );
            $v = sanitize_text_field( $vals[ $index ] ?? '' );
            if ( $k ) {
                $tech[] = [ 'key' => $k, 'val' => $v ];
            }
        }
        return $tech;
    }

    /** Fechas: pares from/to con validación de rango y soporte legacy. */
    private static function sanitize_dates( $input ) {
        $from = isset( $input['tp_dates_from'] ) ? (array) $input['tp_dates_from'] : [];
        $to   = isset( $input['tp_dates_to'] )   ? (array) $input['tp_dates_to']   : [];
        $dates = [];
        $max = max( count( $from ), count( $to ) );

        for ( $i = 0; $i < $max; $i++ ) {
            $f = isset( $from[ $i ] ) ? sanitize_text_field( wp_unslash( $from[ $i ] ) ) : '';
            $t = isset( $to[ $i ] )   ? sanitize_text_field( wp_unslash( $to[ $i ] ) )   : '';
            if ( $f || $t ) {
                if ( $f && $t ) {
                    $f_ts = strtotime( str_replace( '/', '-', $f ) );
                    $t_ts = strtotime( str_replace( '/', '-', $t ) );
                    if ( $f_ts && $t_ts && $t_ts < $f_ts ) {
                        $t = $f;
                    }
                }
                $dates[] = [ 'from' => $f, 'to' => $t ];
            }
        }

        // Soporte legacy (campo único tp_dates).
        if ( empty( $dates ) && isset( $input['tp_dates'] ) ) {
            foreach ( (array) $input['tp_dates'] as $d ) {
                $d = sanitize_text_field( wp_unslash( $d ) );
                if ( $d ) {
                    $dates[] = [ 'from' => $d, 'to' => '' ];
                }
            }
        }
        return $dates;
    }

    /**
     * Asigna un término (existente o creado) a una taxonomía del paquete.
     * Si no se envía nada, elimina las relaciones previas.
     */
    private static function save_taxonomy_term( $post_id, $taxonomy, $existing_slug, $new_name ) {
        $requested = [];

        if ( $existing_slug ) {
            $requested[] = sanitize_text_field( wp_unslash( $existing_slug ) );
        }
        if ( $new_name ) {
            $requested[] = sanitize_text_field( wp_unslash( $new_name ) );
        }

        $term_ids = [];
        foreach ( $requested as $value ) {
            if ( ! $value ) {
                continue;
            }
            $term_id = self::resolve_term_id( $value, $taxonomy );
            if ( $term_id ) {
                $term_ids[] = $term_id;
            }
        }
        $term_ids = array_values( array_unique( $term_ids ) );

        if ( $term_ids ) {
            $result = wp_set_object_terms( $post_id, $term_ids, $taxonomy, false );
        } else {
            $result = wp_delete_object_term_relationships( $post_id, $taxonomy );
        }

        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $readback = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
            error_log( sprintf(
                '[TP DEBUG] save_taxonomy_term tax=%s requested=%s ids=%s result=%s readback=%s',
                $taxonomy,
                wp_json_encode( $requested ),
                wp_json_encode( $term_ids ),
                is_wp_error( $result ) ? 'WP_Error:' . $result->get_error_message() : wp_json_encode( $result ),
                wp_json_encode( $readback )
            ) );
        }
    }

    /**
     * Resuelve un término por slug o nombre y devuelve su ID numérico (0 si falla).
     * Incluye fallback por consulta directa a la base para sortear cachés y
     * collations que rompen get_term_by()/term_exists().
     */
    public static function resolve_term_id( $name_or_slug, $taxonomy ) {
        global $wpdb;

        $value = sanitize_text_field( $name_or_slug );
        if ( ! $value ) {
            return 0;
        }

        $term = get_term_by( 'slug', $value, $taxonomy );
        if ( ! $term ) {
            $term = get_term_by( 'name', $value, $taxonomy );
        }
        if ( $term && ! is_wp_error( $term ) ) {
            return intval( $term->term_id );
        }

        $term_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT t.term_id FROM {$wpdb->terms} AS t
             INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_id = t.term_id
             WHERE tt.taxonomy = %s AND (t.slug = %s OR t.name = %s) LIMIT 1",
            $taxonomy, $value, $value
        ) );
        if ( $term_id ) {
            return intval( $term_id );
        }

        $inserted = wp_insert_term( $value, $taxonomy );
        if ( is_wp_error( $inserted ) ) {
            $term_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT t.term_id FROM {$wpdb->terms} AS t
                 INNER JOIN {$wpdb->term_taxonomy} AS tt ON tt.term_id = t.term_id
                 WHERE tt.taxonomy = %s AND t.name = %s LIMIT 1",
                $taxonomy, $value
            ) );
            return $term_id ? intval( $term_id ) : 0;
        }
        return intval( $inserted['term_id'] );
    }

    /** Crea/actualiza el post base y devuelve el ID (0 si falla). */
    public static function upsert_post( $input ) {
        $post_data = [
            'post_type'    => self::POST_TYPE,
            'post_title'   => isset( $input['tp_title'] ) ? sanitize_text_field( wp_unslash( $input['tp_title'] ) ) : '',
            'post_content' => isset( $input['tp_content'] ) ? wp_kses_post( wp_unslash( $input['tp_content'] ) ) : '',
            'post_status'  => isset( $input['tp_post_status'] ) && in_array( $input['tp_post_status'], [ 'publish', 'draft' ], true ) ? $input['tp_post_status'] : 'draft',
        ];

        $package_id = ! empty( $input['tp_package_id'] ) ? intval( $input['tp_package_id'] ) : 0;
        if ( $package_id ) {
            $post_data['ID'] = $package_id;
            return wp_update_post( $post_data );
        }
        return wp_insert_post( $post_data );
    }

    /** Copia meta + términos + imagen destacada + campos personalizados de un paquete a otro. */
    public static function copy_to( $from_id, $to_id ) {
        if ( ! $from_id || ! $to_id ) {
            return;
        }

        foreach ( array_values( self::META_MAP ) as $meta_key ) {
            $val = get_post_meta( $from_id, $meta_key, true );
            if ( $val !== '' ) {
                update_post_meta( $to_id, $meta_key, $val );
            }
        }
        foreach ( [ '_tp_tech_fields', '_tp_dates', self::META_TIPO_VIAJE ] as $meta_key ) {
            $val = get_post_meta( $from_id, $meta_key, true );
            if ( $val !== '' ) {
                update_post_meta( $to_id, $meta_key, $val );
            }
        }

        foreach ( TP_CustomFields::get_definitions() as $cf ) {
            $val = TP_CustomFields::get_field_value( $from_id, $cf['key'] );
            if ( $val !== '' ) {
                update_post_meta( $to_id, '_tp_cf_' . $cf['key'], $val );
            }
        }

        $thumb_id = get_post_thumbnail_id( $from_id );
        if ( $thumb_id ) {
            set_post_thumbnail( $to_id, $thumb_id );
        }

        foreach ( [ self::TAX_DESTINO, self::TAX_TIPOLOGIA ] as $taxonomy ) {
            $slugs    = self::get_term_slugs( $from_id, $taxonomy );
            $term_ids = [];
            foreach ( $slugs as $slug ) {
                $term_id = self::resolve_term_id( $slug, $taxonomy );
                if ( $term_id ) {
                    $term_ids[] = $term_id;
                }
            }
            if ( $term_ids ) {
                wp_set_object_terms( $to_id, $term_ids, $taxonomy, false );
            }
        }
    }
}
