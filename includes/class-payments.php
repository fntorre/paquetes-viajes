<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Optional Mercado Pago Checkout Pro integration.
 */
class TP_Payments {

    const OPTION_ENABLED      = 'tp_payments_enabled';
    const OPTION_ACCESS_TOKEN = 'tp_mp_access_token';
    const OPTION_WEBHOOK_KEY  = 'tp_mp_webhook_secret';
    const OPTION_FX_RATES     = 'tp_mp_ars_rates';

    const META_STATUS       = '_tp_payment_status';
    const META_AMOUNT       = '_tp_payment_amount_ars';
    const META_ORIGINAL     = '_tp_payment_original_amount';
    const META_CURRENCY     = '_tp_payment_original_currency';
    const META_FX_RATE      = '_tp_payment_fx_rate';
    const META_EXPIRES      = '_tp_payment_expires';
    const META_PREFERENCE   = '_tp_payment_preference_id';
    const META_PAYMENT_ID   = '_tp_payment_mp_id';
    const META_RETURN_TOKEN = '_tp_payment_return_token';
    const META_REVIEW       = '_tp_payment_review';
    const META_NOTIFICATION = '_tp_payment_notification';

    const HOLD_SECONDS = 900;

    public static function init() {
        add_action( 'admin_post_tp_start_payment', [ __CLASS__, 'handle_start_checkout' ] );
        add_action( 'admin_post_nopriv_tp_start_payment', [ __CLASS__, 'handle_start_checkout' ] );
        add_action( 'rest_api_init', [ __CLASS__, 'register_webhook_route' ] );
        add_action( 'tp_payment_expire_reservation', [ __CLASS__, 'expire_reservation' ] );
    }

    public static function is_enabled() {
        return get_option( self::OPTION_ENABLED, '0' ) === '1';
    }

    private static function access_token() {
        if ( defined( 'TP_MP_ACCESS_TOKEN' ) && TP_MP_ACCESS_TOKEN ) {
            return trim( (string) TP_MP_ACCESS_TOKEN );
        }
        return trim( (string) get_option( self::OPTION_ACCESS_TOKEN, '' ) );
    }

    private static function webhook_secret() {
        if ( defined( 'TP_MP_WEBHOOK_SECRET' ) && TP_MP_WEBHOOK_SECRET ) {
            return trim( (string) TP_MP_WEBHOOK_SECRET );
        }
        return trim( (string) get_option( self::OPTION_WEBHOOK_KEY, '' ) );
    }

    public static function is_checkout_available() {
        return self::is_enabled()
            && self::access_token() !== ''
            && self::webhook_secret() !== ''
            && class_exists( 'TP_Reservations' )
            && TP_Reservations::is_enabled();
    }

    public static function is_currency_supported( $currency ) {
        $currency = strtoupper( sanitize_key( (string) $currency ) );
        if ( $currency === 'ARS' ) {
            return true;
        }
        $rates = get_option( self::OPTION_FX_RATES, [] );
        return is_array( $rates ) && isset( $rates[ $currency ] ) && is_numeric( $rates[ $currency ] ) && (float) $rates[ $currency ] > 0;
    }

    public static function save_settings( $input ) {
        $enabled = isset( $input['tp_payments_enabled'] ) && $input['tp_payments_enabled'] === '1' ? '1' : '0';
        update_option( self::OPTION_ENABLED, $enabled, false );

        foreach ( [
            'tp_mp_access_token'  => self::OPTION_ACCESS_TOKEN,
            'tp_mp_webhook_secret' => self::OPTION_WEBHOOK_KEY,
        ] as $field => $option ) {
            $clear_field = $field . '_clear';
            if ( ! empty( $input[ $clear_field ] ) ) {
                delete_option( $option );
            } elseif ( isset( $input[ $field ] ) && is_string( $input[ $field ] ) && trim( wp_unslash( $input[ $field ] ) ) !== '' ) {
                update_option( $option, sanitize_text_field( trim( wp_unslash( $input[ $field ] ) ) ), false );
            }
        }

        $rates = [];
        foreach ( [ 'USD', 'EUR', 'BRL' ] as $currency ) {
            $field = 'tp_mp_rate_' . strtolower( $currency );
            $value = isset( $input[ $field ] ) && is_scalar( $input[ $field ] )
                ? str_replace( ',', '.', trim( (string) wp_unslash( $input[ $field ] ) ) )
                : '';
            $rate = is_numeric( $value ) ? (float) $value : 0;
            if ( is_finite( $rate ) && $rate > 0 && $rate < 1000000000 ) {
                $rates[ $currency ] = number_format( $rate, 6, '.', '' );
            }
        }
        update_option( self::OPTION_FX_RATES, $rates, false );
    }

