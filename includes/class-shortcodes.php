<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Shortcodes {

    public static function init() {
        add_shortcode( 'tp_slider',   [ __CLASS__, 'render_slider' ] );
        add_shortcode( 'tp_grid',     [ __CLASS__, 'render_grid' ] );
        add_shortcode( 'tp_paquete',  [ __CLASS__, 'render_single' ] );
        add_shortcode( 'tp_catalogo', [ __CLASS__, 'render_catalogo' ] );
        add_shortcode( 'tp_buscador', [ __CLASS__, 'render_buscador' ] );

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function enqueue_assets() {
        wp_register_style(  'tp-public', TP_PLUGIN_URL . 'public/css/public.css', [], TP_VERSION );
        wp_register_script( 'tp-public', TP_PLUGIN_URL . 'public/js/public.js',  [ 'jquery' ], TP_VERSION, true );
        wp_localize_script( 'tp-public', 'TP_PUBLIC', [
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'tp_public_nonce' ),
            'min_chars'  => 2,
            'searching'  => __( 'Buscando paquetes…', 'travel-packages' ),
            'no_results' => __( 'No encontramos paquetes para tu búsqueda.', 'travel-packages' ),
        ] );
        if ( ! wp_style_is( 'dashicons', 'registered' ) ) {
            wp_register_style( 'dashicons' );
        }
    }

    /**
     * [tp_slider limit="5" autoplay="true" interval="5000" speed="300" pauseOnHover="true" showArrows="true" showDots="true"]
     * Muestra un slider con los paquetes marcados para slider
     * 
     * Props disponibles:
     * - limit: cantidad de paquetes (default: 5)
     * - autoplay: activar autoplay (default: true)
     * - interval: milisegundos entre slides (default: 5000)
     * - speed: velocidad de transición en ms (default: 300)
     * - pauseOnHover: pausar autoplay al pasar el mouse (default: true)
     * - showArrows: mostrar botones prev/next (default: true)
     * - showDots: mostrar puntos de navegación (default: true)
     */
    public static function render_slider( $atts ) {
        $atts = shortcode_atts([
            'limit'         => 5,
            'autoplay'      => 'true',
            'interval'      => 5000,
            'speed'         => 300,
            'pauseOnHover'  => 'true',
            'showArrows'    => 'true',
            'showDots'      => 'true',
        ], $atts, 'tp_slider' );

        wp_enqueue_style(  'tp-public' );
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_script( 'tp-public' );

        $query = new WP_Query([
            'post_type'      => 'paquete_viaje',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $atts['limit'],
            'meta_query'     => [[
                'key'   => '_tp_slider',
                'value' => '1',
            ]],
        ]);

        if ( ! $query->have_posts() ) {
            return '<p class="tp-no-results">' . __('No hay paquetes configurados para el slider.', 'travel-packages') . '</p>';
        }

        $autoplay       = $atts['autoplay'] === 'true' ? 'true' : 'false';
        $interval       = (int) $atts['interval'];
        $speed          = (int) $atts['speed'];
        $pauseOnHover   = $atts['pauseOnHover'] === 'true' ? 'true' : 'false';
        $showArrows     = $atts['showArrows'] === 'true' ? 'true' : 'false';
        $showDots       = $atts['showDots'] === 'true' ? 'true' : 'false';
        $unique_id      = 'tp-slider-' . uniqid();

        ob_start(); ?>
        <div class="tp-slider-wrapper" 
            id="<?php echo esc_attr($unique_id); ?>" 
            data-autoplay="<?php echo esc_attr($autoplay); ?>" 
            data-interval="<?php echo esc_attr($interval); ?>"
            data-speed="<?php echo esc_attr($speed); ?>"
            data-pauseonhover="<?php echo esc_attr($pauseOnHover); ?>"
            data-showarrows="<?php echo esc_attr($showArrows); ?>"
            data-showdots="<?php echo esc_attr($showDots); ?>">
            <div class="tp-slider-track">
                <?php while ( $query->have_posts() ) : $query->the_post();
                    $best_price = TP_Package_Data::get_best_price( get_the_ID() );
                    $short_desc = get_post_meta( get_the_ID(), '_tp_short_desc', true );
                    $thumb_url  = get_the_post_thumbnail_url( get_the_ID(), 'full' );
                    if ( ! $thumb_url ) $thumb_url = TP_PLUGIN_URL . 'public/img/placeholder.jpg';
                ?>
                <div class="tp-slide">
                    <div class="tp-slide-bg" style="background-image:url('<?php echo esc_url($thumb_url); ?>')"></div>
                    <div class="tp-slide-overlay"></div>
                    <div class="tp-slide-content">
                    
                        <h2 class="tp-slide-title"><?php the_title(); ?></h2>
                        <?php if ( $short_desc ) : ?>
                        <p class="tp-slide-desc"><?php echo esc_html($short_desc); ?></p>
                        <?php endif; ?>
                            <?php if ( $best_price ) : ?>
                        <div class="tp-slide-price">
                            <span class="tp-from"><?php _e('Desde', 'travel-packages'); ?></span>
                            <span class="tp-amount"><?php echo esc_html(TP_Package_Data::currency_symbol($best_price['currency']) . ' ' . number_format((float)$best_price['price'], 0, ',', '.')); ?></span>
                            <?php if ( $best_price['note'] ) : ?><span class="tp-note"><?php echo esc_html($best_price['note']); ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>" class="tp-btn-slide"><?php _e('Ver paquete', 'travel-packages'); ?> →</a>
                    </div>
                </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            <button class="tp-slider-prev" aria-label="Anterior">&#8249;</button>
            <button class="tp-slider-next" aria-label="Siguiente">&#8250;</button>
            <div class="tp-slider-dots"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * [tp_grid limit="6" destacados="true" columns="3" destino="europa"]
     * Muestra una grilla de paquetes
     *
     * Props carousel:
     * - desktop: cantidad de paquetes visibles por página en desktop (default: columns)
     * - movile:  cantidad de paquetes visibles por página en mobile (default: desktop)
     */
    public static function render_grid( $atts ) {
        $atts = shortcode_atts([
            'limit'       => 6,
            'destacados'  => 'false',
            'columns'     => 3,
            'carousel'    => 'false',
            'desktop'     => '',
            'movile'      => '',
            'mobile'      => '',
            'destino'     => '',
            'tipologia'   => '',
            'tipo_viaje'  => '',
            'orderby'     => 'date',
            'order'       => 'DESC',
            'ids'         => '',
        ], $atts, 'tp_grid' );

        wp_enqueue_style(  'tp-public' );
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_script( 'tp-public' );

        $args = [
            'post_type'      => 'paquete_viaje',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $atts['limit'],
            'orderby'        => sanitize_key($atts['orderby']),
            'order'          => $atts['order'] === 'ASC' ? 'ASC' : 'DESC',
        ];

        // Filter by featured
        if ( $atts['destacados'] === 'true' ) {
            $args['meta_query'] = [[
                'key'   => '_tp_featured',
                'value' => '1',
            ]];
        }

        // Filter by specific IDs
        if ( $atts['ids'] ) {
            $ids = array_map( 'intval', explode(',', $atts['ids']) );
            $args['post__in'] = $ids;
            $args['orderby']  = 'post__in';
        }

        // Filter by taxonomy
        $tax_query = [];

        if ( $atts['destino'] ) {
            $tax_query[] = [
                'taxonomy' => 'destino',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($atts['destino']),
            ];
        }

        if ( $atts['tipologia'] ) {
            $tax_query[] = [
                'taxonomy' => 'tipologia',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($atts['tipologia']),
            ];
        }

        if ( $tax_query ) {
            $args['tax_query'] = $tax_query;
        }

        if ( in_array( $atts['tipo_viaje'], [ 'nacional', 'internacional' ], true ) ) {
            $args['meta_query'] = array_merge( $args['meta_query'] ?? [], [[
                'key'   => TP_Package_Data::META_TIPO_VIAJE,
                'value' => $atts['tipo_viaje'],
            ]] );
        }

        $query = new WP_Query($args);

        if ( ! $query->have_posts() ) {
            return '<p class="tp-no-results">' . __('No se encontraron paquetes.', 'travel-packages') . '</p>';
        }

        $cols = in_array( (int)$atts['columns'], [1,2,3,4] ) ? (int)$atts['columns'] : 3;
        $is_carousel = $atts['carousel'] === 'true';

        $desktop = (int) $atts['desktop'];
        if ( $desktop < 1 ) $desktop = $cols;

        $movile = (int) $atts['movile'];
        if ( $movile < 1 ) $movile = (int) $atts['mobile'];
        if ( $movile < 1 ) $movile = $desktop;

        ob_start();

        if ( $is_carousel ) :

            $unique_id = 'tp-grid-carousel-' . uniqid();
            $desktop   = max( 1, $desktop );
            $movile    = max( 1, $movile );
        ?>
        <div class="tp-grid-carousel" id="<?php echo esc_attr($unique_id); ?>" data-desktop="<?php echo $desktop; ?>" data-movile="<?php echo $movile; ?>">
            <div class="tp-grid-carousel-track">
                <?php
                while ( $query->have_posts() ) : $query->the_post();
                    $best_price = TP_Package_Data::get_best_price( get_the_ID() );
                    $short_desc = get_post_meta( get_the_ID(), '_tp_short_desc', true );
                    $featured   = get_post_meta( get_the_ID(), '_tp_featured', true );
                    $next_departure = TP_Package_Data::get_next_departure( get_the_ID() );
                    $next_date      = $next_departure ? $next_departure['from'] : '';
                    $next_seats     = ( $next_departure && $next_departure['seats_left'] !== null ) ? (int) $next_departure['seats_left'] : null;
                ?>
                <div class="tp-grid-carousel-item">
                <article class="tp-card <?php echo $featured ? 'tp-card-featured' : ''; ?>">
                    <?php if ( $featured ) : ?>
                    <div class="tp-badge-featured">⭐ <?php _e('Destacado', 'travel-packages'); ?></div>
                    <?php endif; ?>
                    <a href="<?php the_permalink(); ?>" class="tp-card-image-link">
                        <?php echo self::render_card_image( get_the_ID() ); ?>
                        <?php if ( $next_date ) : ?>
                        <div class="tp-card-next-date">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php echo esc_html($next_date); ?>
                            <?php if ( $next_seats !== null && $next_seats > 0 ) : ?>
                            <span class="tp-card-seats <?php echo $next_seats < 5 ? 'is-low' : ''; ?>">
                                <?php
                                if ( $next_seats < 5 ) {
                                    printf( esc_html__('¡Últimos lugares! · %d disponibles','travel-packages'), $next_seats );
                                } else {
                                    printf( esc_html__('%d cupos disponibles','travel-packages'), $next_seats );
                                }
                                ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </a>
                    <div class="tp-card-body">
                        <h3 class="tp-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                        <?php if ( $short_desc ) : ?>
                        <p class="tp-card-desc"><?php echo esc_html($short_desc); ?></p>
                        <?php endif; ?>
                        <?php if ( $best_price ) : ?>
                        <div class="tp-card-price">
                            <span class="tp-from-small"><?php _e('Desde', 'travel-packages'); ?></span>
                            <span class="tp-price-amount"><?php echo esc_html(TP_Package_Data::currency_symbol($best_price['currency'])); ?> <strong><?php echo number_format((float)$best_price['price'], 0, ',', '.'); ?></strong></span>
                            <?php if ( $best_price['note'] ) : ?><span class="tp-price-note"><?php echo esc_html($best_price['note']); ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php self::render_card_actions( get_the_ID(), $next_departure ); ?>
                    </div>
                </article>
                </div>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
            <button class="tp-carousel-prev" aria-label="Anterior">‹</button>
            <button class="tp-carousel-next" aria-label="Siguiente">›</button>
            <div class="tp-carousel-dots"></div>
        </div>
        <?php

        else : // standard grid ?>
        <div class="tp-grid tp-cols-<?php echo $cols; ?>">
            <?php while ( $query->have_posts() ) : $query->the_post();
                $best_price = TP_Package_Data::get_best_price( get_the_ID() );
                $short_desc = get_post_meta( get_the_ID(), '_tp_short_desc', true );
                $featured   = get_post_meta( get_the_ID(), '_tp_featured', true );
                $next_departure = TP_Package_Data::get_next_departure( get_the_ID() );
                $next_date      = $next_departure ? $next_departure['from'] : '';
                $next_seats     = ( $next_departure && $next_departure['seats_left'] !== null ) ? (int) $next_departure['seats_left'] : null;
            ?>
            <article class="tp-card <?php echo $featured ? 'tp-card-featured' : ''; ?>">
                <?php if ( $featured ) : ?>
                <div class="tp-badge-featured">⭐ <?php _e('Destacado', 'travel-packages'); ?></div>
                <?php endif; ?>
                <a href="<?php the_permalink(); ?>" class="tp-card-image-link">
                    <?php echo self::render_card_image( get_the_ID() ); ?>
                    <?php if ( $next_date ) : ?>
                    <div class="tp-card-next-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo esc_html($next_date); ?>
                        <?php if ( $next_seats !== null && $next_seats > 0 ) : ?>
                        <span class="tp-card-seats <?php echo $next_seats < 5 ? 'is-low' : ''; ?>">
                            <?php
                            if ( $next_seats < 5 ) {
                                printf( esc_html__('¡Últimos lugares! · %d disponibles','travel-packages'), $next_seats );
                            } else {
                                printf( esc_html__('%d cupos disponibles','travel-packages'), $next_seats );
                            }
                            ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </a>
                <div class="tp-card-body">
                    <h3 class="tp-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    <?php if ( $short_desc ) : ?>
                    <p class="tp-card-desc"><?php echo esc_html($short_desc); ?></p>
                    <?php endif; ?>
                    <?php if ( $best_price ) : ?>
                    <div class="tp-card-price">
                        <span class="tp-from-small"><?php _e('Desde', 'travel-packages'); ?></span>
                        <span class="tp-price-amount"><?php echo esc_html(TP_Package_Data::currency_symbol($best_price['currency'])); ?> <strong><?php echo number_format((float)$best_price['price'], 0, ',', '.'); ?></strong></span>
                        <?php if ( $best_price['note'] ) : ?><span class="tp-price-note"><?php echo esc_html($best_price['note']); ?></span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php self::render_card_actions( get_the_ID(), $next_departure ); ?>
                </div>
            </article>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php endif;

        return ob_get_clean();
    }

    /**
     * [tp_paquete id="123"]
     * Muestra un paquete individual embebido
     */
    public static function render_single( $atts ) {
        $atts = shortcode_atts([ 'id' => 0 ], $atts, 'tp_paquete' );
        if ( ! $atts['id'] ) return '';

        wp_enqueue_style(  'tp-public' );
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_script( 'tp-public' );

        $post = get_post( (int) $atts['id'] );
        if ( ! $post || $post->post_type !== 'paquete_viaje' ) return '';

        $prices       = TP_Package_Data::get_prices( $post->ID );
        $short_desc   = get_post_meta( $post->ID, '_tp_short_desc', true );
        $tech_fields  = get_post_meta( $post->ID, '_tp_tech_fields', true );
        $dates        = get_post_meta( $post->ID, '_tp_dates', true );
        $gallery_ids  = get_post_meta( $post->ID, '_tp_gallery', true );
        $thumb_url    = get_the_post_thumbnail_url( $post->ID, 'full' );

        $custom_field_defs = TP_CustomFields::get_definitions();
        $custom_field_values = TP_CustomFields::get_all_values( $post->ID );

        ob_start();
        include TP_PLUGIN_DIR . 'templates/single-paquete.php';
        return ob_get_clean();
    }

    /**
     * [tp_buscador url="" placeholder="" button="" show_destino="true" show_tipo_viaje="true"]
     * Buscador de paquetes para colocar en cualquier parte del template.
     * Envía la búsqueda a la página de resultados configurada en Ajustes
     * (o a la página actual si no se configuró ninguna).
     */
    public static function render_buscador( $atts ) {
        $atts = shortcode_atts([
            'url'             => '',
            'placeholder'     => '',
            'button'          => '',
            'show_destino'    => '',
            'show_tipo_viaje' => '',
            'destino'         => '',
            'tipo_viaje'      => '',
            'class'           => '',
        ], $atts, 'tp_buscador' );

        wp_enqueue_style(  'tp-public' );
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_script( 'tp-public' );

        $action = trim( (string) $atts['url'] );
        if ( $action === '' && class_exists( 'TP_Settings' ) ) {
            $action = TP_Settings::get_search_url();
        }

        $action_base   = $action;
        $action_hidden = [];
        if ( $action !== '' ) {
            $parsed = wp_parse_url( $action );
            if ( ! empty( $parsed['query'] ) ) {
                parse_str( $parsed['query'], $action_hidden );
            }
            if ( ! empty( $parsed['scheme'] ) && ! empty( $parsed['host'] ) ) {
                $action_base = $parsed['scheme'] . '://' . $parsed['host'];
                if ( ! empty( $parsed['path'] ) ) {
                    $action_base .= $parsed['path'];
                }
            } else {
                $action_base = strtok( $action, '?' );
            }
            foreach ( [ 'tp_search', 'destino', 'tipo_viaje' ] as $reserved ) {
                unset( $action_hidden[ $reserved ] );
            }
        }

        $placeholder = $atts['placeholder'] !== ''
            ? $atts['placeholder']
            : ( class_exists( 'TP_Settings' ) ? TP_Settings::get_search_placeholder() : __( '¿A dónde querés viajar?', 'travel-packages' ) );

        $button = $atts['button'] !== ''
            ? $atts['button']
            : ( class_exists( 'TP_Settings' ) ? TP_Settings::get_search_button() : __( 'Buscar', 'travel-packages' ) );

        $show_destino = $atts['show_destino'] !== ''
            ? in_array( strtolower( $atts['show_destino'] ), [ '1', 'true', 'yes', 'si', 'sí' ], true )
            : ( ! class_exists( 'TP_Settings' ) || TP_Settings::is_search_destino_enabled() );

        $show_tipo_viaje = $atts['show_tipo_viaje'] !== ''
            ? in_array( strtolower( $atts['show_tipo_viaje'] ), [ '1', 'true', 'yes', 'si', 'sí' ], true )
            : ( ! class_exists( 'TP_Settings' ) || TP_Settings::is_search_tipo_enabled() );

        $search_val = isset( $_GET['tp_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tp_search'] ) ) : '';

        $destino_val = $atts['destino'] !== ''
            ? sanitize_title( $atts['destino'] )
            : ( isset( $_GET['destino'] ) ? sanitize_title( wp_unslash( $_GET['destino'] ) ) : '' );

        $tipo_viaje_val = $atts['tipo_viaje'] !== ''
            ? sanitize_key( $atts['tipo_viaje'] )
            : ( isset( $_GET['tipo_viaje'] ) ? sanitize_key( wp_unslash( $_GET['tipo_viaje'] ) ) : '' );

        if ( ! in_array( $tipo_viaje_val, [ 'nacional', 'internacional' ], true ) ) {
            $tipo_viaje_val = '';
        }

        $destinos = [];
        if ( $show_destino ) {
            $terms = get_terms([ 'taxonomy' => 'destino', 'hide_empty' => true ]);
            if ( ! is_wp_error( $terms ) ) {
                $destinos = $terms;
            }
        }

        $classes = 'tp-buscador';
        $extra_class = preg_replace( '/[^A-Za-z0-9_\-\s]/', '', (string) $atts['class'] );
        if ( trim( $extra_class ) !== '' ) {
            $classes .= ' ' . trim( $extra_class );
        }

        $suggestions_id = 'tp-buscador-suggestions-' . uniqid();

        ob_start(); ?>
        <form class="<?php echo esc_attr( $classes ); ?>" method="get" role="search"<?php echo $action_base !== '' ? ' action="' . esc_url( $action_base ) . '"' : ''; ?>>
            <?php foreach ( $action_hidden as $hidden_key => $hidden_value ) : ?>
            <?php if ( is_array( $hidden_value ) ) continue; ?>
            <input type="hidden" name="<?php echo esc_attr( $hidden_key ); ?>" value="<?php echo esc_attr( $hidden_value ); ?>">
            <?php endforeach; ?>
            <div class="tp-buscador-field">
                <svg class="tp-buscador-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="search" name="tp_search" class="tp-buscador-input" value="<?php echo esc_attr( $search_val ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" aria-label="<?php echo esc_attr( $placeholder ); ?>" autocomplete="off" aria-autocomplete="list" aria-controls="<?php echo esc_attr( $suggestions_id ); ?>" aria-expanded="false" data-tp-search-input>
                <div class="tp-buscador-suggestions" id="<?php echo esc_attr( $suggestions_id ); ?>" role="listbox" aria-live="polite" aria-label="<?php esc_attr_e( 'Sugerencias de paquetes', 'travel-packages' ); ?>" hidden></div>
            </div>

            <?php if ( $show_destino && ! empty( $destinos ) ) : ?>
            <label class="tp-buscador-label">
                <span class="screen-reader-text"><?php _e( 'Destino', 'travel-packages' ); ?></span>
                <select name="destino" class="tp-buscador-select">
                    <option value=""><?php _e( 'Todos los destinos', 'travel-packages' ); ?></option>
                    <?php foreach ( $destinos as $destino ) : ?>
                    <option value="<?php echo esc_attr( $destino->slug ); ?>" <?php selected( $destino_val, $destino->slug ); ?>><?php echo esc_html( $destino->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>

            <?php if ( $show_tipo_viaje ) : ?>
            <label class="tp-buscador-label">
                <span class="screen-reader-text"><?php _e( 'Tipo de viaje', 'travel-packages' ); ?></span>
                <select name="tipo_viaje" class="tp-buscador-select">
                    <option value=""><?php _e( 'Nacional e internacional', 'travel-packages' ); ?></option>
                    <option value="nacional" <?php selected( $tipo_viaje_val, 'nacional' ); ?>><?php _e( 'Nacional', 'travel-packages' ); ?></option>
                    <option value="internacional" <?php selected( $tipo_viaje_val, 'internacional' ); ?>><?php _e( 'Internacional', 'travel-packages' ); ?></option>
                </select>
            </label>
            <?php endif; ?>

            <button type="submit" class="tp-buscador-submit"><?php echo esc_html( $button ); ?></button>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * [tp_catalogo limit="-1" columns="3" orderby="date" order="DESC" show_search="true" show_filters="true"]
     * Catálogo con el buscador AJAX y la grilla de todos los paquetes.
     */
    public static function render_catalogo( $atts ) {
        $atts = shortcode_atts([
            'limit'        => -1,
            'columns'      => 3,
            'orderby'      => 'date',
            'order'        => 'DESC',
            'show_search'  => 'true',
            'show_filters' => 'true',
        ], $atts, 'tp_catalogo' );

        wp_enqueue_style(  'tp-public' );
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_script( 'tp-public' );

        $args = [
            'post_type'      => 'paquete_viaje',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $atts['limit'],
            'orderby'        => sanitize_key($atts['orderby']),
            'order'          => $atts['order'] === 'ASC' ? 'ASC' : 'DESC',
        ];

        // Support URL params for pre-filtered catalog landing pages
        $url_search    = isset( $_GET['tp_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tp_search'] ) ) : '';
        $url_destino   = isset( $_GET['destino'] ) ? sanitize_text_field( wp_unslash( $_GET['destino'] ) ) : '';
        $url_tipologia = isset( $_GET['tipologia'] ) ? sanitize_text_field( wp_unslash( $_GET['tipologia'] ) ) : '';
        $url_tipo_viaje = isset( $_GET['tipo_viaje'] ) ? sanitize_text_field( wp_unslash( $_GET['tipo_viaje'] ) ) : '';
        $tax_query = [];
        if ( $url_destino ) {
            $tax_query[] = [ 'taxonomy' => 'destino', 'field' => 'slug', 'terms' => $url_destino ];
        }
        if ( $url_tipologia ) {
            $tax_query[] = [ 'taxonomy' => 'tipologia', 'field' => 'slug', 'terms' => $url_tipologia ];
        }
        if ( $tax_query ) {
            $args['tax_query'] = $tax_query;
        }
        if ( in_array( $url_tipo_viaje, [ 'nacional', 'internacional' ], true ) ) {
            $args['meta_query'] = [[
                'key'   => TP_Package_Data::META_TIPO_VIAJE,
                'value' => $url_tipo_viaje,
            ]];
        }

        // Búsqueda precisa: restringe y ordena por relevancia.
        if ( $url_search !== '' ) {
            $search_ids        = TP_Package_Data::search_package_ids( $url_search, -1 );
            $args['post__in']  = ! empty( $search_ids ) ? $search_ids : [ 0 ];
            $args['orderby']   = 'post__in';
            $args['order']     = 'ASC';
        }

        $query = new WP_Query($args);

        $cols = in_array( (int)$atts['columns'], [1,2,3,4] ) ? (int)$atts['columns'] : 3;
        $catalog_url = get_permalink( get_queried_object_id() );
        if ( ! $catalog_url ) {
            $catalog_url = home_url( '/' );
        }

        ob_start(); ?>
        <div class="tp-catalogo" data-columns="<?php echo $cols; ?>">

            <?php
            if ( $atts['show_search'] === 'true' ) {
                $search_atts = [
                    'url'   => $catalog_url,
                    'class' => 'tp-catalogo-buscador',
                ];
                if ( $atts['show_filters'] !== 'true' ) {
                    $search_atts['show_destino']    = 'false';
                    $search_atts['show_tipo_viaje'] = 'false';
                }
                echo self::render_buscador( $search_atts );
            }
            ?>

            <div class="tp-catalogo-count">
                <span id="tp-catalogo-total"><?php echo $query->found_posts; ?></span>
                <?php _e('paquetes encontrados', 'travel-packages'); ?>
            </div>

            <?php if ( $query->have_posts() ) : ?>
            <div class="tp-grid tp-cols-<?php echo $cols; ?>" id="tp-catalogo-grid">
                <?php while ( $query->have_posts() ) : $query->the_post();
                    $best_price = TP_Package_Data::get_best_price( get_the_ID() );
                    $short_desc = get_post_meta( get_the_ID(), '_tp_short_desc', true );
                    $featured   = get_post_meta( get_the_ID(), '_tp_featured', true );
                    $dates      = get_post_meta( get_the_ID(), '_tp_dates', true );
                    $terms      = get_the_terms( get_the_ID(), 'destino' );
                    $tipologia_terms = get_the_terms( get_the_ID(), 'tipologia' );
                    $next_departure = TP_Package_Data::get_next_departure( get_the_ID() );
                    $next_date      = $next_departure ? $next_departure['from'] : '';
                    $next_seats     = ( $next_departure && $next_departure['seats_left'] !== null ) ? (int) $next_departure['seats_left'] : null;

                    $destino_slugs = [];
                    $destino_names = [];
                    if ( $terms && ! is_wp_error($terms) ) {
                        foreach ( $terms as $t ) {
                            $destino_slugs[] = $t->slug;
                            $destino_names[] = $t->name;
                        }
                    }

                    $tipologia_slugs = [];
                    $tipologia_names = [];
                    if ( $tipologia_terms && ! is_wp_error($tipologia_terms) ) {
                        foreach ( $tipologia_terms as $t ) {
                            $tipologia_slugs[] = $t->slug;
                            $tipologia_names[] = $t->name;
                        }
                    }

                    $search_text = strtolower( get_the_title() . ' ' . implode(' ', $destino_names) . ' ' . implode(' ', $tipologia_names) . ' ' . ($short_desc ?? '') );
                    $tipo_viaje_val = get_post_meta( get_the_ID(), TP_Package_Data::META_TIPO_VIAJE, true );
                ?>
                <article class="tp-card <?php echo $featured ? 'tp-card-featured' : ''; ?>"
                    data-title="<?php echo esc_attr( strtolower(get_the_title()) ); ?>"
                    data-destinos="<?php echo esc_attr( implode(' ', $destino_slugs) ); ?>"
                    data-tipologia="<?php echo esc_attr( implode(' ', $tipologia_slugs) ); ?>"
                    data-tipo-viaje="<?php echo esc_attr( $tipo_viaje_val ); ?>"
                    data-price="<?php echo esc_attr( $best_price ? $best_price['price'] : '0' ); ?>"
                    data-search="<?php echo esc_attr( $search_text ); ?>"
                    data-date="<?php echo esc_attr( get_the_date('U') ); ?>">

                    <?php if ( $featured ) : ?>
                    <div class="tp-badge-featured">⭐ <?php _e('Destacado', 'travel-packages'); ?></div>
                    <?php endif; ?>

                    <a href="<?php the_permalink(); ?>" class="tp-card-image-link">
                        <?php echo self::render_card_image( get_the_ID() ); ?>
                        <?php if ( $next_date ) : ?>
                        <div class="tp-card-next-date">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php echo esc_html($next_date); ?>
                            <?php if ( $next_seats !== null && $next_seats > 0 ) : ?>
                            <span class="tp-card-seats <?php echo $next_seats < 5 ? 'is-low' : ''; ?>">
                                <?php
                                if ( $next_seats < 5 ) {
                                    printf( esc_html__('¡Últimos lugares! · %d disponibles','travel-packages'), $next_seats );
                                } else {
                                    printf( esc_html__('%d cupos disponibles','travel-packages'), $next_seats );
                                }
                                ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </a>

                    <div class="tp-card-body">
                        <h3 class="tp-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

                        <?php if ( $short_desc ) : ?>
                        <p class="tp-card-desc"><?php echo esc_html($short_desc); ?></p>
                        <?php endif; ?>

                        <?php if ( $best_price ) : ?>
                        <div class="tp-card-price">
                            <span class="tp-from-small"><?php _e('Desde', 'travel-packages'); ?></span>
                            <span class="tp-price-amount"><?php echo esc_html(TP_Package_Data::currency_symbol($best_price['currency'])); ?> <strong><?php echo number_format((float)$best_price['price'], 0, ',', '.'); ?></strong></span>
                            <?php if ( $best_price['note'] ) : ?><span class="tp-price-note"><?php echo esc_html($best_price['note']); ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php self::render_card_actions( get_the_ID(), $next_departure ); ?>
                    </div>
                </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>

            <div class="tp-catalogo-empty" style="display:none;">
                <span class="tp-catalogo-empty-icon">🔍</span>
                <p><?php _e('No se encontraron paquetes con los filtros seleccionados.', 'travel-packages'); ?></p>
            </div>
            <?php else : ?>
            <p class="tp-no-results"><?php echo $url_search !== '' ? esc_html__( 'No se encontraron paquetes para tu búsqueda.', 'travel-packages' ) : esc_html__( 'No hay paquetes disponibles en este momento.', 'travel-packages' ); ?></p>
            <?php endif; ?>

            <?php
            if ( class_exists( 'TP_Templates' ) && ! empty( $query->posts ) ) {
                $catalog_name = $url_search !== ''
                    ? sprintf( __( 'Resultados para “%s”', 'travel-packages' ), $url_search )
                    : get_the_title( get_queried_object_id() );
                TP_Templates::print_item_list_schema( $query->posts, $catalog_name );
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Imagen de tarjeta con srcset, dimensiones y carga diferida. */
    private static function render_card_image( $post_id, $sizes = '(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw' ) {
        $thumb_id = (int) get_post_thumbnail_id( $post_id );

        if ( ! $thumb_id ) {
            return '<div class="tp-card-img-placeholder"><span class="dashicons dashicons-airplane"></span></div>';
        }

        return wp_get_attachment_image( $thumb_id, 'large', false, [
            'class'    => 'tp-card-img',
            'alt'      => wp_strip_all_tags( get_the_title( $post_id ) ),
            'loading'  => 'lazy',
            'decoding' => 'async',
            'sizes'    => $sizes,
        ] );
    }

    /** Acciones de la tarjeta: ver detalles y consultar por WhatsApp. */
    private static function render_card_actions( $post_id, $next_departure = null ) {
        $permalink = get_permalink( $post_id );
        $title     = get_the_title( $post_id );
        $whatsapp  = '';

        if ( class_exists( 'TP_Settings' ) ) {
            $parts = [ sprintf( __( 'Hola, quiero consultar por el paquete "%s".', 'travel-packages' ), $title ) ];
            if ( $next_departure && ! empty( $next_departure['from'] ) ) {
                $parts[] = sprintf( __( 'Salida: %s', 'travel-packages' ), $next_departure['from'] );
            }
            $parts[]  = $permalink;
            $whatsapp = TP_Package_Data::whatsapp_url( $post_id, implode( "\n", $parts ) );
        }

        echo '<div class="tp-card-actions">';
        echo '<a href="' . esc_url( $permalink ) . '" class="tp-btn-card">' . esc_html__( 'Ver detalles', 'travel-packages' ) . '</a>';
        if ( $whatsapp !== '' ) {
            echo '<a href="' . esc_url( $whatsapp ) . '" class="tp-btn-card-wa" target="_blank" rel="noopener"><span class="dashicons dashicons-whatsapp"></span>' . esc_html__( 'Consultar', 'travel-packages' ) . '</a>';
        }
        echo '</div>';
    }
}
