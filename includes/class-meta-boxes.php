<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_MetaBoxes {

    public static function init() {
        add_action( 'add_meta_boxes',  [ __CLASS__, 'register' ] );
        add_action( 'save_post',       [ __CLASS__, 'save' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
    }

    public static function enqueue( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php', 'tp-dashboard_page_tp-packages' ] ) ) return;
        $screen = get_current_screen();
        if ( ! $screen || ( $screen->post_type !== 'paquete_viaje' && $hook !== 'tp-dashboard_page_tp-packages' ) ) return;

        // Media uploader
        wp_enqueue_media();

        // jQuery UI Datepicker
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_style( 'jquery-ui', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css' );

        wp_enqueue_style(  'tp-admin-meta', TP_PLUGIN_URL . 'admin/css/meta-boxes.css', [], filemtime( TP_PLUGIN_DIR . 'admin/css/meta-boxes.css' ) );
        wp_enqueue_script( 'tp-admin-meta', TP_PLUGIN_URL . 'admin/js/meta-boxes.js', [ 'jquery', 'jquery-ui-datepicker' ], filemtime( TP_PLUGIN_DIR . 'admin/js/meta-boxes.js' ), true );

        wp_localize_script( 'tp-admin-meta', 'TP_META', [
            'select_image'  => __( 'Seleccionar imagen', 'travel-packages' ),
            'use_image'     => __( 'Usar esta imagen', 'travel-packages' ),
            'select_images' => __( 'Seleccionar imágenes para la galería', 'travel-packages' ),
            'add_images'    => __( 'Agregar a galería', 'travel-packages' ),
            'confirm_field' => __( '¿Eliminar este campo?', 'travel-packages' ),
            'nonce'         => wp_create_nonce( 'tp_meta_nonce' ),
        ]);
    }

    public static function register() {
        add_meta_box( 'tp_main_info',    __( '✈ Información del Paquete', 'travel-packages' ),    [ __CLASS__, 'render_main' ],    'paquete_viaje', 'normal',   'high' );
        add_meta_box( 'tp_gallery',      __( '🖼 Galería de Imágenes',    'travel-packages' ),    [ __CLASS__, 'render_gallery' ], 'paquete_viaje', 'normal',   'default' );
        add_meta_box( 'tp_tech_sheet',   __( '📋 Ficha Técnica',          'travel-packages' ),    [ __CLASS__, 'render_tech' ],    'paquete_viaje', 'normal',   'default' );
        add_meta_box( 'tp_dates',        __( '📅 Fechas Disponibles',     'travel-packages' ),    [ __CLASS__, 'render_dates' ],   'paquete_viaje', 'normal',   'default' );
        add_meta_box( 'tp_custom_fields',__( '🔧 Campos Personalizados',  'travel-packages' ),    [ __CLASS__, 'render_custom_fields' ], 'paquete_viaje', 'normal', 'default' );
        add_meta_box( 'tp_visibility',   __( '⚙ Visibilidad y Destacados','travel-packages' ),    [ __CLASS__, 'render_visibility'], 'paquete_viaje', 'side', 'high' );
        add_meta_box( 'tp_price_box',    __( '💰 Precio',                 'travel-packages' ),    [ __CLASS__, 'render_price' ],   'paquete_viaje', 'side',     'high' );
    }

    /* ---- RENDER MAIN ---- */
    public static function render_main( $post ) {
        wp_nonce_field( 'tp_save_meta', 'tp_meta_nonce' );
        $short_desc = get_post_meta( $post->ID, '_tp_short_desc', true );
        $featured_id = get_post_thumbnail_id( $post->ID );
        $featured_url = $featured_id ? wp_get_attachment_image_url( $featured_id, 'medium' ) : '';
        ?>
        <div class="tp-field-group">
            <label class="tp-label"><?php _e( 'Descripción Corta', 'travel-packages' ); ?> <span class="tp-hint">(aparece en tarjetas y listados)</span></label>
            <textarea name="tp_short_desc" id="tp_short_desc" rows="3" maxlength="200" placeholder="<?php esc_attr_e('Resumen atractivo del paquete (máx. 200 caracteres)...', 'travel-packages'); ?>"><?php echo esc_textarea( $short_desc ); ?></textarea>
            <div class="tp-char-count"><span id="tp-short-count"><?php echo strlen($short_desc); ?></span>/200</div>
        </div>
        <div class="tp-field-group">
            <label class="tp-label"><?php _e('Imagen principal', 'travel-packages'); ?></label>
            <div class="tp-featured-wrap">
                <div id="tp-featured-preview">
                    <?php if ( $featured_url ) : ?>
                        <img src="<?php echo esc_url($featured_url); ?>" alt="" style="max-width:220px;height:auto;border-radius:8px;display:block;">
                    <?php endif; ?>
                </div>
                <input type="hidden" name="tp_featured_image" id="tp_featured_image" value="<?php echo esc_attr($featured_id); ?>">
                <p>
                    <button type="button" class="button" id="tp-set-featured"><?php _e('Seleccionar imagen principal', 'travel-packages'); ?></button>
                    <button type="button" class="button" id="tp-remove-featured"><?php _e('Eliminar imagen', 'travel-packages'); ?></button>
                </p>
            </div>
        </div>
        <div class="tp-field-group">
            <label class="tp-label"><?php _e('Descripción completa', 'travel-packages'); ?></label>
            <div class="tp-meta-box-editor">
                <?php wp_editor( $post->post_content, 'content', [ 'textarea_name' => 'content', 'media_buttons' => true, 'teeny' => false, 'editor_class' => 'tp-editor-main' ] ); ?>
            </div>
            <small><?php _e('Podés utilizar HTML, iframes de videos, código, etc.', 'travel-packages'); ?></small>
        </div>
        <?php
    }

    /* ---- RENDER GALLERY ---- */
    public static function render_gallery( $post ) {
        $gallery_ids = get_post_meta( $post->ID, '_tp_gallery', true );
        $ids_array   = $gallery_ids ? explode( ',', $gallery_ids ) : [];
        ?>
        <div class="tp-gallery-wrap">
            <div id="tp-gallery-preview" class="tp-gallery-grid">
                <?php foreach ( $ids_array as $img_id ) :
                    $img_id = (int) trim($img_id);
                    if ( ! $img_id ) continue;
                    $url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
                    if ( ! $url ) continue; ?>
                    <div class="tp-gallery-item" data-id="<?php echo $img_id; ?>">
                        <img src="<?php echo esc_url($url); ?>">
                        <button type="button" class="tp-gallery-remove" title="<?php esc_attr_e('Eliminar', 'travel-packages'); ?>">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="tp_gallery" id="tp_gallery_ids" value="<?php echo esc_attr($gallery_ids); ?>">
            <button type="button" id="tp-add-gallery" class="tp-btn tp-btn-secondary">
                <span class="dashicons dashicons-plus-alt"></span> <?php _e( 'Agregar imágenes a la galería', 'travel-packages' ); ?>
            </button>
            <p class="tp-hint-text"><?php _e('Arrastrá las imágenes para reordenarlas.', 'travel-packages'); ?></p>
        </div>
        <?php
    }

    /* ---- RENDER TECH SHEET ---- */
    public static function render_tech( $post ) {
        $fields = get_post_meta( $post->ID, '_tp_tech_fields', true );
        if ( ! is_array($fields) ) $fields = [];
        ?>
        <div class="tp-tech-wrap">
            <div id="tp-tech-fields">
                <?php foreach ( $fields as $i => $field ) : ?>
                <div class="tp-tech-field" data-index="<?php echo $i; ?>">
                    <div class="tp-tech-row">
                        <input type="text" name="tp_tech_key[]" placeholder="<?php esc_attr_e('Etiqueta (ej: Duración)', 'travel-packages'); ?>" value="<?php echo esc_attr($field['key']); ?>" class="tp-tech-key">
                        <input type="text" name="tp_tech_val[]" placeholder="<?php esc_attr_e('Valor (ej: 7 días / 6 noches)', 'travel-packages'); ?>" value="<?php echo esc_attr($field['val']); ?>" class="tp-tech-val">
                        <button type="button" class="tp-remove-field tp-btn-icon" title="<?php esc_attr_e('Eliminar campo', 'travel-packages'); ?>">✕</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="tp-add-tech-field" class="tp-btn tp-btn-secondary">
                <span class="dashicons dashicons-plus-alt"></span> <?php _e( 'Agregar campo a ficha técnica', 'travel-packages' ); ?>
            </button>
            <div class="tp-tech-suggestions">
                <strong><?php _e('Sugerencias rápidas:', 'travel-packages'); ?></strong>
                <?php
                $suggestions = [
                    __('Duración','travel-packages'), __('Salidas','travel-packages'),
                    __('Incluye','travel-packages'),  __('No incluye','travel-packages'),
                    __('Alojamiento','travel-packages'), __('Traslados','travel-packages'),
                    __('Vuelos','travel-packages'),   __('Régimen','travel-packages'),
                    __('Cupos','travel-packages'),    __('Dificultad','travel-packages'),
                ];
                foreach ( $suggestions as $s ) : ?>
                    <button type="button" class="tp-suggestion" data-label="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    /* ---- RENDER DATES ---- */
    public static function render_dates( $post ) {
        $dates = get_post_meta( $post->ID, '_tp_dates', true );
        if ( ! is_array($dates) ) $dates = [];
        ?>
        <div class="tp-dates-wrap">
            <div id="tp-dates-list">
                <?php foreach ( $dates as $i => $date ) :
                    $from = '';
                    $to   = '';
                    if ( is_array($date) ) {
                        $from = isset($date['from']) ? $date['from'] : '';
                        $to   = isset($date['to']) ? $date['to'] : '';
                    } else {
                        $from = $date;
                    }
                ?>
                <div class="tp-date-item">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <input type="text" name="tp_dates_from[]" class="tp-datepicker-from" value="<?php echo esc_attr($from); ?>" placeholder="Salida dd/mm/aaaa" readonly>
                    <input type="text" name="tp_dates_to[]" class="tp-datepicker-to" value="<?php echo esc_attr($to); ?>" placeholder="Regreso dd/mm/aaaa" readonly>
                    <button type="button" class="tp-remove-date tp-btn-icon">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="tp-add-date" class="tp-btn tp-btn-secondary">
                <span class="dashicons dashicons-plus-alt"></span> <?php _e( 'Agregar rango de fechas', 'travel-packages' ); ?>
            </button>
        </div>
        <?php
    }

    /* ---- RENDER CUSTOM FIELDS ---- */
    public static function render_custom_fields( $post ) {
        $defs = TP_CustomFields::get_definitions();
        if ( empty( $defs ) ) {
            echo '<p style="color:#999;">' . __( 'No hay campos personalizados definidos.', 'travel-packages' ) . '</p>';
            echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=tp-custom-fields' ) ) . '" class="button">' . __( 'Gestionar campos personalizados', 'travel-packages' ) . '</a></p>';
            return;
        }
        $values = TP_CustomFields::get_all_values( $post->ID );
        foreach ( $defs as $field ) {
            $value = $values[ $field['key'] ] ?? '';
            echo TP_CustomFields::render_field_input( $field, $value, 'tp_cf' );
        }
        echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=tp-custom-fields' ) ) . '" style="font-size:12px;color:#999;">' . __( 'Editar campos personalizados', 'travel-packages' ) . '</a></p>';
    }

    /* ---- RENDER PRICE ---- */
    public static function render_price( $post ) {
        $price      = get_post_meta( $post->ID, '_tp_price', true );
        $price_note = get_post_meta( $post->ID, '_tp_price_note', true );
        $currency   = get_post_meta( $post->ID, '_tp_currency', true ) ?: 'USD';
        ?>
        <div class="tp-price-wrap">
            <div class="tp-field-group">
                <label class="tp-label"><?php _e('Moneda', 'travel-packages'); ?></label>
                <select name="tp_currency" class="tp-select-small">
                    <option value="USD" <?php selected($currency,'USD'); ?>>USD – Dólar</option>
                    <option value="ARS" <?php selected($currency,'ARS'); ?>>ARS – Peso Arg.</option>
                    <option value="EUR" <?php selected($currency,'EUR'); ?>>EUR – Euro</option>
                    <option value="BRL" <?php selected($currency,'BRL'); ?>>BRL – Real</option>
                </select>
            </div>
            <div class="tp-field-group">
                <label class="tp-label"><?php _e('Precio por persona', 'travel-packages'); ?></label>
                <div class="tp-price-input-wrap">
                    <span class="tp-currency-symbol" id="tp-currency-symbol"><?php echo esc_html($currency); ?></span>
                    <input type="number" name="tp_price" id="tp_price" value="<?php echo esc_attr($price); ?>" placeholder="0.00" step="0.01" min="0">
                </div>
            </div>
            <div class="tp-field-group">
                <label class="tp-label"><?php _e('Nota de precio', 'travel-packages'); ?> <span class="tp-hint">(ej: "por persona en dbl")</span></label>
                <input type="text" name="tp_price_note" value="<?php echo esc_attr($price_note); ?>" placeholder="<?php esc_attr_e('por persona en habitación doble', 'travel-packages'); ?>">
            </div>
        </div>
        <?php
    }

    /* ---- RENDER VISIBILITY ---- */
    public static function render_visibility( $post ) {
        $slider   = get_post_meta( $post->ID, '_tp_slider',   true );
        $featured = get_post_meta( $post->ID, '_tp_featured', true );
        ?>
        <div class="tp-visibility-wrap">
            <div class="tp-toggle-item">
                <div class="tp-toggle-info">
                    <span class="dashicons dashicons-images-alt2"></span>
                    <div>
                        <strong><?php _e('Slider del Home', 'travel-packages'); ?></strong>
                        <small><?php _e('Mostrar en el slider principal', 'travel-packages'); ?></small>
                    </div>
                </div>
                <label class="tp-toggle-switch">
                    <input type="checkbox" name="tp_slider" value="1" <?php checked( $slider, '1' ); ?>>
                    <span class="tp-toggle-slider"></span>
                </label>
            </div>
            <div class="tp-toggle-item">
                <div class="tp-toggle-info">
                    <span class="dashicons dashicons-star-filled"></span>
                    <div>
                        <strong><?php _e('Paquete Destacado', 'travel-packages'); ?></strong>
                        <small><?php _e('Mostrar en sección destacados', 'travel-packages'); ?></small>
                    </div>
                </div>
                <label class="tp-toggle-switch">
                    <input type="checkbox" name="tp_featured" value="1" <?php checked( $featured, '1' ); ?>>
                    <span class="tp-toggle-slider"></span>
                </label>
            </div>
        </div>
        <?php
    }

    /* ---- SAVE ---- */
    public static function save( $post_id, $post ) {
        if ( ! isset($_POST['tp_meta_nonce']) ) return;
        if ( ! wp_verify_nonce($_POST['tp_meta_nonce'], 'tp_save_meta') ) return;
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( $post->post_type !== TP_Package_Data::POST_TYPE ) return;
        if ( ! current_user_can('edit_post', $post_id) ) return;

        TP_Package_Data::save_from_post( $post_id, $_POST );
    }
}