    public static function settings_markup() {
        $rates = get_option( self::OPTION_FX_RATES, [] );
        if ( ! is_array( $rates ) ) {
            $rates = [];
        }
        $webhook_url = rest_url( 'tureo/v1/mercadopago/webhook' );
        ?>
        <div class="tp-settings-payment">
            <h2><?php _e( 'Pagos online (opcional)', 'travel-packages' ); ?></h2>
            <p class="tp-help"><?php _e( 'Permite cobrar el total del paquete con Mercado Pago Checkout Pro y retener cupos durante 15 minutos. Requiere que el módulo de Reservas esté activo.', 'travel-packages' ); ?></p>
            <label class="tp-toggle-row">
                <span><?php _e( 'Activar pagos con Mercado Pago', 'travel-packages' ); ?></span>
                <input type="checkbox" name="tp_payments_enabled" value="1" <?php checked( self::is_enabled() ); ?>>
            </label>
            <?php if ( ! class_exists( 'TP_Reservations' ) || ! TP_Reservations::is_enabled() ) : ?>
            <p class="tp-help"><?php _e( 'Primero activá el módulo de Reservas para habilitar el checkout y el control de cupos.', 'travel-packages' ); ?></p>
            <?php endif; ?>
            <div class="tp-payment-credentials tp-field-grid tp-two-columns">
                <div class="tp-field-group">
                    <label class="tp-label" for="tp_mp_access_token"><?php _e( 'Access Token', 'travel-packages' ); ?></label>
                    <input type="password" id="tp_mp_access_token" name="tp_mp_access_token" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( self::access_token() ? __( 'Configurado; dejar vacío para conservarlo', 'travel-packages' ) : __( 'Pegá el Access Token de Mercado Pago', 'travel-packages' ) ); ?>">
                    <small><?php _e( 'Usá credenciales de prueba para validar la integración y credenciales de producción cuando publiques. El token guardado nunca se vuelve a mostrar.', 'travel-packages' ); ?></small>
                    <?php if ( self::access_token() ) : ?>
                    <label class="tp-payment-clear"><input type="checkbox" name="tp_mp_access_token_clear" value="1"> <?php _e( 'Eliminar token guardado', 'travel-packages' ); ?></label>
                    <?php endif; ?>
                </div>
                <div class="tp-field-group">
                    <label class="tp-label" for="tp_mp_webhook_secret"><?php _e( 'Clave secreta de Webhooks', 'travel-packages' ); ?></label>
                    <input type="password" id="tp_mp_webhook_secret" name="tp_mp_webhook_secret" value="" autocomplete="new-password" placeholder="<?php echo esc_attr( self::webhook_secret() ? __( 'Configurada; dejar vacío para conservarla', 'travel-packages' ) : __( 'Pegá la clave secreta de Webhooks', 'travel-packages' ) ); ?>">
                    <small><?php _e( 'Se usa para verificar que las notificaciones de pago provengan de Mercado Pago.', 'travel-packages' ); ?></small>
                    <?php if ( self::webhook_secret() ) : ?>
                    <label class="tp-payment-clear"><input type="checkbox" name="tp_mp_webhook_secret_clear" value="1"> <?php _e( 'Eliminar clave guardada', 'travel-packages' ); ?></label>
                    <?php endif; ?>
                </div>
            </div>
            <section class="tp-payment-rates">
                <h3><?php _e( 'Conversión a pesos argentinos (ARS)', 'travel-packages' ); ?></h3>
                <p class="tp-help"><?php _e( 'Ingresá cuántos ARS equivale una unidad de cada moneda. La cotización es manual y debe mantenerse actualizada; Tureo no aplica conversiones implícitas.', 'travel-packages' ); ?></p>
                <div class="tp-payment-rate-grid">
                    <?php foreach ( [ 'USD' => 'Dólar estadounidense', 'EUR' => 'Euro', 'BRL' => 'Real brasileño' ] as $currency => $label ) : ?>
                    <div class="tp-field-group">
                        <label class="tp-label" for="tp_mp_rate_<?php echo esc_attr( strtolower( $currency ) ); ?>"><?php echo esc_html( $currency . ' → ARS' ); ?></label>
                        <span class="tp-payment-currency"><?php echo esc_html( $label ); ?></span>
                        <input type="number" id="tp_mp_rate_<?php echo esc_attr( strtolower( $currency ) ); ?>" name="tp_mp_rate_<?php echo esc_attr( strtolower( $currency ) ); ?>" min="0.000001" step="0.000001" value="<?php echo isset( $rates[ $currency ] ) ? esc_attr( $rates[ $currency ] ) : ''; ?>" placeholder="0">
                    </div>
                    <?php endforeach; ?>
                </div>
                <p class="tp-payment-hint"><?php _e( 'Los paquetes en ARS se cobran sin conversión. Si una moneda no tiene cotización, el botón de pago no se muestra para esa salida.', 'travel-packages' ); ?></p>
            </section>
            <div class="tp-payment-webhook">
                <div>
                    <strong><?php _e( 'URL de notificaciones', 'travel-packages' ); ?></strong>
                    <p><?php _e( 'Copiá esta dirección en la configuración de Webhooks de Mercado Pago.', 'travel-packages' ); ?></p>
                </div>
                <code><?php echo esc_html( $webhook_url ); ?></code>
            </div>
            <p class="tp-payment-security"><?php _e( 'Los secretos se guardan en WordPress y nunca vuelven a mostrarse. También podés definir TP_MP_ACCESS_TOKEN y TP_MP_WEBHOOK_SECRET en wp-config.php; esas constantes tienen prioridad.', 'travel-packages' ); ?></p>
        </div>
        <?php
    }

