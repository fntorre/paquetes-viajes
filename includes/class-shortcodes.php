<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Shortcodes {

    public static function init() {
        add_shortcode( 'tp_slider',   [ __CLASS__, 'render_slider' ] );
        add_shortcode( 'tp_grid',     [ __CLASS__, 'render_grid' ] );
        add_shortcode( 'tp_paquete',  [ __CLASS__, 'render_single' ] );

        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
    }

    public static function enqueue_assets() {
        wp_register_style(  'tp-public', TP_PLUGIN_URL . 'public/css/public.css', [], TP_VERSION );
        wp_register_script( 'tp-public', TP_PLUGIN_URL . 'public/js/public.js',  [ 'jquery' ], TP_VERSION, true );
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
            data-autoplay="<?php echo $autoplay; ?>" 
            data-interval="<?php echo $interval; ?>"
            data-speed="<?php echo $speed; ?>"
            data-pauseOnHover="<?php echo $pauseOnHover; ?>"
            data-showArrows="<?php echo $showArrows; ?>"
            data-showDots="<?php echo $showDots; ?>">
            <div class="tp-slider-track">
                <?php while ( $query->have_posts() ) : $query->the_post();
                    $price      = get_post_meta( get_the_ID(), '_tp_price', true );
                    $currency   = get_post_meta( get_the_ID(), '_tp_currency', true ) ?: 'USD';
                    $price_note = get_post_meta( get_the_ID(), '_tp_price_note', true );
                    $short_desc = get_post_meta( get_the_ID(), '_tp_short_desc', true );
                    $thumb_url  = get_the_post_thumbnail_url( get_the_ID(), 'full' );
                    if ( ! $thumb_url ) $thumb_url = TP_PLUGIN_URL . 'public/img/placeholder.jpg';
                ?>
                <div class="tp-slide">
                    <div class="tp-slide-bg" style="background-image:url('<?php echo esc_url($thumb_url); ?>')"></div>
                    <div class="tp-slide-overlay"></div>
                    <div class="tp-slide-content">
                        <?php if ( $price ) : ?>
                        <div class="tp-slide-price">
                            <span class="tp-from"><?php _e('Desde', 'travel-packages'); ?></span>
                            <span class="tp-amount"><?php echo esc_html($currency . ' ' . number_format((float)$price, 0, ',', '.')); ?></span>
                            <?php if ( $price_note ) : ?><span class="tp-note"><?php echo esc_html($price_note); ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <h2 class="tp-slide-title"><?php the_title(); ?></h2>
                        <?php if ( $short_desc ) : ?>
                        <p class="tp-slide-desc"><?php echo esc_html($short_desc); ?></p>
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
     */
    public static function render_grid( $atts ) {
        $atts = shortcode_atts([
            'limit'       => 6,
            'destacados'  => 'false',
            'columns'     => 3,
            'destino'     => '',
            'orderby'     => 'date',
            'order'       => 'DESC',
            'ids'         => '',
        ], $atts, 'tp_grid' );

        wp_enqueue_style(  'tp-public' );
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
        if ( $atts['destino'] ) {
            $args['tax_query'] = [[
                'taxonomy' => 'destino',
                'field'    => 'slug',
                'terms'    => sanitize_text_field($atts['destino']),
            ]];
        }

        $query = new WP_Query($args);

        if ( ! $query->have_posts() ) {
            return '<p class="tp-no-results">' . __('No se encontraron paquetes.', 'travel-packages') . '</p>';
        }

        $cols = in_array( (int)$atts['columns'], [1,2,3,4] ) ? (int)$atts['columns'] : 3;

        ob_start(); ?>
        <div class="tp-grid tp-cols-<?php echo $cols; ?>">
            <?php while ( $query->have_posts() ) : $query->the_post();
                $price      = get_post_meta( get_the_ID(), '_tp_price', true );
                $currency   = get_post_meta( get_the_ID(), '_tp_currency', true ) ?: 'USD';
                $price_note = get_post_meta( get_the_ID(), '_tp_price_note', true );
                $short_desc = get_post_meta( get_the_ID(), '_tp_short_desc', true );
                $featured   = get_post_meta( get_the_ID(), '_tp_featured', true );
                $dates      = get_post_meta( get_the_ID(), '_tp_dates', true );
                $thumb_url  = get_the_post_thumbnail_url( get_the_ID(), 'large' );
                $terms      = get_the_terms( get_the_ID(), 'destino' );
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
                </a>
                <div class="tp-card-body">
                    <?php if ( $terms && ! is_wp_error($terms) ) : ?>
                    <div class="tp-card-terms">
                        <?php foreach ( $terms as $term ) : ?>
                        <a href="<?php echo esc_url(get_term_link($term)); ?>" class="tp-term"><?php echo esc_html($term->name); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <h3 class="tp-card-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                    <?php if ( $short_desc ) : ?>
                    <p class="tp-card-desc"><?php echo esc_html($short_desc); ?></p>
                    <?php endif; ?>
                    <?php if ( $price ) : ?>
                    <div class="tp-card-price">
                        <span class="tp-from-small"><?php _e('Desde', 'travel-packages'); ?></span>
                        <span class="tp-price-amount"><?php echo esc_html($currency); ?> <strong><?php echo number_format((float)$price, 0, ',', '.'); ?></strong></span>
                        <?php if ( $price_note ) : ?><span class="tp-price-note"><?php echo esc_html($price_note); ?></span><?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <a href="<?php the_permalink(); ?>" class="tp-btn-card"><?php _e('Ver detalles', 'travel-packages'); ?></a>
                </div>
            </article>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
        <?php
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
        wp_enqueue_script( 'tp-public' );

        $post = get_post( (int) $atts['id'] );
        if ( ! $post || $post->post_type !== 'paquete_viaje' ) return '';

        $price       = get_post_meta( $post->ID, '_tp_price', true );
        $currency    = get_post_meta( $post->ID, '_tp_currency', true ) ?: 'USD';
        $price_note  = get_post_meta( $post->ID, '_tp_price_note', true );
        $short_desc  = get_post_meta( $post->ID, '_tp_short_desc', true );
        $tech_fields = get_post_meta( $post->ID, '_tp_tech_fields', true );
        $dates       = get_post_meta( $post->ID, '_tp_dates', true );
        $gallery_ids = get_post_meta( $post->ID, '_tp_gallery', true );
        $thumb_url   = get_the_post_thumbnail_url( $post->ID, 'full' );

        ob_start();
        include TP_PLUGIN_DIR . 'templates/single-paquete.php';
        return ob_get_clean();
    }
}
