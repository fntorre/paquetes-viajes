<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TP_Reservations
 *
 * Módulo opcional de reservas. No procesa pagos: registra reservas y,
 * cuando está activo, descuenta cupos de las salidas del paquete.
 */
class TP_Reservations {

    const POST_TYPE      = 'tp_reserva';
    const OPTION_ENABLED = 'tp_reservations_enabled';

    const META_PACKAGE  = '_tp_reserva_package_id';
    const META_UID      = '_tp_reserva_departure_uid';
    const META_FROM     = '_tp_reserva_from';
    const META_TO       = '_tp_reserva_to';
    const META_CUSTOMER = '_tp_reserva_customer';
    const META_EMAIL    = '_tp_reserva_email';
    const META_PHONE    = '_tp_reserva_phone';
    const META_ADULTS   = '_tp_reserva_adults';
    const META_CHILDREN = '_tp_reserva_children';
    const META_PAX      = '_tp_reserva_pax';
    const META_STATUS   = '_tp_reserva_status';
    const META_NOTES    = '_tp_reserva_notes';
    const META_SOURCE   = '_tp_reserva_source';
    const USER_META_LAST_SEEN_ID = 'tp_reservations_last_seen_id_v2';

    private static $booked_cache   = [];
    private static $packages_cache = null;
    private static $new_reservations_count = null;

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_type' ] );
        add_action( 'admin_menu', [ __CLASS__, 'add_submenu_page' ], 21 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_notification_styles' ] );
        add_action( 'admin_post_tp_save_reservation', [ __CLASS__, 'handle_save' ] );
        add_action( 'admin_post_tp_delete_reservation', [ __CLASS__, 'handle_delete' ] );
        add_action( 'admin_post_tp_toggle_reservations', [ __CLASS__, 'handle_toggle' ] );
        add_action( 'admin_post_tp_public_inquiry', [ __CLASS__, 'handle_public_inquiry' ] );
        add_action( 'admin_post_nopriv_tp_public_inquiry', [ __CLASS__, 'handle_public_inquiry' ] );
        add_action( 'before_delete_post', [ __CLASS__, 'delete_for_package' ] );
    }

    public static function register_post_type() {
        register_post_type( self::POST_TYPE, [
            'labels'          => [
                'name'          => __( 'Reservas', 'travel-packages' ),
                'singular_name' => __( 'Reserva', 'travel-packages' ),
            ],
            'public'          => false,
            'show_ui'         => false,
            'show_in_menu'    => false,
            'show_in_rest'    => false,
            'supports'        => [ 'title' ],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
        ] );
    }

    public static function is_enabled() {
        return get_option( self::OPTION_ENABLED, '0' ) === '1';
    }

    public static function get_statuses() {
        return [
            'pending'   => __( 'Pendiente', 'travel-packages' ),
            'confirmed' => __( 'Confirmada', 'travel-packages' ),
            'cancelled' => __( 'Cancelada', 'travel-packages' ),
        ];
    }

    public static function get_active_statuses() {
        return [ 'pending', 'confirmed' ];
    }

