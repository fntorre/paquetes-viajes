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

    const POST_TYPE       = 'paquete_viaje';
    const TAX_DESTINO     = 'destino';
    const TAX_TIPOLOGIA   = 'tipologia';
    const META_TIPO_VIAJE = '_tp_tipo_viaje';
    const META_PRICES     = '_tp_prices';
    const META_PRICING    = '_tp_pricing';
    const META_VIDEO      = '_tp_video_url';
    const META_MAP_LOCATION = '_tp_map_location';
    const META_WHATSAPP   = '_tp_whatsapp';
    const META_ITINERARY  = '_tp_itinerary';
    const META_SIMILAR_DESTINOS = '_tp_similar_destinos';
    const META_SEO_TITLE       = '_tp_seo_title';
    const META_SEO_DESCRIPTION = '_tp_seo_description';
    const META_SEO_IMAGE       = '_tp_seo_image';
    const META_SEO_NOINDEX     = '_tp_seo_noindex';

    const CURRENCY_SYMBOLS = [
        'USD' => 'US$',
        'ARS' => '$',
        'EUR' => '€',
        'BRL' => 'R$',
    ];

    public static function currency_symbol( $code ) {
        return self::CURRENCY_SYMBOLS[ $code ] ?? $code;
    }

    /** Mapeo campo de formulario -> meta key. */
    const META_MAP = [
        'tp_short_desc' => '_tp_short_desc',
        'tp_gallery'    => '_tp_gallery',
        'tp_video_url'  => '_tp_video_url',
    ];

    /* =========================================================
     * LECTURA
     * ========================================================= */

    /**
     * Devuelve el mejor precio de un paquete.
     * Prioridad: precio destacado > precio más bajo.
     * Retorna array [price, note, currency] o false si no hay precios.
     */
    public static function get_best_price( $post_id ) {
        $pricing = self::get_pricing( $post_id );
        if ( $pricing['price_adult'] !== '' && (float) $pricing['price_adult'] > 0 ) {
            return [
                'price'    => $pricing['price_adult'],
                'note'     => __( 'por persona', 'travel-packages' ),
                'currency' => $pricing['currency'],
                'pricing'  => true,
            ];
        }

        $prices = get_post_meta( $post_id, self::META_PRICES, true );
        if ( ! is_array( $prices ) || empty( $prices ) ) {
            $price    = get_post_meta( $post_id, '_tp_price', true );
            $note     = get_post_meta( $post_id, '_tp_price_note', true );
            $currency = get_post_meta( $post_id, '_tp_currency', true ) ?: 'USD';
            if ( $price !== '' && $price !== false ) {
                return [ 'price' => $price, 'note' => $note, 'currency' => $currency ];
            }
            return false;
        }
        foreach ( $prices as $p ) {
            if ( ! empty( $p['highlighted'] ) ) {
                return $p;
            }
        }
        $lowest = false;
        foreach ( $prices as $p ) {
            $val = (float) ( $p['price'] ?? 0 );
            if ( $val > 0 && ( $lowest === false || $val < (float)$lowest['price'] ) ) {
                $lowest = $p;
            }
        }
        return $lowest ?: false;
    }

    /** Devuelve todos los precios de un paquete. */
    public static function get_prices( $post_id ) {
        $prices = get_post_meta( $post_id, self::META_PRICES, true );
        if ( ! is_array( $prices ) ) {
            $pricing = self::get_pricing( $post_id );
            if ( $pricing['price_adult'] !== '' ) {
                return [];
            }
            $price    = get_post_meta( $post_id, '_tp_price', true );
            $note     = get_post_meta( $post_id, '_tp_price_note', true );
            $currency = get_post_meta( $post_id, '_tp_currency', true ) ?: 'USD';
            if ( $price !== '' && $price !== false ) {
                return [ [ 'price' => $price, 'note' => $note, 'currency' => $currency, 'highlighted' => true ] ];
            }
            return [];
        }
        return $prices;
    }

    /**
     * Precios por pasajero, suplemento y seña.
     * Devuelve siempre un array con todas las claves.
     */
    public static function get_pricing( $post_id ) {
        $defaults = [
            'currency'          => get_post_meta( $post_id, '_tp_currency', true ) ?: 'USD',
            'price_adult'       => '',
            'price_child'       => '',
            'single_supplement' => '',
            'deposit_type'      => 'none',
            'deposit_value'     => '',
            'deposit_note'      => '',
        ];

        $raw = get_post_meta( $post_id, self::META_PRICING, true );
        if ( ! is_array( $raw ) ) {
            return $defaults;
        }

        return array_merge( $defaults, $raw );
    }

    /** true si el paquete tiene configuración de precios por pasajero. */
    public static function has_pricing( $post_id ) {
        $pricing = self::get_pricing( $post_id );
        return $pricing['price_adult'] !== ''
            || $pricing['price_child'] !== ''
            || $pricing['single_supplement'] !== ''
            || $pricing['deposit_type'] !== 'none';
    }

    /**
     * Salidas normalizadas con cupos, disponibilidad, estado y precio efectivo.
     *
     * @param int  $post_id
     * @param bool $only_future   Solo salidas de hoy en adelante.
     * @param bool $include_closed Incluir salidas agotadas/cerradas.
     * @return array
     */
    public static function get_departures( $post_id, $only_future = false, $include_closed = true ) {
        $dates = get_post_meta( $post_id, '_tp_dates', true );
        if ( ! is_array( $dates ) ) {
            $dates = [];
        }

        $pricing    = self::get_pricing( $post_id );
        $today      = strtotime( 'today' );
        $out        = [];
        $booked_map = ( class_exists( 'TP_Reservations' ) && TP_Reservations::is_enabled() )
            ? TP_Reservations::get_booked_map( $post_id )
            : [];

        foreach ( $dates as $date ) {
            if ( ! is_array( $date ) ) {
                $date = [ 'from' => (string) $date, 'to' => '' ];
            }

            $from    = $date['from'] ?? '';
            $to      = $date['to'] ?? '';
            $from_ts = self::parse_date_ts( $from );
            $to_ts   = self::parse_date_ts( $to );
            $uid     = ! empty( $date['uid'] ) ? sanitize_key( (string) $date['uid'] ) : self::departure_uid( $from, $to );

            $seats               = ( isset( $date['seats'] ) && $date['seats'] !== '' ) ? (int) $date['seats'] : null;
            $seats_booked_manual = ( isset( $date['seats_booked'] ) && $date['seats_booked'] !== '' ) ? (int) $date['seats_booked'] : 0;
            $seats_booked_module = isset( $booked_map[ $uid ] ) ? (int) $booked_map[ $uid ] : 0;
            $seats_booked        = $seats_booked_manual + $seats_booked_module;
            $seats_left          = $seats !== null ? max( 0, $seats - $seats_booked ) : null;

            $cutoff    = $date['cutoff'] ?? '';
            $cutoff_ts = self::parse_date_ts( $cutoff );

            $manual = $date['status'] ?? 'auto';
            if ( ! in_array( $manual, [ 'auto', 'open', 'soldout', 'closed' ], true ) ) {
                $manual = 'auto';
            }

            $status = $manual;
            if ( $manual === 'auto' ) {
                if ( $from_ts && $from_ts < $today ) {
                    $status = 'closed';
                } elseif ( $seats_left !== null && $seats_left <= 0 ) {
                    $status = 'soldout';
                } elseif ( $cutoff_ts && $cutoff_ts < $today ) {
                    $status = 'closed';
                } else {
                    $status = 'open';
                }
            }

            if ( $only_future && $from_ts && $from_ts < $today ) {
                continue;
            }
            if ( ! $include_closed && $status !== 'open' ) {
                continue;
            }

            $price_adult = isset( $date['price_adult'] ) && $date['price_adult'] !== '' ? $date['price_adult'] : $pricing['price_adult'];
            $price_child = isset( $date['price_child'] ) && $date['price_child'] !== '' ? $date['price_child'] : $pricing['price_child'];
            $currency    = ! empty( $date['currency'] ) ? $date['currency'] : $pricing['currency'];

            $out[] = array_merge( $date, [
                'uid'                    => $uid,
                'from'                   => $from,
                'to'                     => $to,
                'from_ts'                => $from_ts,
                'to_ts'                  => $to_ts,
                'seats'                  => $seats,
                'seats_booked'           => $seats_booked,
                'seats_booked_manual'    => $seats_booked_manual,
                'seats_booked_reservations' => $seats_booked_module,
                'seats_left'             => $seats_left,
                'min_pax'       => ( isset( $date['min_pax'] ) && $date['min_pax'] !== '' ) ? (int) $date['min_pax'] : null,
                'max_pax'       => ( isset( $date['max_pax'] ) && $date['max_pax'] !== '' ) ? (int) $date['max_pax'] : null,
                'cutoff'        => $cutoff,
                'cutoff_ts'     => $cutoff_ts,
                'status'        => $status,
                'manual_status' => $manual,
                'price_adult'   => $price_adult,
                'price_child'   => $price_child,
                'currency'      => $currency,
            ] );
        }

        usort( $out, function( $a, $b ) {
            $ta = $a['from_ts'] ?: PHP_INT_MAX;
            $tb = $b['from_ts'] ?: PHP_INT_MAX;
            return $ta <=> $tb;
        } );

        return $out;
    }

    /** Próxima salida futura y abierta, o null. */
    public static function get_next_departure( $post_id ) {
        foreach ( self::get_departures( $post_id, true, false ) as $departure ) {
            return $departure;
        }
        return null;
    }

    /** Identificador estable de una salida a partir de sus fechas. */
    public static function departure_uid( $from, $to = '' ) {
        $from = trim( (string) $from );
        $to   = trim( (string) $to );
        return substr( md5( $from . '|' . $to ), 0, 12 );
    }

    /** Agrega uid a salidas que no lo tengan (datos previos al módulo). */
    public static function add_departure_uids( $dates ) {
        if ( ! is_array( $dates ) ) {
            return [];
        }

        foreach ( $dates as &$date ) {
            if ( ! is_array( $date ) ) {
                $date = [ 'from' => (string) $date, 'to' => '' ];
            }
            if ( empty( $date['uid'] ) ) {
                $date['uid'] = self::departure_uid( $date['from'] ?? '', $date['to'] ?? '' );
            }
        }
        unset( $date );

        return $dates;
    }

    /** Convierte dd/mm/yyyy o yyyy-mm-dd a timestamp (0 si no es válida). */
    private static function parse_date_ts( $value ) {
        $value = trim( (string) $value );
        if ( $value === '' ) {
            return 0;
        }

        if ( preg_match( '#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $value, $m ) ) {
            return strtotime( sprintf( '%04d-%02d-%02d', $m[3], $m[2], $m[1] ) ) ?: 0;
        }

        return strtotime( str_replace( '/', '-', $value ) ) ?: 0;
    }

    /** Normaliza montos que llegan del formulario como texto. */
    private static function sanitize_amount( $value ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }
        $value = sanitize_text_field( wp_unslash( (string) $value ) );
        if ( $value === '' ) {
            return '';
        }
        return (string) (float) str_replace( ',', '.', $value );
    }

    public static function get_meta( $post_id ) {
        $data = [];
        foreach ( self::META_MAP as $field => $meta_key ) {
            $data[ $field ] = get_post_meta( $post_id, $meta_key, true );
        }
        $data['tp_slider']   = get_post_meta( $post_id, '_tp_slider', true );
        $data['tp_featured'] = get_post_meta( $post_id, '_tp_featured', true );
        $data['tp_whatsapp'] = get_post_meta( $post_id, self::META_WHATSAPP, true );
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

    /** URL de video configurada para el paquete. */
    public static function get_video_url( $post_id ) {
        $url = esc_url_raw( (string) get_post_meta( $post_id, self::META_VIDEO, true ) );
        return $url ?: '';
    }

    /**
     * Datos del video listos para incrustar.
     * Devuelve [] si no hay video o la URL no es compatible.
     */
    public static function get_video_data( $post_id ) {
        $url = self::get_video_url( $post_id );
        if ( $url === '' ) {
            return [];
        }

        $type  = '';
        $embed = '';

        if ( preg_match( '~youtu\.be/([A-Za-z0-9_-]{6,})~i', $url, $m ) ) {
            $type  = 'youtube';
            $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1];
        } elseif ( preg_match( '~youtube\.com/(?:watch\?v=|embed/|shorts/|v/)([A-Za-z0-9_-]{6,})~i', $url, $m ) ) {
            $type  = 'youtube';
            $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1];
        } elseif ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~i', $url, $m ) ) {
            $type  = 'vimeo';
            $embed = 'https://player.vimeo.com/video/' . $m[1];
        } elseif ( preg_match( '~\.(mp4|webm|ogg)(\?.*)?$~i', $url ) ) {
            $type  = 'file';
            $embed = $url;
        }

        if ( $type === '' ) {
            return [];
        }

        return [
            'type'     => $type,
            'url'      => $embed,
            'original' => $url,
        ];
    }

    /** Ubicación del hotel guardada (dirección, coordenadas, URL o embed de Google Maps). */
    public static function get_map_location( $post_id ) {
        $value = get_post_meta( $post_id, self::META_MAP_LOCATION, true );
        return is_string( $value ) ? trim( $value ) : '';
    }

    /** URL de Google Maps lista para usar en un iframe. */
    public static function get_map_embed_url( $post_id ) {
        return self::map_embed_url( self::get_map_location( $post_id ) );
    }

    /**
     * Convierte una dirección, coordenadas, enlace o iframe de Google Maps
     * en una URL segura para incrustar.
     */
    public static function map_embed_url( $location ) {
        $location = trim( (string) $location );
        if ( $location === '' ) {
            return '';
        }

        if ( ! preg_match( '#^https?://#i', $location ) ) {
            return 'https://www.google.com/maps?q=' . rawurlencode( $location ) . '&output=embed';
        }

        $url = esc_url_raw( $location );
        if ( ! $url ) {
            return '';
        }

        $parts = wp_parse_url( $url );
        if ( ! is_array( $parts ) ) {
            return '';
        }

        $host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
        $path  = isset( $parts['path'] ) ? $parts['path'] : '';
        $query = [];
        if ( ! empty( $parts['query'] ) ) {
            parse_str( $parts['query'], $query );
        }

        $is_google = (bool) preg_match( '/(^|\.)google\.[a-z.]+$/', $host );
        $is_short  = (bool) preg_match( '/(^|\.)goo\.gl$/', $host );

        // Enlace de inserción de Google Maps: ya es apto para iframe.
        if ( $is_google && ( false !== strpos( $path, '/maps/embed' ) || false !== strpos( $url, 'output=embed' ) ) ) {
            return $url;
        }

        // Coordenadas presentes en enlaces de lugar o de inserción.
        if ( preg_match( '/@(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/', $url, $m ) ) {
            return 'https://www.google.com/maps?q=' . $m[1] . ',' . $m[2] . '&output=embed';
        }
        if ( preg_match( '/!3d(-?\d{1,3}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/', $url, $m ) ) {
            return 'https://www.google.com/maps?q=' . $m[1] . ',' . $m[2] . '&output=embed';
        }

        // Parámetros de búsqueda habituales.
        foreach ( [ 'q', 'query', 'destination' ] as $key ) {
            if ( ! empty( $query[ $key ] ) ) {
                return 'https://www.google.com/maps?q=' . rawurlencode( (string) $query[ $key ] ) . '&output=embed';
            }
        }

        if ( $is_google || $is_short ) {
            if ( preg_match( '#/maps/place/([^/@]+)#i', $path, $m ) ) {
                $place = str_replace( '+', ' ', urldecode( $m[1] ) );
                return 'https://www.google.com/maps?q=' . rawurlencode( $place ) . '&output=embed';
            }
        }

        // Último recurso: usar el enlace como término de búsqueda.
        return 'https://www.google.com/maps?q=' . rawurlencode( $url ) . '&output=embed';
    }

    /** Sanitiza el valor ingresado en el campo de ubicación (acepta iframe). */
    public static function sanitize_map_location( $value ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = trim( (string) $value );
        if ( $value === '' ) {
            return '';
        }

        if ( preg_match( '/<iframe\b[^>]*\bsrc\s*=\s*(["\'])(.*?)\1/is', $value, $m ) ) {
            $value = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
        } elseif ( preg_match( '/<iframe\b[^>]*\bsrc\s*=\s*([^\s>]+)/is', $value, $m ) ) {
            $value = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
        }

        $value = trim( wp_strip_all_tags( $value ) );
        if ( $value === '' ) {
            return '';
        }

        if ( preg_match( '#^https?://#i', $value ) ) {
            return esc_url_raw( $value );
        }

        return sanitize_text_field( $value );
    }

    /** Número de WhatsApp del paquete; si está vacío usa el general de Ajustes. */
    public static function get_whatsapp_number( $post_id ) {
        $number = preg_replace( '/\D+/', '', (string) get_post_meta( $post_id, self::META_WHATSAPP, true ) );
        if ( $number === '' && class_exists( 'TP_Settings' ) ) {
            $number = TP_Settings::get_whatsapp_number();
        }
        return $number;
    }

    /** URL de WhatsApp del paquete con fallback al número general. */
    public static function whatsapp_url( $post_id, $message = '' ) {
        if ( ! class_exists( 'TP_Settings' ) ) {
            return '';
        }
        return TP_Settings::whatsapp_url( $message, self::get_whatsapp_number( $post_id ) );
    }

    /** Itinerario día por día normalizado. */
    public static function get_itinerary( $post_id ) {
        $rows = get_post_meta( $post_id, self::META_ITINERARY, true );
        if ( ! is_array( $rows ) ) {
            return [];
        }

        $out = [];
        foreach ( $rows as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $day   = isset( $row['day'] ) ? sanitize_text_field( $row['day'] ) : '';
            $title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
            $desc  = isset( $row['desc'] ) ? sanitize_textarea_field( $row['desc'] ) : '';
            if ( $day === '' && $title === '' && $desc === '' ) {
                continue;
            }
            $out[] = [ 'day' => $day, 'title' => $title, 'desc' => $desc ];
        }

        return $out;
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
     * Busca paquetes por texto de forma precisa y devuelve sus IDs ordenados
     * por relevancia. Considera título, descripción corta, contenido y los
     * nombres de las taxonomías destino/tipología.
     *
     * @param string $term  Texto a buscar.
     * @param int    $limit Cantidad máxima de resultados (-1 = todos).
     * @return int[]
     */
    public static function search_package_ids( $term, $limit = 20 ) {
        $term = trim( (string) $term );
        if ( $term === '' ) {
            return [];
        }

        $normalize = static function( $value ) {
            return strtolower( remove_accents( (string) $value ) );
        };
        $needle = $normalize( $term );

        $base = [
            'post_type'           => self::POST_TYPE,
            'post_status'         => 'publish',
            'posts_per_page'      => 100,
            'fields'              => 'ids',
            'no_found_rows'       => true,
            'ignore_sticky_posts' => true,
            'orderby'             => 'title',
            'order'               => 'ASC',
        ];

        $ids = [];

        // Búsqueda nativa: título, extracto y contenido.
        $query = new WP_Query( array_merge( $base, [ 's' => $term ] ) );
        foreach ( $query->posts as $id ) {
            $ids[ (int) $id ] = (int) $id;
        }

        // Descripción corta.
        $query = new WP_Query( array_merge( $base, [
            'meta_query' => [[
                'key'     => '_tp_short_desc',
                'value'   => $term,
                'compare' => 'LIKE',
            ]],
        ] ) );
        foreach ( $query->posts as $id ) {
            $ids[ (int) $id ] = (int) $id;
        }

        // Destinos y tipologías cuyo nombre coincide con el texto.
        $tax_query = [ 'relation' => 'OR' ];
        foreach ( [ self::TAX_DESTINO, self::TAX_TIPOLOGIA ] as $taxonomy ) {
            $terms = get_terms([
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'search'     => $term,
                'fields'     => 'ids',
            ]);
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                $tax_query[] = [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => array_map( 'intval', $terms ),
                ];
            }
        }
        if ( count( $tax_query ) > 1 ) {
            $query = new WP_Query( array_merge( $base, [ 'tax_query' => $tax_query ] ) );
            foreach ( $query->posts as $id ) {
                $ids[ (int) $id ] = (int) $id;
            }
        }

        if ( empty( $ids ) ) {
            return [];
        }

        $scored = [];
        foreach ( $ids as $id ) {
            $post = get_post( $id );
            if ( ! $post ) {
                continue;
            }

            $score = 0;
            $title = $normalize( $post->post_title );

            if ( $title === $needle ) {
                $score += 120;
            } elseif ( strpos( $title, $needle ) === 0 ) {
                $score += 90;
            } elseif ( strpos( $title, $needle ) !== false ) {
                $score += 70;
            }

            $short_desc = $normalize( get_post_meta( $id, '_tp_short_desc', true ) );
            if ( $short_desc !== '' && strpos( $short_desc, $needle ) !== false ) {
                $score += strpos( $short_desc, $needle ) === 0 ? 55 : 45;
            }

            foreach ( [ self::TAX_DESTINO, self::TAX_TIPOLOGIA ] as $taxonomy ) {
                foreach ( self::get_term_names( $id, $taxonomy ) as $name ) {
                    $name_norm = $normalize( $name );
                    if ( $name_norm === $needle ) {
                        $score += 80;
                    } elseif ( strpos( $name_norm, $needle ) === 0 ) {
                        $score += 55;
                    } elseif ( strpos( $name_norm, $needle ) !== false ) {
                        $score += 35;
                    }
                }
            }

            $excerpt = $normalize( $post->post_excerpt );
            if ( $excerpt !== '' && strpos( $excerpt, $needle ) !== false ) {
                $score += 30;
            }

            $content = $normalize( $post->post_content );
            if ( $content !== '' && strpos( $content, $needle ) !== false ) {
                $score += 20;
            }

            if ( $score <= 0 ) {
                continue;
            }

            $scored[] = [
                'id'    => (int) $id,
                'score' => $score,
                'title' => $post->post_title,
            ];
        }

        usort( $scored, static function( $a, $b ) {
            if ( $a['score'] === $b['score'] ) {
                return strcasecmp( $a['title'], $b['title'] );
            }
            return $b['score'] <=> $a['score'];
        } );

        $result = array_column( $scored, 'id' );

        if ( $limit > 0 ) {
            $result = array_slice( $result, 0, (int) $limit );
        }

        return array_map( 'intval', $result );
    }

    /** IDs de destinos elegidos manualmente para el bloque "Destinos similares". */
    public static function get_similar_destinos( $post_id ) {
        $term_ids = get_post_meta( $post_id, self::META_SIMILAR_DESTINOS, true );
        if ( ! is_array( $term_ids ) ) {
            return [];
        }
        return array_values( array_filter( array_map( 'intval', $term_ids ) ) );
    }

    /** Nombres de los destinos elegidos manualmente (para exportar/importar). */
    public static function get_similar_destino_names( $post_id ) {
        $names = [];
        foreach ( self::get_similar_destinos( $post_id ) as $term_id ) {
            $term = get_term( $term_id, self::TAX_DESTINO );
            if ( $term && ! is_wp_error( $term ) ) {
                $names[] = $term->name;
            }
        }
        return $names;
    }

    /**
     * Paquetes para el carrusel "Destinos similares".
     * Usa los destinos elegidos en el admin o, si no hay, el destino del paquete.
     */
    public static function get_similar_packages( $post_id, $limit = 8 ) {
        $post_id = (int) $post_id;
        $limit   = max( 1, (int) $limit );
        $term_ids = self::get_similar_destinos( $post_id );

        if ( empty( $term_ids ) ) {
            $current_terms = wp_get_post_terms( $post_id, self::TAX_DESTINO, [ 'fields' => 'ids' ] );
            $term_ids      = is_wp_error( $current_terms ) ? [] : array_map( 'intval', $current_terms );
        }

        if ( empty( $term_ids ) ) {
            return [];
        }

        $query = new WP_Query( [
            'post_type'           => self::POST_TYPE,
            'post_status'         => 'publish',
            'posts_per_page'      => $limit,
            'post__not_in'        => [ $post_id ],
            'ignore_sticky_posts' => true,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'tax_query'           => [
                [
                    'taxonomy' => self::TAX_DESTINO,
                    'field'    => 'term_id',
                    'terms'    => $term_ids,
                ],
            ],
        ] );

        return $query->posts;
    }

    /**
     * URL pública (o de vista previa para borradores) del paquete.
     */
    public static function get_view_url( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            return '';
        }

        if ( 'publish' === $post->post_status ) {
            return get_permalink( $post );
        }

        $preview = get_preview_post_link( $post );
        return $preview ?: get_permalink( $post );
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

        $dates = self::add_departure_uids( get_post_meta( $post->ID, '_tp_dates', true ) );
        if ( class_exists( 'TP_Reservations' ) && TP_Reservations::is_enabled() ) {
            $booked = TP_Reservations::get_booked_map( $post->ID );
            foreach ( $dates as &$date ) {
                $date['reservations_booked'] = isset( $booked[ $date['uid'] ] ) ? (int) $booked[ $date['uid'] ] : 0;
            }
            unset( $date );
        }

        $seo_image_raw = get_post_meta( $post->ID, self::META_SEO_IMAGE, true );
        $seo_image_id  = is_scalar( $seo_image_raw ) ? (int) $seo_image_raw : 0;

        return [
            'ID'               => $post->ID,
            'post_title'       => $post->post_title,
            'post_content'     => $post->post_content,
            'post_status'      => $post->post_status,
            'view_url'         => self::get_view_url( $post->ID ),
            'tp_short_desc'    => get_post_meta( $post->ID, '_tp_short_desc', true ),
            'tp_prices'        => self::get_prices( $post->ID ),
            'tp_pricing'       => self::get_pricing( $post->ID ),
            'tp_slider'        => get_post_meta( $post->ID, '_tp_slider', true ),
            'tp_featured'      => get_post_meta( $post->ID, '_tp_featured', true ),
            'tp_featured_image'=> get_post_thumbnail_id( $post->ID ) ?: '',
            'tp_gallery'       => self::get_gallery_items( $post->ID ),
            'tp_video_url'     => self::get_video_url( $post->ID ),
            'tp_map_location'  => self::get_map_location( $post->ID ),
            'tp_whatsapp'      => get_post_meta( $post->ID, self::META_WHATSAPP, true ),
            'tp_itinerary'     => self::get_itinerary( $post->ID ),
            'tp_dates'         => $dates,
            'tp_tech_fields'   => get_post_meta( $post->ID, '_tp_tech_fields', true ),
            'tp_custom_fields' => TP_CustomFields::get_package_data( $post->ID ),
            'tp_cf_order'      => get_post_meta( $post->ID, '_tp_cf_order', true ),
            'tp_tipo_viaje'    => get_post_meta( $post->ID, self::META_TIPO_VIAJE, true ),
            'tp_similar_destinos' => self::get_similar_destinos( $post->ID ),
            'tp_seo_title'       => get_post_meta( $post->ID, self::META_SEO_TITLE, true ),
            'tp_seo_description' => get_post_meta( $post->ID, self::META_SEO_DESCRIPTION, true ),
            'tp_seo_image'       => $seo_image_id,
            'tp_seo_image_url'   => $seo_image_id ? ( wp_get_attachment_image_url( $seo_image_id, 'medium' ) ?: '' ) : '',
            'tp_seo_noindex'     => get_post_meta( $post->ID, self::META_SEO_NOINDEX, true ),
            'destinos'         => self::get_term_slugs( $post->ID, self::TAX_DESTINO ),
            'tipologias'       => self::get_term_slugs( $post->ID, self::TAX_TIPOLOGIA ),
        ];
    }

    /* =========================================================
     * ESCRITURA
     * ========================================================= */

    /**
     * Guarda todos los campos de un paquete a partir de $_POST.
     * Usado por TP_Admin_Handlers::save_package() y por la importación.
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
            if ( isset( $input[ $field ] ) && is_scalar( $input[ $field ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( wp_unslash( (string) $input[ $field ] ) ) );
            }
        }

        update_post_meta( $post_id, '_tp_slider',   empty( $input['tp_slider'] )   ? '0' : '1' );
        update_post_meta( $post_id, '_tp_featured', empty( $input['tp_featured'] ) ? '0' : '1' );

        $seo_title = isset( $input['tp_seo_title'] ) && is_scalar( $input['tp_seo_title'] ) ? sanitize_text_field( wp_unslash( (string) $input['tp_seo_title'] ) ) : '';
        if ( $seo_title !== '' ) {
            update_post_meta( $post_id, self::META_SEO_TITLE, $seo_title );
        } else {
            delete_post_meta( $post_id, self::META_SEO_TITLE );
        }

        $seo_desc = isset( $input['tp_seo_description'] ) && is_scalar( $input['tp_seo_description'] ) ? sanitize_textarea_field( wp_unslash( (string) $input['tp_seo_description'] ) ) : '';
        if ( $seo_desc !== '' ) {
            update_post_meta( $post_id, self::META_SEO_DESCRIPTION, $seo_desc );
        } else {
            delete_post_meta( $post_id, self::META_SEO_DESCRIPTION );
        }

        $seo_image = isset( $input['tp_seo_image'] ) && is_scalar( $input['tp_seo_image'] ) ? absint( $input['tp_seo_image'] ) : 0;
        if ( $seo_image && wp_attachment_is_image( $seo_image ) ) {
            update_post_meta( $post_id, self::META_SEO_IMAGE, $seo_image );
        } else {
            delete_post_meta( $post_id, self::META_SEO_IMAGE );
        }

        if ( ! empty( $input['tp_seo_noindex'] ) ) {
            update_post_meta( $post_id, self::META_SEO_NOINDEX, '1' );
        } else {
            delete_post_meta( $post_id, self::META_SEO_NOINDEX );
        }

        $tipo_viaje = isset( $input['tp_tipo_viaje'] ) && is_scalar( $input['tp_tipo_viaje'] ) ? sanitize_text_field( (string) $input['tp_tipo_viaje'] ) : '';
        if ( in_array( $tipo_viaje, [ 'nacional', 'internacional' ], true ) ) {
            update_post_meta( $post_id, self::META_TIPO_VIAJE, $tipo_viaje );
        } else {
            delete_post_meta( $post_id, self::META_TIPO_VIAJE );
        }

        $cf_order = isset( $input['tp_cf_order'] ) && is_scalar( $input['tp_cf_order'] ) ? sanitize_text_field( wp_unslash( (string) $input['tp_cf_order'] ) ) : '';
        update_post_meta( $post_id, '_tp_cf_order', $cf_order );

        $video_url = isset( $input['tp_video_url'] ) && is_scalar( $input['tp_video_url'] ) ? esc_url_raw( wp_unslash( (string) $input['tp_video_url'] ) ) : '';
        if ( $video_url !== '' ) {
            update_post_meta( $post_id, self::META_VIDEO, $video_url );
        } else {
            delete_post_meta( $post_id, self::META_VIDEO );
        }

        $map_location = isset( $input['tp_map_location'] ) ? self::sanitize_map_location( wp_unslash( $input['tp_map_location'] ) ) : '';
        if ( $map_location !== '' ) {
            update_post_meta( $post_id, self::META_MAP_LOCATION, $map_location );
        } else {
            delete_post_meta( $post_id, self::META_MAP_LOCATION );
        }

        $whatsapp = isset( $input['tp_whatsapp_number'] ) && is_scalar( $input['tp_whatsapp_number'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $input['tp_whatsapp_number'] ) ) : '';
        if ( $whatsapp !== '' ) {
            update_post_meta( $post_id, self::META_WHATSAPP, $whatsapp );
        } else {
            delete_post_meta( $post_id, self::META_WHATSAPP );
        }

        $itinerary = self::sanitize_itinerary( $input );
        if ( ! empty( $itinerary ) ) {
            update_post_meta( $post_id, self::META_ITINERARY, $itinerary );
        } else {
            delete_post_meta( $post_id, self::META_ITINERARY );
        }

        $similar_destinos = isset( $input['tp_similar_destinos'] ) ? (array) $input['tp_similar_destinos'] : [];
        $similar_destinos = array_values( array_filter( array_map( 'intval', $similar_destinos ) ) );
        if ( ! empty( $similar_destinos ) ) {
            update_post_meta( $post_id, self::META_SIMILAR_DESTINOS, $similar_destinos );
        } else {
            delete_post_meta( $post_id, self::META_SIMILAR_DESTINOS );
        }

        self::save_prices( $post_id, $input );
        self::save_pricing( $post_id, $input );
    }

    /** Normaliza el itinerario recibido del formulario. */
    private static function sanitize_itinerary( $input ) {
        $days   = isset( $input['tp_itinerary_day'] ) ? (array) $input['tp_itinerary_day'] : [];
        $titles = isset( $input['tp_itinerary_title'] ) ? (array) $input['tp_itinerary_title'] : [];
        $descs  = isset( $input['tp_itinerary_desc'] ) ? (array) $input['tp_itinerary_desc'] : [];

        $count = max( count( $days ), count( $titles ), count( $descs ) );
        $rows  = [];

        for ( $i = 0; $i < $count; $i++ ) {
            $day   = isset( $days[ $i ] ) && is_scalar( $days[ $i ] ) ? sanitize_text_field( wp_unslash( (string) $days[ $i ] ) ) : '';
            $title = isset( $titles[ $i ] ) && is_scalar( $titles[ $i ] ) ? sanitize_text_field( wp_unslash( (string) $titles[ $i ] ) ) : '';
            $desc  = isset( $descs[ $i ] ) && is_scalar( $descs[ $i ] ) ? sanitize_textarea_field( wp_unslash( (string) $descs[ $i ] ) ) : '';

            if ( $day === '' && $title === '' && $desc === '' ) {
                continue;
            }
            if ( $day === '' ) {
                $day = sprintf( __( 'Día %d', 'travel-packages' ), count( $rows ) + 1 );
            }

            $rows[] = [ 'day' => $day, 'title' => $title, 'desc' => $desc ];
        }

        return $rows;
    }

    /** Guarda precios por pasajero, suplemento y seña. */
    private static function save_pricing( $post_id, $input ) {
        $deposit_type = isset( $input['tp_deposit_type'] ) && is_scalar( $input['tp_deposit_type'] ) ? sanitize_key( (string) $input['tp_deposit_type'] ) : 'none';
        if ( ! in_array( $deposit_type, [ 'none', 'percent', 'amount' ], true ) ) {
            $deposit_type = 'none';
        }

        $pricing = [
            'currency'          => isset( $input['tp_currency_base'] ) && is_scalar( $input['tp_currency_base'] ) ? sanitize_text_field( wp_unslash( (string) $input['tp_currency_base'] ) ) : 'USD',
            'price_adult'       => self::sanitize_amount( $input['tp_price_adult'] ?? '' ),
            'price_child'       => self::sanitize_amount( $input['tp_price_child'] ?? '' ),
            'single_supplement' => self::sanitize_amount( $input['tp_single_supplement'] ?? '' ),
            'deposit_type'      => $deposit_type,
            'deposit_value'     => self::sanitize_amount( $input['tp_deposit_value'] ?? '' ),
            'deposit_note'      => isset( $input['tp_deposit_note'] ) && is_scalar( $input['tp_deposit_note'] ) ? sanitize_text_field( wp_unslash( (string) $input['tp_deposit_note'] ) ) : '',
        ];

        if ( ! in_array( $pricing['currency'], array_keys( self::CURRENCY_SYMBOLS ), true ) ) {
            $pricing['currency'] = 'USD';
        }

        $has_pricing = $pricing['price_adult'] !== ''
            || $pricing['price_child'] !== ''
            || $pricing['single_supplement'] !== ''
            || $pricing['deposit_type'] !== 'none';

        if ( $has_pricing ) {
            update_post_meta( $post_id, self::META_PRICING, $pricing );
        } else {
            delete_post_meta( $post_id, self::META_PRICING );
        }

        // Mantener sincronizado el meta de precio usado por filtros/orden.
        if ( $pricing['price_adult'] !== '' ) {
            update_post_meta( $post_id, '_tp_price', $pricing['price_adult'] );
            update_post_meta( $post_id, '_tp_currency', $pricing['currency'] );
            if ( ! get_post_meta( $post_id, '_tp_price_note', true ) ) {
                update_post_meta( $post_id, '_tp_price_note', __( 'por persona', 'travel-packages' ) );
            }
        }
    }

    /**
     * Guarda el array de precios desde $_POST.
     * Sincroniza _tp_price/_tp_price_note/_tp_currency con el precio destacado.
     */
    private static function save_prices( $post_id, $input ) {
        $raw_prices = isset( $input['tp_price_item'] ) && is_array( $input['tp_price_item'] ) ? $input['tp_price_item'] : [];
        $raw_notes  = isset( $input['tp_price_note_item'] ) && is_array( $input['tp_price_note_item'] ) ? $input['tp_price_note_item'] : [];
        $raw_currs  = isset( $input['tp_currency_item'] ) && is_array( $input['tp_currency_item'] ) ? $input['tp_currency_item'] : [];
        $raw_high   = isset( $input['tp_price_highlighted'] ) && is_array( $input['tp_price_highlighted'] ) ? $input['tp_price_highlighted'] : [];

        // Compatibilidad con datos legacy de precio único.
        if ( empty( $raw_prices ) && isset( $input['tp_price'] ) && $input['tp_price'] !== '' ) {
            $raw_prices = [ $input['tp_price'] ];
            $raw_notes  = [ $input['tp_price_note'] ?? '' ];
            $raw_currs  = [ $input['tp_currency'] ?? 'USD' ];
            $raw_high   = [ '0' ];
        }

        $prices = [];
        $count  = max( count( $raw_prices ), count( $raw_notes ), count( $raw_currs ) );
        for ( $i = 0; $i < $count; $i++ ) {
            $raw_price = $raw_prices[ $i ] ?? '';
            if ( ! is_scalar( $raw_price ) ) {
                continue;
            }
            $p = sanitize_text_field( wp_unslash( (string) $raw_price ) );
            if ( $p === '' ) continue;

            $raw_note     = $raw_notes[ $i ] ?? '';
            $raw_currency = $raw_currs[ $i ] ?? '';
            $currency     = is_scalar( $raw_currency ) ? sanitize_text_field( wp_unslash( (string) $raw_currency ) ) : '';
            if ( ! in_array( $currency, array_keys( self::CURRENCY_SYMBOLS ), true ) ) {
                $currency = 'USD';
            }

            $prices[] = [
                'price'       => $p,
                'note'        => is_scalar( $raw_note ) ? sanitize_text_field( wp_unslash( (string) $raw_note ) ) : '',
                'currency'    => $currency,
                'highlighted' => in_array( (string) $i, $raw_high ),
            ];
        }

        update_post_meta( $post_id, self::META_PRICES, $prices );

        $best = false;
        foreach ( $prices as $p ) {
            if ( ! empty( $p['highlighted'] ) ) { $best = $p; break; }
        }
        if ( ! $best && ! empty( $prices ) ) {
            $best = $prices[0];
        }
        if ( $best ) {
            update_post_meta( $post_id, '_tp_price', $best['price'] );
            update_post_meta( $post_id, '_tp_price_note', $best['note'] );
            update_post_meta( $post_id, '_tp_currency', $best['currency'] );
        }
    }

    private static function save_featured_image( $post_id, $input ) {
        if ( isset( $input['tp_featured_image'] ) && is_scalar( $input['tp_featured_image'] ) && $input['tp_featured_image'] !== '' ) {
            $fid = intval( $input['tp_featured_image'] );
            if ( $fid && get_post_type( $fid ) === 'attachment' ) {
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
            if ( ! is_scalar( $key ) ) {
                continue;
            }
            $k = sanitize_text_field( (string) $key );
            $raw_val = $vals[ $index ] ?? '';
            $v = is_scalar( $raw_val ) ? sanitize_text_field( (string) $raw_val ) : '';
            if ( $k ) {
                $tech[] = [ 'key' => $k, 'val' => $v ];
            }
        }
        return $tech;
    }

    /** Fechas: pares from/to + cupos, estado y precios por fecha. Soporta legacy. */
    private static function sanitize_dates( $input ) {
        $array_keys = [
            'uid'          => 'tp_dates_uid',
            'from'         => 'tp_dates_from',
            'to'           => 'tp_dates_to',
            'seats'        => 'tp_dates_seats',
            'seats_booked' => 'tp_dates_seats_booked',
            'min_pax'      => 'tp_dates_min_pax',
            'max_pax'      => 'tp_dates_max_pax',
            'cutoff'       => 'tp_dates_cutoff',
            'status'       => 'tp_dates_status',
            'price_adult'  => 'tp_dates_price_adult',
            'price_child'  => 'tp_dates_price_child',
            'currency'     => 'tp_dates_currency',
        ];

        $columns = [];
        foreach ( $array_keys as $field => $input_key ) {
            $columns[ $field ] = isset( $input[ $input_key ] ) ? (array) $input[ $input_key ] : [];
        }

        $dates = [];
        $max   = 0;
        foreach ( $columns as $values ) {
            $max = max( $max, count( $values ) );
        }

        for ( $i = 0; $i < $max; $i++ ) {
            $raw_from = $columns['from'][ $i ] ?? '';
            $raw_to   = $columns['to'][ $i ] ?? '';
            $f = is_scalar( $raw_from ) ? sanitize_text_field( wp_unslash( (string) $raw_from ) ) : '';
            $t = is_scalar( $raw_to ) ? sanitize_text_field( wp_unslash( (string) $raw_to ) ) : '';

            if ( ! $f && ! $t ) {
                continue;
            }

            if ( $f && $t ) {
                $f_ts = self::parse_date_ts( $f );
                $t_ts = self::parse_date_ts( $t );
                if ( $f_ts && $t_ts && $t_ts < $f_ts ) {
                    $t = $f;
                }
            }

            $raw_status = $columns['status'][ $i ] ?? '';
            $status = is_scalar( $raw_status ) ? sanitize_key( (string) $raw_status ) : 'auto';
            if ( ! in_array( $status, [ 'auto', 'open', 'soldout', 'closed' ], true ) ) {
                $status = 'auto';
            }

            $raw_currency = $columns['currency'][ $i ] ?? '';
            $currency = is_scalar( $raw_currency ) ? sanitize_text_field( wp_unslash( (string) $raw_currency ) ) : '';
            if ( $currency && ! in_array( $currency, array_keys( self::CURRENCY_SYMBOLS ), true ) ) {
                $currency = '';
            }

            $raw_uid = $columns['uid'][ $i ] ?? '';
            $uid = is_scalar( $raw_uid ) ? sanitize_key( (string) $raw_uid ) : '';
            if ( $uid === '' ) {
                $uid = self::departure_uid( $f, $t );
            }

            $dates[] = [
                'uid'          => $uid,
                'from'         => $f,
                'to'           => $t,
                'seats'        => self::sanitize_int_or_empty( $columns['seats'][ $i ] ?? '' ),
                'seats_booked' => self::sanitize_int_or_empty( $columns['seats_booked'][ $i ] ?? '' ),
                'min_pax'      => self::sanitize_int_or_empty( $columns['min_pax'][ $i ] ?? '' ),
                'max_pax'      => self::sanitize_int_or_empty( $columns['max_pax'][ $i ] ?? '' ),
                'cutoff'       => ( isset( $columns['cutoff'][ $i ] ) && is_scalar( $columns['cutoff'][ $i ] ) ) ? sanitize_text_field( wp_unslash( (string) $columns['cutoff'][ $i ] ) ) : '',
                'status'       => $status,
                'price_adult'  => self::sanitize_amount( $columns['price_adult'][ $i ] ?? '' ),
                'price_child'  => self::sanitize_amount( $columns['price_child'][ $i ] ?? '' ),
                'currency'     => $currency,
            ];
        }

        // Soporte legacy (campo único tp_dates).
        if ( empty( $dates ) && isset( $input['tp_dates'] ) ) {
            foreach ( (array) $input['tp_dates'] as $d ) {
                if ( ! is_scalar( $d ) ) {
                    continue;
                }
                $d = sanitize_text_field( wp_unslash( (string) $d ) );
                if ( $d ) {
                    $dates[] = [ 'uid' => self::departure_uid( $d, '' ), 'from' => $d, 'to' => '' ];
                }
            }
        }
        return $dates;
    }

    private static function sanitize_int_or_empty( $value ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }
        $value = sanitize_text_field( wp_unslash( (string) $value ) );
        return $value === '' ? '' : (string) max( 0, (int) $value );
    }

    /**
     * Normaliza un array de salidas ya guardado (usado por importadores).
     * Espera entradas con claves from/to/seats/... y devuelve datos seguros.
     */
    public static function sanitize_departures_array( $dates ) {
        if ( ! is_array( $dates ) ) {
            return [];
        }

        $map = [
            'uid'          => 'tp_dates_uid',
            'from'         => 'tp_dates_from',
            'to'           => 'tp_dates_to',
            'seats'        => 'tp_dates_seats',
            'seats_booked' => 'tp_dates_seats_booked',
            'min_pax'      => 'tp_dates_min_pax',
            'max_pax'      => 'tp_dates_max_pax',
            'cutoff'       => 'tp_dates_cutoff',
            'status'       => 'tp_dates_status',
            'price_adult'  => 'tp_dates_price_adult',
            'price_child'  => 'tp_dates_price_child',
            'currency'     => 'tp_dates_currency',
        ];

        $input = [];
        foreach ( $dates as $date ) {
            $date = is_array( $date ) ? $date : [ 'from' => $date ];
            foreach ( $map as $key => $input_key ) {
                $input[ $input_key ][] = $date[ $key ] ?? '';
            }
        }

        return self::sanitize_dates( $input );
    }

    /**
     * Asigna un término (existente o creado) a una taxonomía del paquete.
     * Si no se envía nada, elimina las relaciones previas.
     */
    private static function save_taxonomy_term( $post_id, $taxonomy, $existing_slug, $new_name ) {
        $requested = [];

        if ( $existing_slug && is_scalar( $existing_slug ) ) {
            $requested[] = sanitize_text_field( wp_unslash( (string) $existing_slug ) );
        }
        if ( $new_name && is_scalar( $new_name ) ) {
            $requested[] = sanitize_text_field( wp_unslash( (string) $new_name ) );
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

        if ( ! is_scalar( $name_or_slug ) ) {
            return 0;
        }

        $value = sanitize_text_field( (string) $name_or_slug );
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
        $status = isset( $input['tp_post_status'] ) && in_array( $input['tp_post_status'], [ 'publish', 'draft' ], true ) ? $input['tp_post_status'] : 'draft';
        if ( $status === 'publish' && ! current_user_can( 'publish_posts' ) ) {
            $status = 'draft';
        }

        $post_data = [
            'post_type'    => self::POST_TYPE,
            'post_title'   => isset( $input['tp_title'] ) ? sanitize_text_field( wp_unslash( $input['tp_title'] ) ) : '',
            'post_content' => isset( $input['tp_content'] ) ? wp_kses_post( wp_unslash( $input['tp_content'] ) ) : '',
            'post_status'  => $status,
        ];

        $package_id = ! empty( $input['tp_package_id'] ) ? intval( $input['tp_package_id'] ) : 0;
        if ( $package_id ) {
            $existing = get_post( $package_id );
            if ( ! $existing || $existing->post_type !== self::POST_TYPE || ! current_user_can( 'edit_post', $package_id ) ) {
                return new WP_Error( 'tp_invalid_package', __( 'Paquete inválido o sin permisos.', 'travel-packages' ) );
            }
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
        foreach ( [ '_tp_tech_fields', '_tp_dates', self::META_ITINERARY, self::META_TIPO_VIAJE, self::META_PRICES, self::META_PRICING, self::META_WHATSAPP, self::META_MAP_LOCATION, self::META_SIMILAR_DESTINOS, self::META_SEO_TITLE, self::META_SEO_DESCRIPTION, self::META_SEO_IMAGE, self::META_SEO_NOINDEX ] as $meta_key ) {
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