    public static function register_webhook_route() {
        register_rest_route( 'tureo/v1', '/mercadopago/webhook', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ __CLASS__, 'handle_webhook' ],
            'permission_callback' => '__return_true',
        ] );
    }

    public static function handle_start_checkout() {
        $package_id = isset( $_POST['tp_inquiry_package'] ) ? absint( wp_unslash( $_POST['tp_inquiry_package'] ) ) : 0;
        $redirect   = $package_id ? get_permalink( $package_id ) : home_url( '/' );
        if ( ! $redirect ) {
            $redirect = home_url( '/' );
        }
        $fail = static function( $code ) use ( $redirect ) {
            wp_safe_redirect( add_query_arg( 'tp_payment_error', sanitize_key( $code ), $redirect ) . '#tp-inquiry' );
            exit;
        };

        if ( ! self::is_checkout_available() ) {
            $fail( 'unavailable' );
        }
        if ( empty( $_POST['tp_inquiry_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tp_inquiry_nonce'] ) ), 'tp_public_inquiry' ) ) {
            $fail( 'nonce' );
        }
        if ( ! empty( $_POST['tp_website'] ) ) {
            $fail( 'invalid' );
        }
        if ( ! $package_id || get_post_type( $package_id ) !== TP_Package_Data::POST_TYPE || get_post_status( $package_id ) !== 'publish' ) {
            $fail( 'package' );
        }

        $name     = isset( $_POST['tp_inquiry_name'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_inquiry_name'] ) ) : '';
        $email    = isset( $_POST['tp_inquiry_email'] ) ? sanitize_email( wp_unslash( $_POST['tp_inquiry_email'] ) ) : '';
        $phone    = isset( $_POST['tp_inquiry_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_inquiry_phone'] ) ) : '';
        $adults   = isset( $_POST['tp_inquiry_adults'] ) ? absint( wp_unslash( $_POST['tp_inquiry_adults'] ) ) : 0;
        $children = isset( $_POST['tp_inquiry_children'] ) ? absint( wp_unslash( $_POST['tp_inquiry_children'] ) ) : 0;
        $notes    = isset( $_POST['tp_inquiry_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tp_inquiry_message'] ) ) : '';
        $uid      = isset( $_POST['tp_inquiry_uid'] ) ? sanitize_key( wp_unslash( $_POST['tp_inquiry_uid'] ) ) : '';

        if ( $name === '' || ! is_email( $email ) || empty( $_POST['tp_inquiry_consent'] ) || $adults < 1 || $adults + $children > 50 || $uid === '' ) {
            $fail( 'fields' );
        }
        if ( ! get_transient( 'tp_payment_' . md5( $package_id . '|' . self::request_ip() ) ) ) {
            set_transient( 'tp_payment_' . md5( $package_id . '|' . self::request_ip() ), '1', 30 );
        } else {
            $fail( 'throttle' );
        }

        global $wpdb;
        $lock_name = 'tpmp_' . md5( $package_id . '|' . $uid );
        $locked    = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 8)', $lock_name ) );
        if ( (string) $locked !== '1' ) {
            $fail( 'busy' );
        }

        $reservation_id = 0;
        $checkout_url   = '';
        $error_code     = '';
        try {
            TP_Reservations::clear_cache( $package_id );
            $departure = self::find_available_departure( $package_id, $uid, $adults + $children );
            if ( is_wp_error( $departure ) ) {
                $error_code = 'departure';
            } else {
                $rate = self::exchange_rate( $departure['currency'] );
                if ( $rate <= 0 ) {
                    $error_code = 'currency';
                } else {
                    $adult_price = (float) $departure['price_adult'];
                    $child_price = $departure['price_child'] !== '' ? (float) $departure['price_child'] : $adult_price;
                    if ( $adult_price <= 0 || ( $children > 0 && $child_price <= 0 ) ) {
                        $error_code = 'price';
                    } else {
                        $original_amount = round( $adult_price * $adults + $child_price * $children, 2 );
                        $amount_ars      = round( $original_amount * $rate, 2 );
                        if ( $amount_ars <= 0 || ! is_finite( $amount_ars ) ) {
                            $error_code = 'price';
                        } else {
                            $reservation_id = TP_Reservations::save( [
                                'package'  => $package_id,
                                'uid'      => $uid,
                                'from'     => $departure['from'],
                                'to'       => $departure['to'],
                                'customer' => $name,
                                'email'    => $email,
                                'phone'    => $phone,
                                'adults'   => $adults,
                                'children' => $children,
                                'status'   => 'pending',
                                'notes'    => $notes,
                                'source'   => 'web',
                            ] );
                            if ( is_wp_error( $reservation_id ) ) {
                                $reservation_id = 0;
                                $error_code     = 'save';
                            } else {
                                $expires      = time() + self::HOLD_SECONDS;
                                $return_token = wp_generate_password( 48, false, false );
                                update_post_meta( $reservation_id, self::META_STATUS, 'pending' );
                                update_post_meta( $reservation_id, self::META_AMOUNT, number_format( $amount_ars, 2, '.', '' ) );
                                update_post_meta( $reservation_id, self::META_ORIGINAL, number_format( $original_amount, 2, '.', '' ) );
                                update_post_meta( $reservation_id, self::META_CURRENCY, strtoupper( $departure['currency'] ) );
                                update_post_meta( $reservation_id, self::META_FX_RATE, number_format( $rate, 6, '.', '' ) );
                                update_post_meta( $reservation_id, self::META_EXPIRES, $expires );
                                update_post_meta( $reservation_id, self::META_RETURN_TOKEN, wp_hash( $return_token, 'tp_payment_return_' . $reservation_id ) );

                                $preference = self::create_preference( $reservation_id, $package_id, $name, $email, $amount_ars, $expires, $return_token );
                                if ( is_wp_error( $preference ) ) {
                                    update_post_meta( $reservation_id, self::META_STATUS, 'failed' );
                                    update_post_meta( $reservation_id, TP_Reservations::META_STATUS, 'cancelled' );
                                    $reservation_id = 0;
                                    $error_code     = 'gateway';
                                } else {
                                    update_post_meta( $reservation_id, self::META_PREFERENCE, sanitize_text_field( $preference['id'] ) );
                                    $checkout_url = $preference['url'];
                                    wp_schedule_single_event( $expires, 'tp_payment_expire_reservation', [ (int) $reservation_id ] );
                                }
                            }
                        }
                    }
                }
            }
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        }

        if ( $error_code !== '' ) {
            TP_Reservations::clear_cache( $package_id );
            $fail( $error_code );
        }
        TP_Reservations::send_payment_notification( $reservation_id );
        wp_redirect( $checkout_url );
        exit;
    }

    private static function request_ip() {
        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    }

    private static function find_available_departure( $package_id, $uid, $pax ) {
        foreach ( TP_Package_Data::get_departures( $package_id, true, true ) as $departure ) {
            if ( $departure['uid'] !== $uid ) {
                continue;
            }
            if ( $departure['status'] !== 'open' ) {
                return new WP_Error( 'tp_payment_departure', __( 'La salida ya no está disponible.', 'travel-packages' ) );
            }
            if ( $departure['seats_left'] !== null && $pax > (int) $departure['seats_left'] ) {
                return new WP_Error( 'tp_payment_capacity', __( 'No hay suficientes cupos para esa cantidad de pasajeros.', 'travel-packages' ) );
            }
            if ( $departure['min_pax'] !== null && $pax < (int) $departure['min_pax'] ) {
                return new WP_Error( 'tp_payment_min_pax', __( 'La cantidad de pasajeros es menor al mínimo permitido.', 'travel-packages' ) );
            }
            if ( $departure['max_pax'] !== null && $pax > (int) $departure['max_pax'] ) {
                return new WP_Error( 'tp_payment_max_pax', __( 'La cantidad de pasajeros supera el máximo permitido.', 'travel-packages' ) );
            }
            return $departure;
        }
        return new WP_Error( 'tp_payment_departure', __( 'Seleccioná una salida disponible.', 'travel-packages' ) );
    }

    private static function exchange_rate( $currency ) {
        $currency = strtoupper( sanitize_key( (string) $currency ) );
        if ( $currency === 'ARS' ) {
            return 1.0;
        }
        $rates = get_option( self::OPTION_FX_RATES, [] );
        return is_array( $rates ) && isset( $rates[ $currency ] ) && is_numeric( $rates[ $currency ] )
            ? (float) $rates[ $currency ]
            : 0.0;
    }

    private static function create_preference( $reservation_id, $package_id, $name, $email, $amount_ars, $expires, $return_token ) {
        $package_title = get_the_title( $package_id );
        $return_url    = add_query_arg( [
            'tp_payment_reservation' => (int) $reservation_id,
            'tp_payment_token'       => $return_token,
        ], get_permalink( $package_id ) );
        $payload = [
            'items' => [[
                'title'       => sanitize_text_field( $package_title ),
                'quantity'    => 1,
                'unit_price'  => (float) number_format( $amount_ars, 2, '.', '' ),
                'currency_id' => 'ARS',
            ]],
            'payer' => [
                'name'  => $name,
                'email' => $email,
            ],
            'external_reference' => (string) $reservation_id,
            'metadata'           => [ 'tp_reservation_id' => (int) $reservation_id ],
            'notification_url'   => rest_url( 'tureo/v1/mercadopago/webhook' ),
            'back_urls'          => [
                'success' => add_query_arg( 'tp_payment_result', 'success', $return_url ),
                'pending' => add_query_arg( 'tp_payment_result', 'pending', $return_url ),
                'failure' => add_query_arg( 'tp_payment_result', 'failure', $return_url ),
            ],
            'auto_return'           => 'approved',
            'expires'               => true,
            'expiration_date_from'  => gmdate( 'Y-m-d\TH:i:s.000P' ),
            'expiration_date_to'    => gmdate( 'Y-m-d\TH:i:s.000P', $expires ),
        ];

        $response = wp_remote_post( 'https://api.mercadopago.com/checkout/preferences', [
            'timeout'     => 20,
            'headers'     => [
                'Authorization'  => 'Bearer ' . self::access_token(),
                'Content-Type'   => 'application/json',
                'X-Idempotency-Key' => wp_generate_uuid4(),
            ],
            'body'        => wp_json_encode( $payload ),
            'data_format' => 'body',
        ] );
        if ( is_wp_error( $response ) ) {
            error_log( 'Tureo Mercado Pago preference request failed: ' . $response->get_error_message() );
            return new WP_Error( 'tp_mp_request', __( 'No se pudo iniciar el pago. Intentá nuevamente.', 'travel-packages' ) );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( $status < 200 || $status >= 300 || ! is_array( $body ) || empty( $body['id'] ) ) {
            error_log( 'Tureo Mercado Pago preference rejected with HTTP status ' . $status );
            return new WP_Error( 'tp_mp_preference', __( 'Mercado Pago no pudo iniciar el pago. Revisá la configuración.', 'travel-packages' ) );
        }

        $url = ! empty( $body['init_point'] ) ? $body['init_point'] : '';
        $host = $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) : '';
        $scheme = $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) : '';
        $valid_mp_host = $host === 'mercadopago.com'
            || $host === 'mercadopago.com.ar'
            || substr( $host, -16 ) === '.mercadopago.com'
            || substr( $host, -19 ) === '.mercadopago.com.ar';
        $safe_url = $url ? esc_url_raw( $url, [ 'https' ] ) : '';
        if ( ! $valid_mp_host || $scheme !== 'https' || $safe_url === '' ) {
            error_log( 'Tureo Mercado Pago returned an invalid checkout URL.' );
            return new WP_Error( 'tp_mp_url', __( 'Mercado Pago devolvió una dirección de pago inválida.', 'travel-packages' ) );
        }

        return [ 'id' => sanitize_text_field( (string) $body['id'] ), 'url' => $safe_url ];
    }

    public static function register_expiration( $reservation_id, $expires ) {
        wp_schedule_single_event( (int) $expires, 'tp_payment_expire_reservation', [ (int) $reservation_id ] );
    }

    public static function expire_reservation( $reservation_id ) {
        $reservation_id = (int) $reservation_id;
        if ( ! $reservation_id || get_post_type( $reservation_id ) !== TP_Reservations::POST_TYPE ) {
            return false;
        }

        global $wpdb;
        $lock_name = 'tpmp_res_' . $reservation_id;
        $locked    = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );
        if ( (string) $locked !== '1' ) {
            wp_schedule_single_event( time() + 30, 'tp_payment_expire_reservation', [ $reservation_id ] );
            return false;
        }

        try {
            $expires = (int) get_post_meta( $reservation_id, self::META_EXPIRES, true );
            if ( $expires <= 0 || $expires > time() || get_post_meta( $reservation_id, self::META_STATUS, true ) !== 'pending' ) {
                return false;
            }
            if ( get_post_meta( $reservation_id, TP_Reservations::META_STATUS, true ) !== 'pending' ) {
                return false;
            }

            update_post_meta( $reservation_id, self::META_STATUS, 'expired' );
            update_post_meta( $reservation_id, TP_Reservations::META_STATUS, 'cancelled' );
            TP_Reservations::clear_cache( (int) get_post_meta( $reservation_id, TP_Reservations::META_PACKAGE, true ) );
            return true;
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        }
    }

    public static function expire_if_due( $reservation_id ) {
        return self::expire_reservation( $reservation_id );
    }

    public static function handle_webhook( WP_REST_Request $request ) {
        if ( ! self::is_enabled() || self::webhook_secret() === '' ) {
            return new WP_REST_Response( [ 'error' => 'not_configured' ], 503 );
        }
        if ( ! self::valid_signature( $request ) ) {
            return new WP_REST_Response( [ 'error' => 'invalid_signature' ], 401 );
        }

        $params = $request->get_json_params();
        $data   = isset( $params['data'] ) && is_array( $params['data'] ) ? $params['data'] : [];
        $payment_id = isset( $data['id'] ) ? sanitize_text_field( (string) $data['id'] ) : sanitize_text_field( (string) $request->get_param( 'data.id' ) );
        if ( $payment_id === '' ) {
            return new WP_REST_Response( [ 'received' => true ], 200 );
        }

        $result = self::sync_payment( $payment_id );
        if ( is_wp_error( $result ) ) {
            error_log( 'Tureo Mercado Pago webhook could not reconcile payment: ' . $result->get_error_code() );
            return new WP_REST_Response( [ 'error' => 'reconciliation_failed' ], 500 );
        }
        return new WP_REST_Response( [ 'received' => true ], 200 );
    }

    private static function valid_signature( WP_REST_Request $request ) {
        $signature = (string) $request->get_header( 'x-signature' );
        $request_id = (string) $request->get_header( 'x-request-id' );
        $params = $request->get_json_params();
        $data = isset( $params['data'] ) && is_array( $params['data'] ) ? $params['data'] : [];
        $payment_id = isset( $data['id'] ) ? (string) $data['id'] : (string) $request->get_param( 'data.id' );
        if ( $signature === '' || $request_id === '' || $payment_id === '' ) {
            return false;
        }

        $parts = [];
        foreach ( explode( ',', $signature ) as $component ) {
            $pair = explode( '=', trim( $component ), 2 );
            if ( count( $pair ) === 2 ) {
                $parts[ trim( $pair[0] ) ] = trim( $pair[1] );
            }
        }
        if ( empty( $parts['ts'] ) || empty( $parts['v1'] ) ) {
            return false;
        }
        $manifest = 'id:' . strtolower( $payment_id ) . ';request-id:' . $request_id . ';ts:' . $parts['ts'] . ';';
        $expected = hash_hmac( 'sha256', $manifest, self::webhook_secret() );
        return hash_equals( $expected, strtolower( $parts['v1'] ) );
    }

    private static function sync_payment( $payment_id ) {
        $response = wp_remote_get( 'https://api.mercadopago.com/v1/payments/' . rawurlencode( $payment_id ), [
            'timeout' => 15,
            'headers' => [ 'Authorization' => 'Bearer ' . self::access_token() ],
        ] );
        if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return new WP_Error( 'tp_mp_payment_lookup', 'Could not verify payment at Mercado Pago.' );
        }
        $payment = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $payment ) || empty( $payment['external_reference'] ) || empty( $payment['id'] ) || (string) $payment['id'] !== (string) $payment_id ) {
            return new WP_Error( 'tp_mp_payment_data', 'Payment response is missing its reservation reference.' );
        }

        $reservation_id = absint( $payment['external_reference'] );
        if ( ! $reservation_id ) {
            return new WP_Error( 'tp_mp_reservation', 'Payment reservation reference is invalid.' );
        }

        global $wpdb;
        $lock_name = 'tpmp_res_' . $reservation_id;
        $locked    = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 8)', $lock_name ) );
        if ( (string) $locked !== '1' ) {
            return new WP_Error( 'tp_mp_reservation_busy', 'Payment reservation is being updated.' );
        }

        $notify_approved = false;
        try {
            $reservation = TP_Reservations::get_reservation( $reservation_id );
            $preference_id = (string) get_post_meta( $reservation_id, self::META_PREFERENCE, true );
            if ( ! $reservation || $preference_id === '' || empty( $payment['preference_id'] ) || ! hash_equals( $preference_id, (string) $payment['preference_id'] ) ) {
                return new WP_Error( 'tp_mp_reservation', 'Payment reservation does not exist.' );
            }
            $expected_amount = (int) round( (float) get_post_meta( $reservation_id, self::META_AMOUNT, true ) * 100 );
            $actual_amount   = (int) round( (float) ( $payment['transaction_amount'] ?? 0 ) * 100 );
            if ( $actual_amount !== $expected_amount || strtoupper( (string) ( $payment['currency_id'] ?? '' ) ) !== 'ARS' ) {
                return new WP_Error( 'tp_mp_amount_mismatch', 'Payment amount or currency does not match the reservation.' );
            }

            $status = sanitize_key( (string) ( $payment['status'] ?? '' ) );
            update_post_meta( $reservation_id, self::META_PAYMENT_ID, sanitize_text_field( (string) $payment['id'] ) );
            if ( $status === 'approved' ) {
                update_post_meta( $reservation_id, self::META_STATUS, 'approved' );
                if ( $reservation['status'] !== 'confirmed' ) {
                    if ( $reservation['status'] === 'pending' && (int) get_post_meta( $reservation_id, self::META_EXPIRES, true ) > time() ) {
                        update_post_meta( $reservation_id, TP_Reservations::META_STATUS, 'confirmed' );
                    } else {
                        update_post_meta( $reservation_id, self::META_REVIEW, '1' );
                    }
                }
                if ( get_post_meta( $reservation_id, self::META_NOTIFICATION, true ) !== 'approved' ) {
                    update_post_meta( $reservation_id, self::META_NOTIFICATION, 'approved' );
                    $notify_approved = true;
                }
            } elseif ( in_array( $status, [ 'rejected', 'cancelled', 'refunded', 'charged_back' ], true ) ) {
                update_post_meta( $reservation_id, self::META_STATUS, $status );
                if ( $reservation['status'] === 'pending' ) {
                    update_post_meta( $reservation_id, TP_Reservations::META_STATUS, 'cancelled' );
                } elseif ( $reservation['status'] === 'confirmed' ) {
                    update_post_meta( $reservation_id, self::META_REVIEW, '1' );
                }
            } else {
                update_post_meta( $reservation_id, self::META_STATUS, 'pending' );
            }

            TP_Reservations::clear_cache( $reservation['package'] );
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        }
        if ( $notify_approved && ! TP_Reservations::send_payment_notification( $reservation_id, true ) ) {
            update_post_meta( $reservation_id, self::META_NOTIFICATION, 'pending' );
        }
        return true;
    }

    public static function return_notice( $reservation_id, $token ) {
        $reservation_id = absint( $reservation_id );
        $token = is_string( $token ) ? sanitize_text_field( wp_unslash( $token ) ) : '';
        $stored = (string) get_post_meta( $reservation_id, self::META_RETURN_TOKEN, true );
        if ( ! $reservation_id || $token === '' || $stored === '' || ! hash_equals( $stored, wp_hash( $token, 'tp_payment_return_' . $reservation_id ) ) ) {
            return '';
        }

        $payment_status = (string) get_post_meta( $reservation_id, self::META_STATUS, true );
        if ( $payment_status === 'approved' && get_post_meta( $reservation_id, self::META_REVIEW, true ) !== '1' ) {
            $message = __( '¡Pago aprobado! Tu reserva quedó confirmada.', 'travel-packages' );
            $class = 'is-success';
        } elseif ( get_post_meta( $reservation_id, self::META_REVIEW, true ) === '1' ) {
            $message = __( 'El pago requiere una revisión manual de la reserva. Nos comunicaremos con vos.', 'travel-packages' );
            $class = 'is-error';
        } elseif ( $payment_status === 'expired' || in_array( $payment_status, [ 'rejected', 'cancelled' ], true ) || ( in_array( $payment_status, [ 'refunded', 'charged_back' ], true ) && get_post_meta( $reservation_id, TP_Reservations::META_STATUS, true ) === 'cancelled' ) ) {
            $message = __( 'El pago no se completó y el cupo fue liberado. Podés volver a intentarlo.', 'travel-packages' );
            $class = 'is-error';
        } elseif ( in_array( $payment_status, [ 'refunded', 'charged_back' ], true ) ) {
            $message = __( 'El estado del pago cambió y requiere seguimiento. Nos comunicaremos con vos.', 'travel-packages' );
            $class = 'is-error';
        } else {
            $message = __( 'Estamos verificando tu pago. La confirmación puede demorar unos instantes.', 'travel-packages' );
            $class = '';
        }

        return '<div class="tp-inquiry-notice ' . esc_attr( $class ) . '">' . esc_html( $message ) . '</div>';
    }

    public static function error_notice( $code ) {
        $messages = [
            'unavailable' => __( 'El pago online no está disponible en este momento.', 'travel-packages' ),
            'nonce'       => __( 'La sesión expiró. Recargá la página e intentá de nuevo.', 'travel-packages' ),
            'invalid'     => __( 'No pudimos procesar la solicitud.', 'travel-packages' ),
            'package'     => __( 'El paquete ya no está disponible.', 'travel-packages' ),
            'fields'      => __( 'Completá tus datos, elegí una salida disponible y verificá la cantidad de pasajeros.', 'travel-packages' ),
            'throttle'    => __( 'Esperá unos segundos antes de iniciar otro pago.', 'travel-packages' ),
            'busy'        => __( 'Estamos procesando otra reserva para esta salida. Intentá nuevamente en unos segundos.', 'travel-packages' ),
            'departure'   => __( 'La salida ya no está disponible o no tiene cupos suficientes.', 'travel-packages' ),
            'currency'    => __( 'No hay una cotización a pesos configurada para la moneda de esta salida.', 'travel-packages' ),
            'price'       => __( 'El precio de esta salida no está configurado correctamente.', 'travel-packages' ),
            'save'        => __( 'No pudimos crear la reserva de pago. Intentá nuevamente.', 'travel-packages' ),
            'gateway'     => __( 'No se pudo iniciar el pago con Mercado Pago. Verificá la configuración o intentá más tarde.', 'travel-packages' ),
        ];
        return $messages[ sanitize_key( (string) $code ) ] ?? $messages['gateway'];
    }
}
