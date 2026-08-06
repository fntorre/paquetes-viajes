<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Shortcodes {

    public static function init() {
        add_shortcode( 'tp_slider',   [ __CLASS__, 'render_slider' ] );
        add_shortcode( 'tp_grid',     [ __CLASS__, 'render_grid' ] );
        add_shortcode( 'tp_paquete',  [ __CLASS__, 'render_single' ] );
        add_shortcode( 'tp_catalogo', [ __CLASS__, 'render_catalogo' ] );

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function enqueue_assets() {
        wp_register_style(  'tp-public', TP_PLUGIN_URL . 'public/css/public.css', [], TP_VERSION );
        wp_register_script( 'tp-public', TP_PLUGIN_URL . 'public/js/public.js',  [ 'jquery' ], TP_VERSION, true );
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
                            <span class="tp-amount"><?php echo esc_html($best_price['currency'] . ' ' . number_format((float)$best_price['price'], 0, ',', '.')); ?></span>
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
                    $dates      = get_post_meta( get_the_ID(), '_tp_dates', true );
                    $thumb_url  = get_the_post_thumbnail_url( get_the_ID(), 'large' );
                    $terms      = get_the_terms( get_the_ID(), 'destino' );
                    $tipologia_terms = get_the_terms( get_the_ID(), 'tipologia' );
                    $next_date  = '';
                    if ( is_array($dates) && count($dates) ) {
                        $future = [];
                        foreach ( $dates as $d ) {
                            $from = '';
                            if ( is_array($d) ) {
                                $from = $d['from'] ?? '';
                            } else {
                                $from = $d;
                            }
                            if ( ! $from ) continue;
                            $ts = strtotime( str_replace('/', '-', $from) );
                            if ( $ts >= strtotime('today') ) {
                                $future[] = $from;
                            }
                        }
                        if ( $future ) $next_date = reset($future);
                    }
                ?>
                <div class="tp-grid-carousel-item">
                <article class="tp-card <?php echo $featured ? 'tp-card-featured' : ''; ?>">
                    <?php if ( $featured ) : ?>
                    <div class="tp-badge-featured">⭐ <?php _e('Destacado', 'travel-packages'); ?></div>
                    <?php endif; ?>
                    <a href="<?php the_permalink(); ?>" class="tp-card-image-link">
                        <?php if ( $thumb_url ) : ?>
                        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>" class="tp-card-img" loading="lazy">
                        <?php else : ?>
                        <div class="tp-card-img-placeholder"><span class="dashicons dashicons-airplane"></span></div>
                        <?php endif; ?>
                        <?php if ( $next_date ) : ?>
                        <div class="tp-card-next-date">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php echo esc_html($next_date); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ( $terms && ! is_wp_error($terms) ) : ?>
                        <div class="tp-card-terms">
                            <?php foreach ( $terms as $term ) : ?>
                            <a href="<?php echo esc_url(get_term_link($term)); ?>" class="tp-term"><?php echo esc_html($term->name); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ( $tipologia_terms && ! is_wp_error($tipologia_terms) ) : ?>
                        <div class="tp-card-terms tp-card-terms-tipologia">
                            <?php foreach ( $tipologia_terms as $term ) : ?>
                            <a href="<?php echo esc_url(get_term_link($term)); ?>" class="tp-term tp-term-tipologia"><?php echo esc_html($term->name); ?></a>
                            <?php endforeach; ?>
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
                            <span class="tp-price-amount"><?php echo esc_html($best_price['currency']); ?> <strong><?php echo number_format((float)$best_price['price'], 0, ',', '.'); ?></strong></span>
                            <?php if ( $best_price['note'] ) : ?><span class="tp-price-note"><?php echo esc_html($best_price['note']); ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <a href="<?php the_permalink(); ?>" class="tp-btn-card"><?php _e('Ver detalles', 'travel-packages'); ?></a>
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
                $dates      = get_post_meta( get_the_ID(), '_tp_dates', true );
                $thumb_url  = get_the_post_thumbnail_url( get_the_ID(), 'large' );
                $terms      = get_the_terms( get_the_ID(), 'destino' );
                $tipologia_terms = get_the_terms( get_the_ID(), 'tipologia' );
                $next_date  = '';
                if ( is_array($dates) && count($dates) ) {
                    $future = [];
                    foreach ( $dates as $d ) {
                        $from = '';
                        if ( is_array($d) ) {
                            $from = $d['from'] ?? '';
                        } else {
                            $from = $d;
                        }
                        if ( ! $from ) continue;
                        $ts = strtotime( str_replace('/', '-', $from) );
                        if ( $ts >= strtotime('today') ) {
                            $future[] = $from;
                        }
                    }
                    if ( $future ) $next_date = reset($future);
                }
            ?>
            <article class="tp-card <?php echo $featured ? 'tp-card-featured' : ''; ?>">
                <?php if ( $featured ) : ?>
                <div class="tp-badge-featured">⭐ <?php _e('Destacado', 'travel-packages'); ?></div>
                <?php endif; ?>
                <a href="<?php the_permalink(); ?>" class="tp-card-image-link">
                    <?php if ( $thumb_url ) : ?>
                    <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>" class="tp-card-img" loading="lazy">
                    <?php else : ?>
                    <div class="tp-card-img-placeholder"><span class="dashicons dashicons-airplane"></span></div>
                    <?php endif; ?>
                    <?php if ( $next_date ) : ?>
                    <div class="tp-card-next-date">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        <?php echo esc_html($next_date); ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( $terms && ! is_wp_error($terms) ) : ?>
                    <div class="tp-card-terms">
                        <?php foreach ( $terms as $term ) : ?>
                        <a href="<?php echo esc_url(get_term_link($term)); ?>" class="tp-term"><?php echo esc_html($term->name); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if ( $tipologia_terms && ! is_wp_error($tipologia_terms) ) : ?>
                    <div class="tp-card-terms tp-card-terms-tipologia">
                        <?php foreach ( $tipologia_terms as $term ) : ?>
                        <a href="<?php echo esc_url(get_term_link($term)); ?>" class="tp-term tp-term-tipologia"><?php echo esc_html($term->name); ?></a>
                        <?php endforeach; ?>
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
                        <span class="tp-price-amount"><?php echo esc_html($best_price['currency']); ?> <strong><?php echo number_format((float)$best_price['price'], 0, ',', '.'); ?></strong></span>
                        <?php if ( $best_price['note'] ) : ?><span class="tp-price-note"><?php echo esc_html($best_price['note']); ?></span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <a href="<?php the_permalink(); ?>" class="tp-btn-card"><?php _e('Ver detalles', 'travel-packages'); ?></a>
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
     * [tp_catalogo limit="-1" columns="3" orderby="date" order="DESC" show_search="true" show_filters="true"]
     * Landing page con buscador + filtros + grilla de todos los paquetes
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
        $url_destino   = isset( $_GET['destino'] ) ? sanitize_text_field( $_GET['destino'] ) : '';
        $url_tipologia = isset( $_GET['tipologia'] ) ? sanitize_text_field( $_GET['tipologia'] ) : '';
        $url_tipo_viaje = isset( $_GET['tipo_viaje'] ) ? sanitize_text_field( $_GET['tipo_viaje'] ) : '';
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

        $query = new WP_Query($args);

        $cols    = in_array( (int)$atts['columns'], [1,2,3,4] ) ? (int)$atts['columns'] : 3;
        $destinos = get_terms([ 'taxonomy' => 'destino', 'hide_empty' => true ]);
        $tipologias = get_terms([ 'taxonomy' => 'tipologia', 'hide_empty' => true ]);

        ob_start(); ?>
        <div class="tp-catalogo" data-columns="<?php echo $cols; ?>">

            <?php if ( $atts['show_search'] === 'true' || $atts['show_filters'] === 'true' ) : ?>
            <div class="tp-catalogo-toolbar">
                <?php if ( $atts['show_search'] === 'true' ) : ?>
                <div class="tp-catalogo-search">
                    <svg class="tp-search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="search" id="tp-catalogo-search" class="tp-catalogo-search-input" placeholder="<?php esc_attr_e('Buscar paquetes por nombre, destino…', 'travel-packages'); ?>">
                </div>
                <?php endif; ?>

                <?php if ( $atts['show_filters'] === 'true' ) : ?>
                <div class="tp-catalogo-filters">
                    <select id="tp-filter-destino" class="tp-catalogo-select">
                        <option value=""><?php _e('Todos los destinos', 'travel-packages'); ?></option>
                        <?php foreach ( $destinos as $destino ) : ?>
                        <option value="<?php echo esc_attr($destino->slug); ?>" <?php selected( $url_destino, $destino->slug ); ?>><?php echo esc_html($destino->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ( ! empty( $tipologias ) ) : ?>
                    <select id="tp-filter-tipologia" class="tp-catalogo-select">
                        <option value=""><?php _e('Todas las tipologías', 'travel-packages'); ?></option>
                        <?php foreach ( $tipologias as $tipologia ) : ?>
                        <option value="<?php echo esc_attr($tipologia->slug); ?>" <?php selected( $url_tipologia, $tipologia->slug ); ?>><?php echo esc_html($tipologia->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <div class="tp-filter-toggle" id="tp-filter-tipo-viaje">
                        <button type="button" class="tp-toggle-btn" data-value=""><?php _e('Todos', 'travel-packages'); ?></button>
                        <button type="button" class="tp-toggle-btn" data-value="nacional"><?php _e('Nacional', 'travel-packages'); ?></button>
                        <button type="button" class="tp-toggle-btn" data-value="internacional"><?php _e('Internacional', 'travel-packages'); ?></button>
                    </div>
                    <select id="tp-filter-orderby" class="tp-catalogo-select">
                        <option value="default"><?php _e('Ordenar por', 'travel-packages'); ?></option>
                        <option value="title"><?php _e('Nombre', 'travel-packages'); ?></option>
                        <option value="price"><?php _e('Precio', 'travel-packages'); ?></option>
                        <option value="date"><?php _e('Fecha', 'travel-packages'); ?></option>
                    </select>
                    <select id="tp-filter-order" class="tp-catalogo-select">
                        <option value="ASC"><?php _e('Ascendente', 'travel-packages'); ?></option>
                        <option value="DESC" selected><?php _e('Descendente', 'travel-packages'); ?></option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

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
                    $thumb_url  = get_the_post_thumbnail_url( get_the_ID(), 'large' );
                    $terms      = get_the_terms( get_the_ID(), 'destino' );
                    $tipologia_terms = get_the_terms( get_the_ID(), 'tipologia' );
                    $next_date  = '';

                    if ( is_array($dates) && count($dates) ) {
                        $future = [];
                        foreach ( $dates as $d ) {
                            $from = is_array($d) ? ($d['from'] ?? '') : $d;
                            if ( ! $from ) continue;
                            $ts = strtotime( str_replace('/', '-', $from) );
                            if ( $ts >= strtotime('today') ) $future[] = $from;
                        }
                        if ( $future ) $next_date = reset($future);
                    }

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
                    data-price="<?php echo esc_attr( $price ?: '0' ); ?>"
                    data-search="<?php echo esc_attr( $search_text ); ?>"
                    data-date="<?php echo esc_attr( get_the_date('U') ); ?>">

                    <?php if ( $featured ) : ?>
                    <div class="tp-badge-featured">⭐ <?php _e('Destacado', 'travel-packages'); ?></div>
                    <?php endif; ?>

                    <a href="<?php the_permalink(); ?>" class="tp-card-image-link">
                        <?php if ( $thumb_url ) : ?>
                        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php the_title_attribute(); ?>" class="tp-card-img" loading="lazy">
                        <?php else : ?>
                        <div class="tp-card-img-placeholder"><span class="dashicons dashicons-airplane"></span></div>
                        <?php endif; ?>
                        <?php if ( $next_date ) : ?>
                        <div class="tp-card-next-date">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php echo esc_html($next_date); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ( $terms && ! is_wp_error($terms) ) : ?>
                        <div class="tp-card-terms">
                            <?php foreach ( $terms as $term ) : ?>
                            <a href="<?php echo esc_url(get_term_link($term)); ?>" class="tp-term"><?php echo esc_html($term->name); ?></a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <?php if ( $tipologia_terms && ! is_wp_error($tipologia_terms) ) : ?>
                        <div class="tp-card-terms tp-card-terms-tipologia">
                            <?php foreach ( $tipologia_terms as $term ) : ?>
                            <a href="<?php echo esc_url(get_term_link($term)); ?>" class="tp-term tp-term-tipologia"><?php echo esc_html($term->name); ?></a>
                            <?php endforeach; ?>
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
                            <span class="tp-price-amount"><?php echo esc_html($best_price['currency']); ?> <strong><?php echo number_format((float)$best_price['price'], 0, ',', '.'); ?></strong></span>
                            <?php if ( $best_price['note'] ) : ?><span class="tp-price-note"><?php echo esc_html($best_price['note']); ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <a href="<?php the_permalink(); ?>" class="tp-btn-card"><?php _e('Ver detalles', 'travel-packages'); ?></a>
                    </div>
                </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>

            <div class="tp-catalogo-empty" style="display:none;">
                <span class="tp-catalogo-empty-icon">🔍</span>
                <p><?php _e('No se encontraron paquetes con los filtros seleccionados.', 'travel-packages'); ?></p>
            </div>
            <?php else : ?>
            <p class="tp-no-results"><?php _e('No hay paquetes disponibles en este momento.', 'travel-packages'); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