    /** Mapa uid de salida => pasajeros reservados activos. */
    public static function get_booked_map( $package_id ) {
        $package_id = (int) $package_id;
        if ( $package_id <= 0 ) {
            return [];
        }
        if ( isset( self::$booked_cache[ $package_id ] ) ) {
            return self::$booked_cache[ $package_id ];
        }

        $ids = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [ 'key' => self::META_PACKAGE, 'value' => $package_id, 'compare' => '=', 'type' => 'NUMERIC' ],
                [ 'key' => self::META_STATUS, 'value' => self::get_active_statuses(), 'compare' => 'IN' ],
            ],
        ] );

        $map = [];
        foreach ( $ids as $id ) {
            if ( class_exists( 'TP_Payments' ) && TP_Payments::expire_if_due( $id ) ) {
                continue;
            }
            $uid = (string) get_post_meta( $id, self::META_UID, true );
            if ( $uid === '' ) {
                continue;
            }
            $pax = max( 0, (int) get_post_meta( $id, self::META_PAX, true ) );
            $map[ $uid ] = ( $map[ $uid ] ?? 0 ) + $pax;
        }

        self::$booked_cache[ $package_id ] = $map;
        return $map;
    }

    public static function get_booked_for_uid( $package_id, $uid ) {
        $map = self::get_booked_map( $package_id );
        return isset( $map[ $uid ] ) ? (int) $map[ $uid ] : 0;
    }

    public static function clear_cache( $package_id = 0 ) {
        if ( $package_id ) {
            unset( self::$booked_cache[ (int) $package_id ] );
        } else {
            self::$booked_cache = [];
        }
        self::$packages_cache = null;
    }

    public static function get_reservation( $id ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return null;
        }

        return [
            'id'       => $post->ID,
            'package'  => (int) get_post_meta( $post->ID, self::META_PACKAGE, true ),
            'uid'      => (string) get_post_meta( $post->ID, self::META_UID, true ),
            'from'     => (string) get_post_meta( $post->ID, self::META_FROM, true ),
            'to'       => (string) get_post_meta( $post->ID, self::META_TO, true ),
            'customer' => (string) get_post_meta( $post->ID, self::META_CUSTOMER, true ),
            'email'    => (string) get_post_meta( $post->ID, self::META_EMAIL, true ),
            'phone'    => (string) get_post_meta( $post->ID, self::META_PHONE, true ),
            'adults'   => (int) get_post_meta( $post->ID, self::META_ADULTS, true ),
            'children' => (int) get_post_meta( $post->ID, self::META_CHILDREN, true ),
            'pax'      => (int) get_post_meta( $post->ID, self::META_PAX, true ),
            'status'   => (string) get_post_meta( $post->ID, self::META_STATUS, true ),
            'notes'    => (string) get_post_meta( $post->ID, self::META_NOTES, true ),
            'source'   => (string) get_post_meta( $post->ID, self::META_SOURCE, true ),
            'payment_status' => class_exists( 'TP_Payments' ) ? (string) get_post_meta( $post->ID, TP_Payments::META_STATUS, true ) : '',
            'payment_amount_ars' => class_exists( 'TP_Payments' ) ? (string) get_post_meta( $post->ID, TP_Payments::META_AMOUNT, true ) : '',
            'payment_original_amount' => class_exists( 'TP_Payments' ) ? (string) get_post_meta( $post->ID, TP_Payments::META_ORIGINAL, true ) : '',
            'payment_currency' => class_exists( 'TP_Payments' ) ? (string) get_post_meta( $post->ID, TP_Payments::META_CURRENCY, true ) : '',
            'payment_review' => class_exists( 'TP_Payments' ) && get_post_meta( $post->ID, TP_Payments::META_REVIEW, true ) === '1',
            'payment_id' => class_exists( 'TP_Payments' ) ? (string) get_post_meta( $post->ID, TP_Payments::META_PAYMENT_ID, true ) : '',
        ];
    }

    public static function save( $data, $id = 0 ) {
        $id = (int) $id;

        $package_id = isset( $data['package'] ) ? (int) $data['package'] : 0;
        $package    = $package_id ? get_post( $package_id ) : null;
        if ( ! $package || $package->post_type !== TP_Package_Data::POST_TYPE ) {
            return new WP_Error( 'tp_reserva_package', __( 'Seleccioná un paquete válido.', 'travel-packages' ) );
        }

        $customer = isset( $data['customer'] ) ? sanitize_text_field( wp_unslash( $data['customer'] ) ) : '';
        $email    = isset( $data['email'] ) ? sanitize_email( wp_unslash( $data['email'] ) ) : '';
        $phone    = isset( $data['phone'] ) ? sanitize_text_field( wp_unslash( $data['phone'] ) ) : '';
        $adults   = isset( $data['adults'] ) ? max( 0, (int) $data['adults'] ) : 0;
        $children = isset( $data['children'] ) ? max( 0, (int) $data['children'] ) : 0;
        $pax      = $adults + $children;

        if ( $pax <= 0 ) {
            return new WP_Error( 'tp_reserva_pax', __( 'La reserva debe tener al menos un pasajero.', 'travel-packages' ) );
        }

        $status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'pending';
        if ( ! array_key_exists( $status, self::get_statuses() ) ) {
            $status = 'pending';
        }

        $uid  = isset( $data['uid'] ) ? sanitize_key( $data['uid'] ) : '';
        $from = isset( $data['from'] ) ? sanitize_text_field( wp_unslash( $data['from'] ) ) : '';
        $to   = isset( $data['to'] ) ? sanitize_text_field( wp_unslash( $data['to'] ) ) : '';

        if ( $uid !== '' && ( $from === '' || $to === '' ) ) {
            foreach ( TP_Package_Data::get_departures( $package_id ) as $departure ) {
                if ( $departure['uid'] === $uid ) {
                    $from = $departure['from'];
                    $to   = $departure['to'];
                    break;
                }
            }
        }

        if ( $uid === '' ) {
            $uid = TP_Package_Data::departure_uid( $from, $to );
        }

        if ( $id && class_exists( 'TP_Payments' ) ) {
            $existing_reservation = self::get_reservation( $id );
            $payment_status = (string) get_post_meta( $id, TP_Payments::META_STATUS, true );
            if ( $existing_reservation && in_array( $payment_status, [ 'pending', 'approved', 'expired' ], true ) ) {
                if (
                    $package_id !== $existing_reservation['package']
                    || $uid !== $existing_reservation['uid']
                    || $from !== $existing_reservation['from']
                    || $to !== $existing_reservation['to']
                    || $adults !== $existing_reservation['adults']
                    || $children !== $existing_reservation['children']
                ) {
                    return new WP_Error( 'tp_reservation_payment_locked', __( 'No se pueden cambiar el paquete, la salida ni los pasajeros mientras el pago esté pendiente o aprobado.', 'travel-packages' ) );
                }
            }
        }

        $postarr = [
            'post_type'   => self::POST_TYPE,
            'post_title'  => $customer !== '' ? $customer : __( 'Reserva', 'travel-packages' ),
            'post_status' => 'publish',
        ];
        if ( $id ) {
            $existing = get_post( $id );
            if ( ! $existing || $existing->post_type !== self::POST_TYPE ) {
                return new WP_Error( 'tp_reserva_not_found', __( 'Reserva no encontrada.', 'travel-packages' ) );
            }
            $postarr['ID'] = $id;
        }

        $post_id = $id ? wp_update_post( $postarr ) : wp_insert_post( $postarr );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        update_post_meta( $post_id, self::META_PACKAGE, $package_id );
        update_post_meta( $post_id, self::META_UID, $uid );
        update_post_meta( $post_id, self::META_FROM, $from );
        update_post_meta( $post_id, self::META_TO, $to );
        update_post_meta( $post_id, self::META_CUSTOMER, $customer );
        update_post_meta( $post_id, self::META_EMAIL, $email );
        update_post_meta( $post_id, self::META_PHONE, $phone );
        update_post_meta( $post_id, self::META_ADULTS, $adults );
        update_post_meta( $post_id, self::META_CHILDREN, $children );
        update_post_meta( $post_id, self::META_PAX, $pax );
        update_post_meta( $post_id, self::META_STATUS, $status );
        update_post_meta( $post_id, self::META_NOTES, isset( $data['notes'] ) ? sanitize_textarea_field( wp_unslash( $data['notes'] ) ) : '' );
        if ( class_exists( 'TP_Payments' ) ) {
            $payment_status = (string) get_post_meta( $post_id, TP_Payments::META_STATUS, true );
            if ( ( $payment_status === 'approved' && $status !== 'confirmed' ) || ( in_array( $payment_status, [ 'pending', 'expired' ], true ) && $status === 'confirmed' ) ) {
                update_post_meta( $post_id, TP_Payments::META_REVIEW, '1' );
            } elseif ( $payment_status === 'pending' && $status === 'cancelled' ) {
                update_post_meta( $post_id, TP_Payments::META_STATUS, 'expired' );
            }
        }

        $source = isset( $data['source'] ) ? sanitize_key( $data['source'] ) : '';
        if ( $source !== '' ) {
            update_post_meta( $post_id, self::META_SOURCE, $source );
        } elseif ( ! $id ) {
            update_post_meta( $post_id, self::META_SOURCE, 'admin' );
        }

        self::clear_cache( $package_id );

        return (int) $post_id;
    }

    public static function save_with_capacity_lock( $data, $id = 0 ) {
        $package_id = isset( $data['package'] ) ? (int) $data['package'] : 0;
        $uid = isset( $data['uid'] ) ? sanitize_key( $data['uid'] ) : '';
        if ( ! $package_id || $uid === '' ) {
            return self::save( $data, $id );
        }

        global $wpdb;
        $lock_name = 'tpmp_' . md5( $package_id . '|' . $uid );
        $locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 8)', $lock_name ) );
        if ( (string) $locked !== '1' ) {
            return new WP_Error( 'tp_reservation_busy', __( 'La salida se está actualizando. Intentá nuevamente en unos segundos.', 'travel-packages' ) );
        }

        $reservation_lock = '';
        try {
            self::clear_cache( $package_id );
            $departure = null;
            foreach ( TP_Package_Data::get_departures( $package_id, false, true ) as $candidate ) {
                if ( $candidate['uid'] === $uid ) {
                    $departure = $candidate;
                    break;
                }
            }
            if ( ! $departure ) {
                return new WP_Error( 'tp_reservation_departure', __( 'La salida seleccionada ya no está disponible.', 'travel-packages' ) );
            }

            $existing = $id ? self::get_reservation( $id ) : null;
            $current_pax = $existing
                && $existing['package'] === $package_id
                && $existing['uid'] === $uid
                && in_array( $existing['status'], self::get_active_statuses(), true )
                    ? $existing['pax']
                    : 0;
            $pax = isset( $data['adults'], $data['children'] )
                ? max( 0, (int) $data['adults'] ) + max( 0, (int) $data['children'] )
                : 0;

            if ( $departure['status'] !== 'open' && $current_pax === 0 ) {
                return new WP_Error( 'tp_reservation_departure', __( 'La salida seleccionada ya no está disponible.', 'travel-packages' ) );
            }
            if ( $departure['seats_left'] !== null && $pax > (int) $departure['seats_left'] + $current_pax ) {
                return new WP_Error( 'tp_reservation_capacity', __( 'No hay suficientes cupos disponibles para esa cantidad de pasajeros.', 'travel-packages' ) );
            }
            if ( $departure['min_pax'] !== null && $pax < (int) $departure['min_pax'] ) {
                return new WP_Error( 'tp_reservation_min_pax', __( 'La cantidad de pasajeros es menor al mínimo permitido.', 'travel-packages' ) );
            }
            if ( $departure['max_pax'] !== null && $pax > (int) $departure['max_pax'] ) {
                return new WP_Error( 'tp_reservation_max_pax', __( 'La cantidad de pasajeros supera el máximo permitido.', 'travel-packages' ) );
            }

            $data['from'] = $departure['from'];
            $data['to'] = $departure['to'];
            if ( $id && class_exists( 'TP_Payments' ) && in_array( (string) get_post_meta( $id, TP_Payments::META_STATUS, true ), [ 'pending', 'approved', 'expired' ], true ) ) {
                $reservation_lock = 'tpmp_res_' . (int) $id;
                $reservation_locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 8)', $reservation_lock ) );
                if ( (string) $reservation_locked !== '1' ) {
                    $reservation_lock = '';
                    return new WP_Error( 'tp_reservation_busy', __( 'El pago se está actualizando. Intentá nuevamente en unos segundos.', 'travel-packages' ) );
                }
            }

            return self::save( $data, $id );
        } finally {
            if ( $reservation_lock !== '' ) {
                $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $reservation_lock ) );
            }
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        }
    }

    public static function delete( $id ) {
        $id   = (int) $id;
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            return false;
        }

        if ( class_exists( 'TP_Payments' ) ) {
            $payment_status = (string) get_post_meta( $id, TP_Payments::META_STATUS, true );
            if ( in_array( $payment_status, [ 'pending', 'approved', 'expired' ], true ) || get_post_meta( $id, TP_Payments::META_REVIEW, true ) === '1' ) {
                return false;
            }
        }

        $package_id = (int) get_post_meta( $id, self::META_PACKAGE, true );
        $deleted    = wp_delete_post( $id, true );
        self::clear_cache( $package_id );

        return (bool) $deleted;
    }

    public static function delete_for_package( $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== TP_Package_Data::POST_TYPE ) {
            return;
        }

        $ids = get_posts( [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [ 'key' => self::META_PACKAGE, 'value' => (int) $post_id, 'compare' => '=', 'type' => 'NUMERIC' ],
            ],
        ] );

        foreach ( $ids as $id ) {
            if ( class_exists( 'TP_Payments' ) ) {
                $payment_status = (string) get_post_meta( $id, TP_Payments::META_STATUS, true );
                if ( in_array( $payment_status, [ 'pending', 'approved', 'expired' ], true ) || get_post_meta( $id, TP_Payments::META_REVIEW, true ) === '1' ) {
                    if ( $payment_status === 'pending' ) {
                        update_post_meta( $id, self::META_STATUS, 'cancelled' );
                        update_post_meta( $id, TP_Payments::META_STATUS, 'expired' );
                    } else {
                        update_post_meta( $id, TP_Payments::META_REVIEW, '1' );
                    }
                    continue;
                }
            }
            wp_delete_post( $id, true );
        }

        self::clear_cache( (int) $post_id );
    }

    public static function get_packages_with_departures() {
        if ( self::$packages_cache !== null ) {
            return self::$packages_cache;
        }

        $packages = get_posts( [
            'post_type'      => TP_Package_Data::POST_TYPE,
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $out = [];
        foreach ( $packages as $package ) {
            $departures = [];
            foreach ( TP_Package_Data::get_departures( $package->ID, false, true ) as $departure ) {
                $label = $departure['from'];
                if ( ! empty( $departure['to'] ) ) {
                    $label .= ' → ' . $departure['to'];
                }
                $label .= ' · ' . (
                    $departure['seats_left'] !== null
                        ? sprintf( __( '%d cupos', 'travel-packages' ), (int) $departure['seats_left'] )
                        : __( 'sin cupo', 'travel-packages' )
                );

                $departures[] = [
                    'uid'   => $departure['uid'],
                    'label' => $label,
                    'from'  => $departure['from'],
                    'to'    => $departure['to'],
                ];
            }

            $out[] = [
                'id'         => $package->ID,
                'title'      => $package->post_title,
                'departures' => $departures,
            ];
        }

        self::$packages_cache = $out;

        return $out;
    }

    public static function add_submenu_page() {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        if ( $page === 'tp-reservations' && current_user_can( 'edit_posts' ) ) {
            self::mark_reservations_seen();
        }

        $new_count = self::get_new_reservations_count();
        $menu_title = __( 'Reservas', 'travel-packages' );
        if ( $new_count > 0 ) {
            $menu_title .= sprintf(
                ' <span class="tp-reservation-new-count" role="status" aria-label="%s"><span>%s</span></span>',
                esc_attr( sprintf( __( '%d nuevas reservas', 'travel-packages' ), $new_count ) ),
                esc_html( number_format_i18n( $new_count ) )
            );
        }

        add_submenu_page(
            'tp-dashboard',
            __( 'Reservas', 'travel-packages' ),
            $menu_title,
            'edit_posts',
            'tp-reservations',
            [ __CLASS__, 'render_page' ]
        );
    }

    private static function get_latest_reservation_id() {
        global $wpdb;

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' ORDER BY ID DESC LIMIT 1",
            self::POST_TYPE
        ) );
    }

    private static function mark_reservations_seen() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        update_user_meta( $user_id, self::USER_META_LAST_SEEN_ID, self::get_latest_reservation_id() );
        self::$new_reservations_count = 0;
    }

    private static function get_new_reservations_count() {
        if ( self::$new_reservations_count !== null ) {
            return self::$new_reservations_count;
        }

        global $wpdb;
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            self::$new_reservations_count = 0;
            return 0;
        }

        $last_seen_id = get_user_meta( $user_id, self::USER_META_LAST_SEEN_ID, true );
        if ( $last_seen_id === '' ) {
            $last_seen_id = 0;
            update_user_meta( $user_id, self::USER_META_LAST_SEEN_ID, $last_seen_id );
        }

        self::$new_reservations_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND ID > %d",
            self::POST_TYPE,
            (int) $last_seen_id
        ) );

        return self::$new_reservations_count;
    }

    public static function enqueue_notification_styles() {
        if ( self::get_new_reservations_count() <= 0 ) {
            return;
        }

        wp_register_style( 'tp-reservation-notification', false, [], TP_VERSION );
        wp_enqueue_style( 'tp-reservation-notification' );
        wp_add_inline_style(
            'tp-reservation-notification',
            '@keyframes tp-reservation-notification-glow {
                0%, 100% { box-shadow: 0 0 0 0 rgba(201,239,116,.24); }
                50% { box-shadow: 0 0 7px 2px rgba(201,239,116,.62); }
            }
            #toplevel_page_tp-dashboard .wp-submenu a[href*="page=tp-reservations"] .tp-reservation-new-count {
                display: inline-flex;
                min-width: 15px;
                height: 15px;
                margin-left: 3px;
                padding: 0 4px;
                align-items: center;
                justify-content: center;
                box-sizing: border-box;
                border-radius: 999px;
                background: #c9ef74;
                color: #233d32;
                font-size: 9px;
                font-weight: 600;
                line-height: 15px;
                vertical-align: middle;
                animation: tp-reservation-notification-glow 2.4s ease-in-out infinite;
            }'
        );
    }

    public static function enqueue( $hook ) {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        if ( $page !== 'tp-reservations' ) {
            return;
        }

        wp_enqueue_script(
            'tp-admin-reservations',
            TP_PLUGIN_URL . 'admin/js/reservations.js',
            [ 'jquery', 'tp-admin' ],
            TP_VERSION,
            true
        );

        wp_localize_script( 'tp-admin-reservations', 'TP_RESERVATIONS', [
            'packages' => self::get_packages_with_departures(),
        ] );
    }

    public static function handle_toggle() {
        if ( ! isset( $_POST['tp_reservations_nonce'] ) || ! wp_verify_nonce( $_POST['tp_reservations_nonce'], 'tp_toggle_reservations' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        $enabled = isset( $_POST['enabled'] ) && $_POST['enabled'] === '1' ? '1' : '0';
        update_option( self::OPTION_ENABLED, $enabled );
        self::clear_cache();

        wp_safe_redirect( admin_url( 'admin.php?page=tp-reservations&status=toggled' ) );
        exit;
    }

    public static function handle_save() {
        if ( ! isset( $_POST['tp_reservation_nonce'] ) || ! wp_verify_nonce( $_POST['tp_reservation_nonce'], 'tp_reservation_save' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        $id = isset( $_POST['reservation_id'] ) ? (int) $_POST['reservation_id'] : 0;
        if ( $id && ( ! get_post( $id ) || get_post_type( $id ) !== self::POST_TYPE || ! current_user_can( 'edit_post', $id ) ) ) {
            wp_die( __( 'No tienes permisos para editar esta reserva.', 'travel-packages' ) );
        }

        $data = [
            'package'  => isset( $_POST['tp_res_package'] ) ? (int) $_POST['tp_res_package'] : 0,
            'uid'      => isset( $_POST['tp_res_departure'] ) ? sanitize_key( wp_unslash( $_POST['tp_res_departure'] ) ) : '',
            'customer' => isset( $_POST['tp_res_customer'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_res_customer'] ) ) : '',
            'email'    => isset( $_POST['tp_res_email'] ) ? sanitize_email( wp_unslash( $_POST['tp_res_email'] ) ) : '',
            'phone'    => isset( $_POST['tp_res_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_res_phone'] ) ) : '',
            'adults'   => isset( $_POST['tp_res_adults'] ) ? (int) $_POST['tp_res_adults'] : 0,
            'children' => isset( $_POST['tp_res_children'] ) ? (int) $_POST['tp_res_children'] : 0,
            'status'   => isset( $_POST['tp_res_status'] ) ? sanitize_key( wp_unslash( $_POST['tp_res_status'] ) ) : 'pending',
            'notes'    => isset( $_POST['tp_res_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tp_res_notes'] ) ) : '',
        ];

        $result = self::save_with_capacity_lock( $data, $id );
        if ( is_wp_error( $result ) ) {
            wp_safe_redirect( add_query_arg( [
                'page'    => 'tp-reservations',
                'status'  => 'error',
                'message' => $result->get_error_message(),
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        wp_safe_redirect( add_query_arg( [
            'page'           => 'tp-reservations',
            'status'         => 'saved',
            'reservation_id' => $result,
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    public static function handle_delete() {
        if ( ! isset( $_POST['tp_reservation_delete_nonce'] ) || ! wp_verify_nonce( $_POST['tp_reservation_delete_nonce'], 'tp_reservation_delete' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        $id   = isset( $_POST['reservation_id'] ) ? (int) $_POST['reservation_id'] : 0;
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || $post->post_type !== self::POST_TYPE ) {
            wp_die( __( 'Reserva no encontrada.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'delete_post', $id ) ) {
            wp_die( __( 'No tienes permisos para eliminar esta reserva.', 'travel-packages' ) );
        }

        if ( ! self::delete( $id ) ) {
            wp_safe_redirect( add_query_arg( [
                'page'    => 'tp-reservations',
                'status'  => 'error',
                'message' => __( 'No se puede eliminar una reserva vinculada a un pago pendiente, aprobado, vencido o que requiere revisión.', 'travel-packages' ),
            ], admin_url( 'admin.php' ) ) );
            exit;
        }

        wp_safe_redirect( admin_url( 'admin.php?page=tp-reservations&status=deleted' ) );
        exit;
    }

    /** Mensaje legible para el resultado de una consulta pública. */
    public static function get_inquiry_notice( $code ) {
        if ( $code === 'success' ) {
            return __( '¡Gracias! Recibimos tu consulta y te contactaremos a la brevedad.', 'travel-packages' );
        }

        $messages = [
            'nonce'     => __( 'La sesión expiró. Recargá la página e intentá de nuevo.', 'travel-packages' ),
            'package'   => __( 'El paquete no está disponible.', 'travel-packages' ),
            'fields'    => __( 'Verificá tus datos, la cantidad de pasajeros y la salida seleccionada.', 'travel-packages' ),
            'departure' => __( 'La salida seleccionada ya no está disponible.', 'travel-packages' ),
            'capacity'  => __( 'No hay suficientes cupos disponibles para esa cantidad de pasajeros.', 'travel-packages' ),
            'throttle'  => __( 'Esperá unos segundos antes de enviar otra consulta.', 'travel-packages' ),
            'busy'      => __( 'La salida se está actualizando. Intentá nuevamente en unos segundos.', 'travel-packages' ),
            'save'      => __( 'No pudimos guardar la consulta. Intentá nuevamente.', 'travel-packages' ),
            'spam'      => __( 'No pudimos procesar la consulta.', 'travel-packages' ),
        ];

        return $messages[ $code ] ?? __( 'No pudimos procesar la consulta. Intentá nuevamente.', 'travel-packages' );
    }

    /**
     * Consulta pública desde la ficha del paquete.
     * Crea una reserva pendiente (sin pago) y notifica por email.
     */
    public static function handle_public_inquiry() {
        $package_id = isset( $_POST['tp_inquiry_package'] ) ? (int) $_POST['tp_inquiry_package'] : 0;
        $redirect   = $package_id ? get_permalink( $package_id ) : home_url( '/' );
        if ( ! $redirect ) {
            $redirect = home_url( '/' );
        }

        $fail = function( $code ) use ( $redirect ) {
            wp_safe_redirect( add_query_arg( 'tp_inquiry_msg', $code, add_query_arg( 'tp_inquiry', 'error', $redirect ) ) . '#tp-inquiry' );
            exit;
        };

        if ( ! isset( $_POST['tp_inquiry_nonce'] ) || ! wp_verify_nonce( $_POST['tp_inquiry_nonce'], 'tp_public_inquiry' ) ) {
            $fail( 'nonce' );
        }
        if ( class_exists( 'TP_Settings' ) && ! TP_Settings::is_inquiry_enabled() ) {
            $fail( 'package' );
        }
        if ( ! empty( $_POST['tp_website'] ) ) {
            $fail( 'spam' );
        }
        if ( ! $package_id || get_post_type( $package_id ) !== TP_Package_Data::POST_TYPE || get_post_status( $package_id ) !== 'publish' ) {
            $fail( 'package' );
        }

        $name     = isset( $_POST['tp_inquiry_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_inquiry_name'] ) ) : '';
        $email    = isset( $_POST['tp_inquiry_email'] ) ? sanitize_email( wp_unslash( $_POST['tp_inquiry_email'] ) ) : '';
        $phone    = isset( $_POST['tp_inquiry_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_inquiry_phone'] ) ) : '';
        $adults   = isset( $_POST['tp_inquiry_adults'] ) ? max( 1, (int) $_POST['tp_inquiry_adults'] ) : 1;
        $children = isset( $_POST['tp_inquiry_children'] ) ? max( 0, (int) $_POST['tp_inquiry_children'] ) : 0;
        $notes    = isset( $_POST['tp_inquiry_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tp_inquiry_message'] ) ) : '';
        $consent  = ! empty( $_POST['tp_inquiry_consent'] );

        if ( $name === '' || ! is_email( $email ) || ! $consent ) {
            $fail( 'fields' );
        }

        $uid  = isset( $_POST['tp_inquiry_uid'] ) ? sanitize_key( wp_unslash( $_POST['tp_inquiry_uid'] ) ) : '';
        $from = '';
        $to   = '';
        if ( $uid !== '' ) {
            $found = false;
            foreach ( TP_Package_Data::get_departures( $package_id ) as $departure ) {
                if ( $departure['uid'] === $uid && $departure['status'] === 'open' ) {
                    $from  = $departure['from'];
                    $to    = $departure['to'];
                    $found = true;
                    break;
                }
            }
            if ( ! $found ) {
                $fail( 'departure' );
            }
        }

        $ip           = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $throttle_key = 'tp_inquiry_' . md5( $package_id . '|' . $ip );
        if ( get_transient( $throttle_key ) ) {
            $fail( 'throttle' );
        }
        set_transient( $throttle_key, '1', 30 );

        $result = self::save_with_capacity_lock( [
            'package'  => $package_id,
            'uid'      => $uid,
            'from'     => $from,
            'to'       => $to,
            'customer' => $name,
            'email'    => $email,
            'phone'    => $phone,
            'adults'   => $adults,
            'children' => $children,
            'status'   => 'pending',
            'notes'    => $notes,
            'source'   => 'web',
        ] );

        if ( is_wp_error( $result ) ) {
            $error_code = $result->get_error_code();
            if ( $error_code === 'tp_reservation_capacity' ) {
                $fail( 'capacity' );
            } elseif ( $error_code === 'tp_reservation_departure' ) {
                $fail( 'departure' );
            } elseif ( in_array( $error_code, [ 'tp_reservation_min_pax', 'tp_reservation_max_pax' ], true ) ) {
                $fail( 'fields' );
            } elseif ( $error_code === 'tp_reservation_busy' ) {
                $fail( 'busy' );
            } else {
                $fail( 'save' );
            }
        }

        self::send_inquiry_email( (int) $result );

        wp_safe_redirect( add_query_arg( 'tp_inquiry', 'success', $redirect ) . '#tp-inquiry' );
        exit;
    }

    /** Notifica por email una consulta pública. */
    private static function send_inquiry_email( $reservation_id ) {
        if ( ! class_exists( 'TP_Settings' ) ) {
            return;
        }

        $reservation = self::get_reservation( $reservation_id );
        if ( ! $reservation ) {
            return;
        }

        $to            = TP_Settings::get_inquiry_email();
        $package_title = $reservation['package'] ? get_the_title( $reservation['package'] ) : __( 'Paquete', 'travel-packages' );
        if ( ! $to ) {
            return;
        }

        $subject = sprintf( __( 'Nueva consulta web: %s', 'travel-packages' ), $package_title );
        $dates   = trim( $reservation['from'] . ( $reservation['to'] ? ' → ' . $reservation['to'] : '' ) );

        $lines = [
            sprintf( __( 'Paquete: %s', 'travel-packages' ), $package_title ),
            sprintf( __( 'Salida: %s', 'travel-packages' ), $dates ?: __( 'Consulta general', 'travel-packages' ) ),
            sprintf( __( 'Pasajeros: %d adultos, %d niños', 'travel-packages' ), $reservation['adults'], $reservation['children'] ),
            '',
            sprintf( __( 'Nombre: %s', 'travel-packages' ), $reservation['customer'] ),
            sprintf( __( 'Email: %s', 'travel-packages' ), $reservation['email'] ),
            sprintf( __( 'Teléfono: %s', 'travel-packages' ), $reservation['phone'] ?: '-' ),
            '',
            __( 'Mensaje:', 'travel-packages' ),
            $reservation['notes'] ?: '-',
            '',
            sprintf( __( 'Gestionar reserva: %s', 'travel-packages' ), admin_url( 'admin.php?page=tp-reservations&edit=' . $reservation_id ) ),
        ];

        $headers = [];
        if ( $reservation['email'] ) {
            $headers[] = 'Reply-To: ' . $reservation['email'];
        }

        wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
    }

    public static function send_payment_notification( $reservation_id, $approved = false ) {
        if ( ! class_exists( 'TP_Settings' ) ) {
            return false;
        }

        $reservation = self::get_reservation( $reservation_id );
        $to = TP_Settings::get_inquiry_email();
        if ( ! $reservation || ! $to ) {
            return false;
        }

        $package_title = $reservation['package'] ? get_the_title( $reservation['package'] ) : __( 'Paquete', 'travel-packages' );
        $dates = trim( $reservation['from'] . ( $reservation['to'] ? ' → ' . $reservation['to'] : '' ) );
        $amount = $reservation['payment_amount_ars'] !== ''
            ? 'ARS ' . number_format_i18n( (float) $reservation['payment_amount_ars'], 2 )
            : '—';
        $lines = [
            sprintf( __( 'Paquete: %s', 'travel-packages' ), $package_title ),
            sprintf( __( 'Salida: %s', 'travel-packages' ), $dates ?: '—' ),
            sprintf( __( 'Pasajeros: %d adultos, %d niños', 'travel-packages' ), $reservation['adults'], $reservation['children'] ),
            sprintf( __( 'Cliente: %s', 'travel-packages' ), $reservation['customer'] ),
            sprintf( __( 'Email: %s', 'travel-packages' ), $reservation['email'] ),
            sprintf( __( 'Teléfono: %s', 'travel-packages' ), $reservation['phone'] ?: '-' ),
            sprintf( __( 'Total en ARS: %s', 'travel-packages' ), $amount ),
            sprintf( __( 'Estado del pago: %s', 'travel-packages' ), $approved ? __( 'Aprobado', 'travel-packages' ) : __( 'Pendiente', 'travel-packages' ) ),
            '',
            __( 'Mensaje:', 'travel-packages' ),
            $reservation['notes'] ?: '-',
            '',
            sprintf( __( 'Gestionar reserva: %s', 'travel-packages' ), admin_url( 'admin.php?page=tp-reservations&view=' . (int) $reservation_id ) ),
        ];
        $headers = $reservation['email'] ? [ 'Reply-To: ' . $reservation['email'] ] : [];
        $subject = sprintf(
            $approved ? __( 'Pago aprobado: %s', 'travel-packages' ) : __( 'Nueva reserva online pendiente de pago: %s', 'travel-packages' ),
            $package_title
        );

        $sent = wp_mail( $to, $subject, implode( "\n", $lines ), $headers );
        if ( ! $sent ) {
            error_log( 'Tureo could not send Mercado Pago reservation notification for reservation ' . (int) $reservation_id );
        }
        return (bool) $sent;
    }

    private static function query_reservations( $args = [] ) {
        $per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
        $paged    = max( 1, (int) ( $args['paged'] ?? 1 ) );

        $meta_query = [];
        if ( ! empty( $args['package'] ) ) {
            $meta_query[] = [
                'key'     => self::META_PACKAGE,
                'value'   => (int) $args['package'],
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
        }
        $status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : '';
        if ( $status !== '' && array_key_exists( $status, self::get_statuses() ) ) {
            $meta_query[] = [
                'key'   => self::META_STATUS,
                'value' => $status,
            ];
        } elseif ( ! empty( $args['active_only'] ) ) {
            $meta_query[] = [
                'key'     => self::META_STATUS,
                'value'   => self::get_active_statuses(),
                'compare' => 'IN',
            ];
        }

        $query_args = [
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'any',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if ( $meta_query ) {
            $query_args['meta_query'] = $meta_query;
        }
        if ( ! empty( $args['search'] ) ) {
            $query_args['s'] = sanitize_text_field( $args['search'] );
        }

        $query = new WP_Query( $query_args );
        $posts = [];
        foreach ( $query->posts as $post ) {
            $reservation = self::get_reservation( $post->ID );
            if ( $reservation ) {
                $posts[] = $reservation;
            }
        }

        return [
            'posts' => $posts,
            'pages' => (int) $query->max_num_pages,
            'total' => (int) $query->found_posts,
        ];
    }

    public static function render_page() {
        $enabled  = self::is_enabled();
        $edit_id  = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
        $editing  = $edit_id ? self::get_reservation( $edit_id ) : null;
        $view_id  = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;
        $viewing  = null;
        $view_post = null;
        if ( $view_id ) {
            $view_post = get_post( $view_id );
            if ( ! $view_post || $view_post->post_type !== self::POST_TYPE ) {
                wp_die( esc_html__( 'Reserva no encontrada.', 'travel-packages' ) );
            }
            if ( ! current_user_can( 'edit_post', $view_id ) ) {
                wp_die( esc_html__( 'No tienes permisos para ver esta reserva.', 'travel-packages' ) );
            }
            $viewing = self::get_reservation( $view_id );
        }
        $packages = self::get_packages_with_departures();
        $statuses = self::get_statuses();
        $notice   = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
        $message  = isset( $_GET['message'] ) ? sanitize_text_field( wp_unslash( $_GET['message'] ) ) : '';

        $status_filter  = isset( $_GET['tp_status'] ) ? sanitize_key( wp_unslash( $_GET['tp_status'] ) ) : '';
        $package_filter = isset( $_GET['tp_package'] ) ? (int) $_GET['tp_package'] : 0;
        $search         = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $paged          = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;

        $active_only = isset( $_GET['tp_active_seen'] )
            ? ( isset( $_GET['tp_active'] ) && sanitize_key( wp_unslash( $_GET['tp_active'] ) ) === '1' )
            : ( $package_filter > 0 );
        if ( $status_filter !== '' && array_key_exists( $status_filter, $statuses ) ) {
            $active_only = false;
        }

        $reservations = self::query_reservations( [
            'status'      => $status_filter,
            'package'     => $package_filter,
            'active_only' => $active_only,
            'search'      => $search,
            'paged'       => $paged,
            'per_page'    => 20,
        ] );

        $filter_package_title = $package_filter ? get_the_title( $package_filter ) : '';
        ?>
        <div class="wrap tp-admin-wrap tp-reservations-wrap">
            <div class="tp-admin-header">
                <div class="tp-header-left tp-tureo-header-left">
                    <?php TP_Admin::render_brand(); ?>
                    <div class="tp-tureo-heading">
                        <h1><?php _e( 'Reservas', 'travel-packages' ); ?></h1>
                        <p><?php _e( 'Módulo opcional para registrar reservas y descontar cupos automáticamente.', 'travel-packages' ); ?></p>
                    </div>
                    <?php TP_Admin::render_version_badge(); ?>
                </div>
            </div>

            <?php if ( $notice ) : ?>
            <div class="notice <?php echo $notice === 'error' ? 'notice-error' : 'notice-success'; ?> is-dismissible">
                <p><?php
                    if ( $notice === 'saved' ) {
                        _e( 'Reserva guardada correctamente.', 'travel-packages' );
                    } elseif ( $notice === 'deleted' ) {
                        _e( 'Reserva eliminada.', 'travel-packages' );
                    } elseif ( $notice === 'toggled' ) {
                        _e( 'Configuración actualizada.', 'travel-packages' );
                    } elseif ( $notice === 'error' ) {
                        echo esc_html( $message ?: __( 'No se pudo guardar la reserva.', 'travel-packages' ) );
                    }
                ?></p>
            </div>
            <?php endif; ?>

            <div class="tp-package-form tp-res-toggle">
                <div class="tp-res-toggle-info">
                    <h2><?php _e( 'Estado del módulo', 'travel-packages' ); ?></h2>
                    <p>
                        <?php if ( $enabled ) : ?>
                            <?php _e( 'Activo: las reservas pendientes y confirmadas descuentan cupos en el front y el calendario.', 'travel-packages' ); ?>
                        <?php else : ?>
                            <?php _e( 'Desactivado: los cupos se siguen gestionando manualmente desde cada paquete.', 'travel-packages' ); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="tp_toggle_reservations">
                    <input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>">
                    <?php wp_nonce_field( 'tp_toggle_reservations', 'tp_reservations_nonce' ); ?>
                    <button type="submit" class="tp-btn <?php echo $enabled ? 'tp-btn-secondary' : 'tp-btn-primary'; ?>">
                        <?php echo $enabled ? esc_html__( 'Desactivar módulo', 'travel-packages' ) : esc_html__( 'Activar módulo', 'travel-packages' ); ?>
                    </button>
                </form>
            </div>

            <?php if ( ! $enabled ) : ?>
            <div class="tp-panel tp-res-disabled">
                <p><?php _e( 'El módulo de reservas está desactivado: las consultas y reservas se pueden gestionar, pero no descuentan cupos. Activalo cuando quieras.', 'travel-packages' ); ?></p>
            </div>
            <?php endif; ?>

            <div class="tp-reservations-grid">
                <div class="tp-packages-list tp-res-list">
                    <div class="tp-card-header">
                        <h2><?php _e( 'Listado de reservas', 'travel-packages' ); ?></h2>
                    </div>

                    <form method="get" class="tp-res-filters">
                        <input type="hidden" name="page" value="tp-reservations">
                        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Buscar cliente...', 'travel-packages' ); ?>">
                        <select name="tp_status" id="tp-res-filter-status" aria-label="<?php esc_attr_e( 'Filtrar por estado', 'travel-packages' ); ?>">
                            <option value=""><?php _e( 'Todos los estados', 'travel-packages' ); ?></option>
                            <?php foreach ( $statuses as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status_filter, $key ); ?>><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="tp_package" id="tp-res-filter-package" aria-label="<?php esc_attr_e( 'Filtrar por paquete', 'travel-packages' ); ?>">
                            <option value="0"><?php _e( 'Todos los paquetes', 'travel-packages' ); ?></option>
                            <?php foreach ( $packages as $package ) : ?>
                            <option value="<?php echo (int) $package['id']; ?>" <?php selected( $package_filter, $package['id'] ); ?>><?php echo esc_html( $package['title'] ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="tp-res-active-toggle" for="tp-res-active-only">
                            <input type="hidden" name="tp_active_seen" value="1">
                            <input type="checkbox" name="tp_active" id="tp-res-active-only" value="1" <?php checked( $active_only ); ?>>
                            <span><?php _e( 'Solo activas', 'travel-packages' ); ?></span>
                        </label>
                        <button type="submit" class="button"><?php _e( 'Filtrar', 'travel-packages' ); ?></button>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-reservations' ) ); ?>" class="button"><?php _e( 'Limpiar', 'travel-packages' ); ?></a>
                    </form>

                    <?php if ( $package_filter ) : ?>
                    <p class="tp-res-filter-summary">
                        <strong><?php echo esc_html( $filter_package_title ?: __( 'Paquete seleccionado', 'travel-packages' ) ); ?></strong>
                        <?php echo esc_html( $active_only ? __( '· Mostrando reservas activas', 'travel-packages' ) : __( '· Mostrando todos los estados', 'travel-packages' ) ); ?>
                    </p>
                    <?php endif; ?>

                    <?php if ( ! $reservations || empty( $reservations['posts'] ) ) : ?>
                    <div class="tp-empty-state tp-res-empty">
                        <span class="dashicons dashicons-tickets-alt"></span>
                        <p>
                            <?php
                            if ( $package_filter || $status_filter || $active_only || $search !== '' ) {
                                _e( 'No hay reservas que coincidan con los filtros seleccionados.', 'travel-packages' );
                            } else {
                                _e( 'No hay reservas registradas todavía.', 'travel-packages' );
                            }
                            ?>
                        </p>
                    </div>
                    <?php else : ?>
                    <div class="tp-res-table-wrap">
                        <table class="tp-res-table">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Cliente', 'travel-packages' ); ?></th>
                                    <th><?php _e( 'Paquete / Salida', 'travel-packages' ); ?></th>
                                    <th><?php _e( 'Pax', 'travel-packages' ); ?></th>
                                    <th><?php _e( 'Estado', 'travel-packages' ); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $reservations['posts'] as $reservation ) :
                                    $res_status       = $reservation['status'];
                                    $res_status_label = $statuses[ $res_status ] ?? $res_status;
                                    $res_package      = $reservation['package'] ? get_the_title( $reservation['package'] ) : '';
                                    $res_dates        = trim( $reservation['from'] . ( $reservation['to'] ? ' → ' . $reservation['to'] : '' ) );
                                    ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $reservation['customer'] ?: __( 'Sin nombre', 'travel-packages' ) ); ?></strong>
                                        <?php if ( ( $reservation['source'] ?? '' ) === 'web' ) : ?>
                                        <span class="tp-res-source"><?php _e( 'Web', 'travel-packages' ); ?></span>
                                        <?php endif; ?>
                                        <?php if ( $reservation['email'] ) : ?>
                                        <br><small><?php echo esc_html( $reservation['email'] ); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html( $res_package ?: __( 'Paquete eliminado', 'travel-packages' ) ); ?>
                                        <br><small><?php echo esc_html( $res_dates ); ?></small>
                                    </td>
                                    <td><?php echo (int) $reservation['pax']; ?></td>
                                    <td><span class="tp-res-status is-<?php echo esc_attr( $res_status ); ?>"><?php echo esc_html( $res_status_label ); ?></span></td>
                                    <td class="tp-res-actions">
                                        <div class="tp-res-action-buttons">
                                            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'tp-reservations', 'view' => $reservation['id'] ], admin_url( 'admin.php' ) ) ); ?>" class="button button-small"><?php _e( 'Ver', 'travel-packages' ); ?></a>
                                            <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'tp-reservations', 'edit' => $reservation['id'] ], admin_url( 'admin.php' ) ) ); ?>" class="button button-small"><?php _e( 'Editar', 'travel-packages' ); ?></a>
                                            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar esta reserva?', 'travel-packages' ) ); ?>');">
                                                <input type="hidden" name="action" value="tp_delete_reservation">
                                                <input type="hidden" name="reservation_id" value="<?php echo (int) $reservation['id']; ?>">
                                                <?php wp_nonce_field( 'tp_reservation_delete', 'tp_reservation_delete_nonce' ); ?>
                                                <button type="submit" class="button button-small tp-res-delete"><?php _e( 'Eliminar', 'travel-packages' ); ?></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                        <?php if ( $reservations['pages'] > 1 ) : ?>
                        <div class="tp-pagination">
                            <?php
                            echo paginate_links( [
                                'base'      => admin_url( 'admin.php?page=tp-reservations&paged=%#%' ),
                                'format'    => '',
                                'current'   => $paged,
                                'total'     => $reservations['pages'],
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'add_args'  => array_filter( [
                                    's'              => $search,
                                    'tp_status'      => $status_filter,
                                    'tp_package'     => $package_filter ?: '',
                                    'tp_active_seen' => '1',
                                    'tp_active'      => $active_only ? '1' : '',
                                ] ),
                            ] );
                            ?>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ( $viewing ) : ?>
                <?php
                    $view_status = $viewing['status'];
                    $view_status_label = $statuses[ $view_status ] ?? $view_status;
                    $view_package_title = $viewing['package'] ? get_the_title( $viewing['package'] ) : '';
                    $view_created = $view_post->post_date_gmt && $view_post->post_date_gmt !== '0000-00-00 00:00:00'
                        ? get_date_from_gmt( $view_post->post_date_gmt, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
                        : get_the_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $view_post );
                ?>
                <section class="tp-package-form tp-res-form tp-res-detail">
                    <div class="tp-res-detail-heading">
                        <h2><?php _e( 'Detalle de la reserva', 'travel-packages' ); ?></h2>
                        <span class="tp-res-status is-<?php echo esc_attr( $view_status ); ?>"><?php echo esc_html( $view_status_label ); ?></span>
                    </div>
                    <?php if ( $viewing['payment_status'] !== '' ) : ?>
                    <?php
                    $payment_status_labels = [
                        'pending' => __( 'Pendiente', 'travel-packages' ),
                        'approved' => __( 'Aprobado', 'travel-packages' ),
                        'expired' => __( 'Vencido', 'travel-packages' ),
                        'failed' => __( 'Fallido', 'travel-packages' ),
                        'rejected' => __( 'Rechazado', 'travel-packages' ),
                        'cancelled' => __( 'Cancelado', 'travel-packages' ),
                        'refunded' => __( 'Reembolsado', 'travel-packages' ),
                        'charged_back' => __( 'Desconocido por el titular', 'travel-packages' ),
                    ];
                    ?>
                    <div class="notice notice-<?php echo $viewing['payment_review'] ? 'warning' : ( $viewing['payment_status'] === 'approved' ? 'success' : 'info' ); ?> inline">
                        <p>
                        <strong><?php _e( 'Pago:', 'travel-packages' ); ?></strong>
                        <?php echo esc_html( $payment_status_labels[ $viewing['payment_status'] ] ?? $viewing['payment_status'] ); ?>
                        <?php if ( $viewing['payment_review'] ) : ?>
                            — <strong><?php _e( 'Requiere revisión manual por disponibilidad.', 'travel-packages' ); ?></strong>
                        <?php endif; ?>
                        <?php if ( $viewing['payment_amount_ars'] !== '' ) : ?>
                            <br><?php printf( esc_html__( 'Total cobrado: ARS %s', 'travel-packages' ), esc_html( number_format_i18n( (float) $viewing['payment_amount_ars'], 2 ) ) ); ?>
                            <?php if ( $viewing['payment_original_amount'] !== '' && $viewing['payment_currency'] !== '' ) : ?>
                                <span>(<?php echo esc_html( $viewing['payment_currency'] . ' ' . number_format_i18n( (float) $viewing['payment_original_amount'], 2 ) ); ?>)</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ( $viewing['payment_id'] !== '' ) : ?>
                            <br><?php printf( esc_html__( 'ID de pago: %s', 'travel-packages' ), esc_html( $viewing['payment_id'] ) ); ?>
                        <?php endif; ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    <dl class="tp-res-detail-grid">
                        <div><dt><?php _e( 'Cliente', 'travel-packages' ); ?></dt><dd><?php echo esc_html( $viewing['customer'] ?: __( 'Sin nombre', 'travel-packages' ) ); ?></dd></div>
                        <div><dt><?php _e( 'Paquete', 'travel-packages' ); ?></dt><dd><?php echo esc_html( $view_package_title ?: __( 'Paquete eliminado', 'travel-packages' ) ); ?></dd></div>
                        <div><dt><?php _e( 'Salida', 'travel-packages' ); ?></dt><dd><?php echo esc_html( trim( $viewing['from'] . ( $viewing['to'] ? ' → ' . $viewing['to'] : '' ) ) ?: '—' ); ?></dd></div>
                        <div><dt><?php _e( 'Email', 'travel-packages' ); ?></dt><dd><?php echo $viewing['email'] ? esc_html( $viewing['email'] ) : '—'; ?></dd></div>
                        <div><dt><?php _e( 'Teléfono', 'travel-packages' ); ?></dt><dd><?php echo $viewing['phone'] ? esc_html( $viewing['phone'] ) : '—'; ?></dd></div>
                        <div><dt><?php _e( 'Adultos', 'travel-packages' ); ?></dt><dd><?php echo (int) $viewing['adults']; ?></dd></div>
                        <div><dt><?php _e( 'Niños', 'travel-packages' ); ?></dt><dd><?php echo (int) $viewing['children']; ?></dd></div>
                        <div><dt><?php _e( 'Total de pasajeros', 'travel-packages' ); ?></dt><dd><?php echo (int) $viewing['pax']; ?></dd></div>
                        <div><dt><?php _e( 'Origen', 'travel-packages' ); ?></dt><dd><?php echo esc_html( ( $viewing['source'] ?? '' ) === 'web' ? __( 'Web', 'travel-packages' ) : __( 'Administrativa', 'travel-packages' ) ); ?></dd></div>
                        <div><dt><?php _e( 'Creada', 'travel-packages' ); ?></dt><dd><?php echo esc_html( $view_created ); ?></dd></div>
                        <div class="tp-res-detail-notes"><dt><?php _e( 'Notas', 'travel-packages' ); ?></dt><dd><?php echo $viewing['notes'] !== '' ? nl2br( esc_html( $viewing['notes'] ) ) : '—'; ?></dd></div>
                    </dl>
                    <div class="tp-panel-actions">
                        <a href="<?php echo esc_url( add_query_arg( [ 'page' => 'tp-reservations', 'edit' => $view_id ], admin_url( 'admin.php' ) ) ); ?>" class="tp-btn-primary"><?php _e( 'Editar reserva', 'travel-packages' ); ?></a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-reservations' ) ); ?>" class="tp-btn tp-btn-secondary"><?php _e( 'Volver al listado', 'travel-packages' ); ?></a>
                    </div>
                </section>
                <?php else : ?>
                <div class="tp-package-form tp-res-form">
                    <h2><?php echo $editing ? esc_html__( 'Editar reserva', 'travel-packages' ) : esc_html__( 'Nueva reserva', 'travel-packages' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tp-res-form">
                        <input type="hidden" name="action" value="tp_save_reservation">
                        <input type="hidden" name="reservation_id" value="<?php echo $editing ? (int) $editing['id'] : 0; ?>">
                        <?php wp_nonce_field( 'tp_reservation_save', 'tp_reservation_nonce' ); ?>

                        <div class="tp-field-grid tp-two-columns">
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_res_package"><?php _e( 'Paquete', 'travel-packages' ); ?></label>
                                <select name="tp_res_package" id="tp_res_package" required>
                                    <option value=""><?php _e( '-- Seleccionar --', 'travel-packages' ); ?></option>
                                    <?php foreach ( $packages as $package ) : ?>
                                    <option value="<?php echo (int) $package['id']; ?>" <?php selected( $editing ? (int) $editing['package'] : 0, $package['id'] ); ?>><?php echo esc_html( $package['title'] ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_res_departure"><?php _e( 'Salida', 'travel-packages' ); ?></label>
                                <select name="tp_res_departure" id="tp_res_departure" data-selected="<?php echo esc_attr( $editing['uid'] ?? '' ); ?>" required>
                                    <?php if ( $editing ) : ?>
                                        <?php foreach ( $packages as $package ) : ?>
                                            <?php if ( (int) $package['id'] !== (int) $editing['package'] ) continue; ?>
                                            <?php foreach ( $package['departures'] as $departure ) : ?>
                                            <option value="<?php echo esc_attr( $departure['uid'] ); ?>" <?php selected( $editing['uid'], $departure['uid'] ); ?>><?php echo esc_html( $departure['label'] ); ?></option>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_res_customer"><?php _e( 'Cliente', 'travel-packages' ); ?></label>
                                <input type="text" name="tp_res_customer" id="tp_res_customer" value="<?php echo esc_attr( $editing['customer'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Nombre y apellido', 'travel-packages' ); ?>" required>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_res_email"><?php _e( 'Email', 'travel-packages' ); ?></label>
                                <input type="email" name="tp_res_email" id="tp_res_email" value="<?php echo esc_attr( $editing['email'] ?? '' ); ?>">
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_res_phone"><?php _e( 'Teléfono', 'travel-packages' ); ?></label>
                                <input type="text" name="tp_res_phone" id="tp_res_phone" value="<?php echo esc_attr( $editing['phone'] ?? '' ); ?>">
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_res_status"><?php _e( 'Estado', 'travel-packages' ); ?></label>
                                <select name="tp_res_status" id="tp_res_status">
                                    <?php foreach ( $statuses as $key => $label ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $editing['status'] ?? 'pending', $key ); ?>><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_res_adults"><?php _e( 'Adultos', 'travel-packages' ); ?></label>
                                <input type="number" min="0" step="1" name="tp_res_adults" id="tp_res_adults" value="<?php echo (int) ( $editing['adults'] ?? 1 ); ?>">
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_res_children"><?php _e( 'Niños', 'travel-packages' ); ?></label>
                                <input type="number" min="0" step="1" name="tp_res_children" id="tp_res_children" value="<?php echo (int) ( $editing['children'] ?? 0 ); ?>">
                            </div>
                            <p class="tp-res-pax-total tp-field-full"><?php _e( 'Total de pasajeros:', 'travel-packages' ); ?> <strong id="tp_res_pax_total">0</strong></p>
                            <div class="tp-field-group tp-field-full">
                                <label class="tp-label" for="tp_res_notes"><?php _e( 'Notas', 'travel-packages' ); ?></label>
                                <textarea name="tp_res_notes" id="tp_res_notes" rows="3"><?php echo esc_textarea( $editing['notes'] ?? '' ); ?></textarea>
                            </div>
                        </div>

                        <div class="tp-panel-actions">
                            <button type="submit" class="tp-btn-primary"><?php echo $editing ? esc_html__( 'Guardar cambios', 'travel-packages' ) : esc_html__( 'Crear reserva', 'travel-packages' ); ?></button>
                            <?php if ( $editing ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-reservations' ) ); ?>" class="tp-btn tp-btn-secondary"><?php _e( 'Cancelar', 'travel-packages' ); ?></a>
                            <?php endif; ?>
                        </div>
                    </form>

                    <?php if ( $editing ) : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tp-res-delete-form" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar esta reserva?', 'travel-packages' ) ); ?>');">
                        <input type="hidden" name="action" value="tp_delete_reservation">
                        <input type="hidden" name="reservation_id" value="<?php echo (int) $editing['id']; ?>">
                        <?php wp_nonce_field( 'tp_reservation_delete', 'tp_reservation_delete_nonce' ); ?>
                        <button type="submit" class="tp-btn tp-btn-danger"><?php _e( 'Eliminar reserva', 'travel-packages' ); ?></button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
