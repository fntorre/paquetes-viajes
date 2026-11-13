<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TP_Settings
 *
 * Ajustes generales de la agencia: WhatsApp, email de consultas,
 * activación del formulario público, paleta cromática y tipografía del frontend.
 */
class TP_Settings {

    const OPTION_WHATSAPP = 'tp_whatsapp_number';
    const OPTION_EMAIL    = 'tp_inquiry_email';
    const OPTION_INQUIRY  = 'tp_inquiry_enabled';
    const OPTION_PALETTE  = 'tp_color_palette';
    const OPTION_FONTS    = 'tp_font_pairing';

    const OPTION_SEARCH_PAGE        = 'tp_search_page_id';
    const OPTION_SEARCH_PLACEHOLDER = 'tp_search_placeholder';
    const OPTION_SEARCH_BUTTON      = 'tp_search_button';
    const OPTION_SEARCH_DESTINO     = 'tp_search_show_destino';
    const OPTION_SEARCH_TIPO        = 'tp_search_show_tipo';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_submenu_page' ], 22 );
        add_action( 'admin_post_tp_save_settings', [ __CLASS__, 'handle_save' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_fonts' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_fonts' ] );
        add_filter( 'body_class', [ __CLASS__, 'add_body_class' ] );
    }

    public static function get_palettes() {
        return [
            'default' => [
                'label'       => __( 'Noche Índigo', 'travel-packages' ),
                'description' => __( 'Índigo profundo, rosa neón y destellos aqua.', 'travel-packages' ),
                'colors'      => [
                    'primary' => '#3a0ca3',
                    'accent'  => '#f72585',
                    'aqua'    => '#4cc9f0',
                    'bg'      => '#fffcf9',
                ],
            ],
            'cool' => [
                'label'       => __( 'Brisa Marina', 'travel-packages' ),
                'description' => __( 'Azules oceánicos, cian y espuma de mar.', 'travel-packages' ),
                'colors'      => [
                    'primary' => '#0f4c81',
                    'accent'  => '#00a8cc',
                    'aqua'    => '#5ad1e6',
                    'bg'      => '#f7fbff',
                ],
            ],
            'warm' => [
                'label'       => __( 'Atardecer en la Playa', 'travel-packages' ),
                'description' => __( 'Terracota, naranja y luz dorada de atardecer.', 'travel-packages' ),
                'colors'      => [
                    'primary' => '#9a3412',
                    'accent'  => '#f97316',
                    'aqua'    => '#fbbf24',
                    'bg'      => '#fffaf5',
                ],
            ],
            'grayscale' => [
                'label'       => __( 'Cumbre Nublada', 'travel-packages' ),
                'description' => __( 'Grises de montaña, niebla y piedra.', 'travel-packages' ),
                'colors'      => [
                    'primary' => '#334155',
                    'accent'  => '#64748b',
                    'aqua'    => '#cbd5e1',
                    'bg'      => '#f8fafc',
                ],
            ],
            'jungle' => [
                'label'       => __( 'Selva Esmeralda', 'travel-packages' ),
                'description' => __( 'Verdes selváticos, lima y rocío tropical.', 'travel-packages' ),
                'colors'      => [
                    'primary' => '#065f46',
                    'accent'  => '#10b981',
                    'aqua'    => '#a3e635',
                    'bg'      => '#f4fbf6',
                ],
            ],
            'desert' => [
                'label'       => __( 'Dunas al Amanecer', 'travel-packages' ),
                'description' => __( 'Arena dorada, ámbar y primera luz del día.', 'travel-packages' ),
                'colors'      => [
                    'primary' => '#92400e',
                    'accent'  => '#f59e0b',
                    'aqua'    => '#fde68a',
                    'bg'      => '#fffbf0',
                ],
            ],
            'aurora' => [
                'label'       => __( 'Aurora Boreal', 'travel-packages' ),
                'description' => __( 'Noche polar, turquesa y destellos de aurora.', 'travel-packages' ),
                'colors'      => [
                    'primary' => '#1e1b4b',
                    'accent'  => '#06b6d4',
                    'aqua'    => '#a7f3d0',
                    'bg'      => '#f5f7ff',
                ],
            ],
            'wine' => [
                'label'       => __( 'Ruta del Vino', 'travel-packages' ),
                'description' => __( 'Borgoña, rosa profundo y notas doradas.', 'travel-packages' ),
                'colors'      => [
                    'primary' => '#6b0f2a',
                    'accent'  => '#be123c',
                    'aqua'    => '#f59e0b',
                    'bg'      => '#fff7f5',
                ],
            ],
        ];
    }

    public static function get_font_pairings() {
        return [
            'theme' => [
                'label'       => __( 'Tipografía del tema', 'travel-packages' ),
                'description' => __( 'Mantiene las fuentes del tema activo.', 'travel-packages' ),
                'heading'     => '',
                'body'        => '',
                'google_url'  => '',
            ],
            'modern' => [
                'label'       => __( 'Moderna', 'travel-packages' ),
                'description' => __( 'Poppins + Inter', 'travel-packages' ),
                'heading'     => "'Poppins', sans-serif",
                'body'        => "'Inter', sans-serif",
                'google_url'  => 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap',
            ],
            'elegant' => [
                'label'       => __( 'Elegante', 'travel-packages' ),
                'description' => __( 'Playfair Display + Lato', 'travel-packages' ),
                'heading'     => "'Playfair Display', serif",
                'body'        => "'Lato', sans-serif",
                'google_url'  => 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Playfair+Display:wght@600;700&display=swap',
            ],
            'adventure' => [
                'label'       => __( 'Aventura', 'travel-packages' ),
                'description' => __( 'Bebas Neue + Nunito Sans', 'travel-packages' ),
                'heading'     => "'Bebas Neue', sans-serif",
                'body'        => "'Nunito Sans', sans-serif",
                'google_url'  => 'https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Nunito+Sans:wght@400;600;700&display=swap',
            ],
            'editorial' => [
                'label'       => __( 'Editorial', 'travel-packages' ),
                'description' => __( 'DM Serif Display + DM Sans', 'travel-packages' ),
                'heading'     => "'DM Serif Display', serif",
                'body'        => "'DM Sans', sans-serif",
                'google_url'  => 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&family=DM+Serif+Display&display=swap',
            ],
        ];
    }

    public static function get_palette() {
        $palette  = (string) get_option( self::OPTION_PALETTE, 'default' );
        $palettes = self::get_palettes();

        return isset( $palettes[ $palette ] ) ? $palette : 'default';
    }

    public static function get_font_pairing() {
        $pairing  = (string) get_option( self::OPTION_FONTS, 'theme' );
        $pairings = self::get_font_pairings();

        return isset( $pairings[ $pairing ] ) ? $pairing : 'theme';
    }

    public static function get_search_page_id() {
        return (int) get_option( self::OPTION_SEARCH_PAGE, 0 );
    }

    public static function get_search_url() {
        $page_id = self::get_search_page_id();
        if ( ! $page_id ) {
            return '';
        }

        $permalink = get_permalink( $page_id );
        return $permalink ? $permalink : '';
    }

    public static function get_search_placeholder() {
        $value = (string) get_option( self::OPTION_SEARCH_PLACEHOLDER, '' );
        return $value !== '' ? $value : __( '¿A dónde querés viajar?', 'travel-packages' );
    }

    public static function get_search_button() {
        $value = (string) get_option( self::OPTION_SEARCH_BUTTON, '' );
        return $value !== '' ? $value : __( 'Buscar', 'travel-packages' );
    }

    public static function is_search_destino_enabled() {
        return get_option( self::OPTION_SEARCH_DESTINO, '1' ) === '1';
    }

    public static function is_search_tipo_enabled() {
        return get_option( self::OPTION_SEARCH_TIPO, '1' ) === '1';
    }

    public static function enqueue_fonts() {
        $pairing  = self::get_font_pairing();
        $pairings = self::get_font_pairings();

        if ( empty( $pairings[ $pairing ]['google_url'] ) ) {
            return;
        }

        wp_enqueue_style( 'tp-google-fonts', $pairings[ $pairing ]['google_url'], [], null );
    }

    public static function enqueue_admin_fonts() {
        $page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
        $pages  = [ 'tp-dashboard', 'tp-packages', 'tp-package-new', 'tp-calendar', 'tp-shortcodes', 'tp-reservations', 'tp-settings', 'tp-custom-fields' ];
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $is_tp  = in_array( $page, $pages, true ) || ( $screen && isset( $screen->post_type ) && $screen->post_type === 'paquete_viaje' );

        if ( ! $is_tp ) {
            return;
        }

        if ( $page === 'tp-settings' ) {
            $url = 'https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&family=DM+Serif+Display&family=Inter:wght@400;500;600;700&family=Lato:wght@400;700&family=Nunito+Sans:wght@400;600;700&family=Playfair+Display:wght@600;700&family=Poppins:wght@600;700;800&family=Ubuntu:wght@400;500;700&display=swap';
        } else {
            $url = 'https://fonts.googleapis.com/css2?family=Ubuntu:wght@400;500;700&display=swap';
        }

        wp_enqueue_style( 'tp-admin-fonts', $url, [], null );

        if ( $page === 'tp-package-new' ) {
            $pairing  = self::get_font_pairing();
            $pairings = self::get_font_pairings();
            if ( ! empty( $pairings[ $pairing ]['google_url'] ) ) {
                wp_enqueue_style( 'tp-admin-promo-fonts', $pairings[ $pairing ]['google_url'], [], null );
            }
        }
    }

    public static function add_body_class( $classes ) {
        $palette = self::get_palette();
        if ( $palette !== 'default' ) {
            $classes[] = 'tp-palette-' . $palette;
        }

        $font_pairing = self::get_font_pairing();
        if ( $font_pairing !== 'theme' ) {
            $classes[] = 'tp-font-' . $font_pairing;
        }

        return $classes;
    }

    public static function get_whatsapp_number() {
        $raw = (string) get_option( self::OPTION_WHATSAPP, '' );
        return preg_replace( '/\D+/', '', $raw );
    }

    public static function get_inquiry_email() {
        $email = sanitize_email( (string) get_option( self::OPTION_EMAIL, '' ) );
        if ( ! $email ) {
            $email = sanitize_email( get_option( 'admin_email' ) );
        }
        return $email;
    }

    public static function is_inquiry_enabled() {
        return get_option( self::OPTION_INQUIRY, '1' ) === '1';
    }

    public static function whatsapp_url( $message = '', $number = '' ) {
        $number = preg_replace( '/\D+/', '', (string) $number );
        if ( $number === '' ) {
            $number = self::get_whatsapp_number();
        }
        if ( $number === '' ) {
            return '';
        }

        $url = 'https://wa.me/' . $number;
        if ( $message !== '' ) {
            $url .= '?text=' . rawurlencode( $message );
        }

        return $url;
    }

    public static function add_submenu_page() {
        add_submenu_page(
            'tp-dashboard',
            __( 'Ajustes', 'travel-packages' ),
            __( 'Ajustes', 'travel-packages' ),
            'manage_options',
            'tp-settings',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function handle_save() {
        if ( ! isset( $_POST['tp_settings_nonce'] ) || ! wp_verify_nonce( $_POST['tp_settings_nonce'], 'tp_save_settings' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        $whatsapp = isset( $_POST['tp_whatsapp_number'] ) ? preg_replace( '/\D+/', '', (string) wp_unslash( $_POST['tp_whatsapp_number'] ) ) : '';
        $email    = isset( $_POST['tp_inquiry_email'] ) ? sanitize_email( wp_unslash( $_POST['tp_inquiry_email'] ) ) : '';
        $inquiry  = isset( $_POST['tp_inquiry_enabled'] ) && $_POST['tp_inquiry_enabled'] === '1' ? '1' : '0';
        $palette  = isset( $_POST['tp_color_palette'] ) ? sanitize_key( wp_unslash( $_POST['tp_color_palette'] ) ) : 'default';
        $palettes = self::get_palettes();
        if ( ! isset( $palettes[ $palette ] ) ) {
            $palette = 'default';
        }

        $font_pairing  = isset( $_POST['tp_font_pairing'] ) ? sanitize_key( wp_unslash( $_POST['tp_font_pairing'] ) ) : 'theme';
        $font_pairings = self::get_font_pairings();
        if ( ! isset( $font_pairings[ $font_pairing ] ) ) {
            $font_pairing = 'theme';
        }

        $search_page        = isset( $_POST['tp_search_page_id'] ) ? absint( wp_unslash( $_POST['tp_search_page_id'] ) ) : 0;
        $search_placeholder = isset( $_POST['tp_search_placeholder'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_search_placeholder'] ) ) : '';
        $search_button      = isset( $_POST['tp_search_button'] ) ? sanitize_text_field( wp_unslash( $_POST['tp_search_button'] ) ) : '';
        $search_destino     = isset( $_POST['tp_search_show_destino'] ) && $_POST['tp_search_show_destino'] === '1' ? '1' : '0';
        $search_tipo        = isset( $_POST['tp_search_show_tipo'] ) && $_POST['tp_search_show_tipo'] === '1' ? '1' : '0';

        update_option( self::OPTION_WHATSAPP, $whatsapp );
        update_option( self::OPTION_EMAIL, $email );
        update_option( self::OPTION_INQUIRY, $inquiry );
        update_option( self::OPTION_PALETTE, $palette );
        update_option( self::OPTION_FONTS, $font_pairing );
        update_option( self::OPTION_SEARCH_PAGE, $search_page );
        update_option( self::OPTION_SEARCH_PLACEHOLDER, $search_placeholder );
        update_option( self::OPTION_SEARCH_BUTTON, $search_button );
        update_option( self::OPTION_SEARCH_DESTINO, $search_destino );
        update_option( self::OPTION_SEARCH_TIPO, $search_tipo );

        wp_safe_redirect( admin_url( 'admin.php?page=tp-settings&status=saved' ) );
        exit;
    }

    public static function render_page() {
        $whatsapp        = (string) get_option( self::OPTION_WHATSAPP, '' );
        $email           = (string) get_option( self::OPTION_EMAIL, '' );
        $inquiry         = self::is_inquiry_enabled();
        $palette_current = self::get_palette();
        $palettes        = self::get_palettes();
        $font_current    = self::get_font_pairing();
        $font_pairings   = self::get_font_pairings();
        $search_page_id  = self::get_search_page_id();
        $search_placeholder = (string) get_option( self::OPTION_SEARCH_PLACEHOLDER, '' );
        $search_button   = (string) get_option( self::OPTION_SEARCH_BUTTON, '' );
        $search_destino  = self::is_search_destino_enabled();
        $search_tipo     = self::is_search_tipo_enabled();
        $notice          = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
        ?>
        <div class="wrap tp-admin-wrap tp-settings-wrap">
            <div class="tp-admin-header">
                <div class="tp-header-left tp-tureo-header-left">
                    <?php TP_Admin::render_brand(); ?>
                    <div class="tp-tureo-heading">
                        <h1><?php _e( 'Ajustes generales', 'travel-packages' ); ?></h1>
                        <p><?php _e( 'Configurá el contacto, las consultas, la paleta visual y la tipografía del frontend.', 'travel-packages' ); ?></p>
                    </div>
                    <?php TP_Admin::render_version_badge(); ?>
                </div>
            </div>

            <?php if ( $notice === 'saved' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php _e( 'Ajustes guardados.', 'travel-packages' ); ?></p></div>
            <?php endif; ?>

            <div class="tp-package-form">
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="tp_save_settings">
                    <?php wp_nonce_field( 'tp_save_settings', 'tp_settings_nonce' ); ?>

                    <div class="tp-field-grid tp-two-columns">
                        <div class="tp-field-group">
                            <label class="tp-label" for="tp_whatsapp_number"><?php _e( 'WhatsApp de la agencia', 'travel-packages' ); ?></label>
                            <input type="text" name="tp_whatsapp_number" id="tp_whatsapp_number" value="<?php echo esc_attr( $whatsapp ); ?>" placeholder="5491123456789">
                            <small><?php _e( 'Código de país + número, sin +, espacios ni guiones. Ej: 5491123456789. Se usa por defecto en todos los paquetes; podés reemplazarlo en cada uno.', 'travel-packages' ); ?></small>
                        </div>
                        <div class="tp-field-group">
                            <label class="tp-label" for="tp_inquiry_email"><?php _e( 'Email para consultas', 'travel-packages' ); ?></label>
                            <input type="email" name="tp_inquiry_email" id="tp_inquiry_email" value="<?php echo esc_attr( $email ); ?>" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
                            <small><?php _e( 'Si lo dejás vacío, se usa el email del administrador de WordPress.', 'travel-packages' ); ?></small>
                        </div>
                    </div>

                    <label class="tp-toggle-row" style="margin-top:8px;">
                        <span><?php _e( 'Mostrar formulario de consulta en la ficha del paquete', 'travel-packages' ); ?></span>
                        <input type="checkbox" name="tp_inquiry_enabled" value="1" <?php checked( $inquiry ); ?>>
                    </label>
                    <p class="tp-help"><?php _e( 'Las consultas se guardan como reservas pendientes en el módulo de Reservas. Si el módulo está desactivado, no descuentan cupos.', 'travel-packages' ); ?></p>

                    <div class="tp-palette-section">
                        <span class="tp-label"><?php _e( 'Paleta cromática del frontend', 'travel-packages' ); ?></span>
                        <p class="tp-help"><?php _e( 'Se aplica a sliders, grillas, fichas de paquetes, formularios y reservas.', 'travel-packages' ); ?></p>
                        <div class="tp-palette-grid">
                            <?php foreach ( $palettes as $slug => $palette ) : ?>
                                <label class="tp-palette-card">
                                    <input type="radio" name="tp_color_palette" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $palette_current, $slug ); ?>>
                                    <span class="tp-palette-preview" style="--tp-preview-primary: <?php echo esc_attr( $palette['colors']['primary'] ); ?>; --tp-preview-accent: <?php echo esc_attr( $palette['colors']['accent'] ); ?>; --tp-preview-aqua: <?php echo esc_attr( $palette['colors']['aqua'] ); ?>; --tp-preview-bg: <?php echo esc_attr( $palette['colors']['bg'] ); ?>;">
                                        <span class="tp-palette-swatch"></span>
                                        <span class="tp-palette-swatch"></span>
                                        <span class="tp-palette-swatch"></span>
                                    </span>
                                    <span class="tp-palette-info">
                                        <strong><?php echo esc_html( $palette['label'] ); ?></strong>
                                        <small><?php echo esc_html( $palette['description'] ); ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tp-typography-section">
                        <span class="tp-label"><?php _e( 'Tipografía del frontend', 'travel-packages' ); ?></span>
                        <p class="tp-help"><?php _e( 'Elegí un par de fuentes para títulos y textos. La opción del tema mantiene las fuentes actuales.', 'travel-packages' ); ?></p>
                        <div class="tp-typography-grid">
                            <?php foreach ( $font_pairings as $slug => $font ) : ?>
                                <label class="tp-typography-card">
                                    <input type="radio" name="tp_font_pairing" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $font_current, $slug ); ?>>
                                    <span class="tp-typography-preview" style="--tp-preview-heading: <?php echo esc_attr( $font['heading'] ? $font['heading'] : 'inherit' ); ?>; --tp-preview-body: <?php echo esc_attr( $font['body'] ? $font['body'] : 'inherit' ); ?>;">
                                        <strong>Aa</strong>
                                        <span>Viajes que inspiran</span>
                                    </span>
                                    <span class="tp-typography-info">
                                        <strong><?php echo esc_html( $font['label'] ); ?></strong>
                                        <small><?php echo esc_html( $font['description'] ); ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tp-search-section">
                        <span class="tp-label"><?php _e( 'Buscador de paquetes', 'travel-packages' ); ?></span>
                        <p class="tp-help"><?php _e( 'Configurá el buscador que podés insertar en cualquier parte del template con el shortcode de abajo.', 'travel-packages' ); ?></p>

                        <div class="tp-field-group">
                            <label class="tp-label" for="tp_search_page_id"><?php _e( 'Página de resultados', 'travel-packages' ); ?></label>
                            <?php
                            wp_dropdown_pages([
                                'name'              => 'tp_search_page_id',
                                'id'                => 'tp_search_page_id',
                                'selected'          => $search_page_id,
                                'show_option_none'  => __( 'Misma página (sin redirección)', 'travel-packages' ),
                                'option_none_value' => '0',
                            ]);
                            ?>
                            <small><?php _e( 'Página donde se enviará la búsqueda. Debe contener el shortcode [tp_catalogo]. Si no elegís ninguna, se usa la página actual.', 'travel-packages' ); ?></small>
                        </div>

                        <div class="tp-field-grid tp-two-columns">
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_search_placeholder"><?php _e( 'Texto del campo', 'travel-packages' ); ?></label>
                                <input type="text" name="tp_search_placeholder" id="tp_search_placeholder" value="<?php echo esc_attr( $search_placeholder ); ?>" placeholder="<?php esc_attr_e( '¿A dónde querés viajar?', 'travel-packages' ); ?>">
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_search_button"><?php _e( 'Texto del botón', 'travel-packages' ); ?></label>
                                <input type="text" name="tp_search_button" id="tp_search_button" value="<?php echo esc_attr( $search_button ); ?>" placeholder="<?php esc_attr_e( 'Buscar', 'travel-packages' ); ?>">
                            </div>
                        </div>

                        <div class="tp-toggle-grid">
                            <label class="tp-toggle-row">
                                <span><?php _e( 'Mostrar selector de destino', 'travel-packages' ); ?></span>
                                <input type="checkbox" name="tp_search_show_destino" value="1" <?php checked( $search_destino ); ?>>
                            </label>
                            <label class="tp-toggle-row">
                                <span><?php _e( 'Mostrar filtro nacional / internacional', 'travel-packages' ); ?></span>
                                <input type="checkbox" name="tp_search_show_tipo" value="1" <?php checked( $search_tipo ); ?>>
                            </label>
                        </div>

                        <div class="tp-field-group">
                            <label class="tp-label"><?php _e( 'Shortcode', 'travel-packages' ); ?></label>
                            <div class="tp-code-block"><code>[tp_buscador]</code></div>
                            <small><?php _e( 'También podés sobrescribir los valores con atributos, por ejemplo: [tp_buscador placeholder="Tu próximo viaje" button="Buscar" show_destino="false"].', 'travel-packages' ); ?></small>
                        </div>
                    </div>

                    <div class="tp-panel-actions">
                        <button type="submit" class="tp-btn-primary"><?php _e( 'Guardar ajustes', 'travel-packages' ); ?></button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}
