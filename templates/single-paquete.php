<?php
// Template: single package embedded via [tp_paquete id="X"]
if ( ! defined('ABSPATH') ) exit;
?>
<div class="tp-single-embed">

    <?php if ( $thumb_url ) : ?>
    <div class="tp-single-hero">
        <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr( get_the_title( $post ) ); ?>" loading="lazy">
    </div>
    <?php endif; ?>

    <div class="tp-single-body">

        <div class="tp-single-columns">

            <div class="tp-single-main">

                <h2 class="tp-single-title">
                    <?php echo esc_html( get_the_title( $post ) ); ?>
                </h2>

                <?php if ( $short_desc ) : ?>
                <p class="tp-single-short"><?php echo esc_html($short_desc); ?></p>
                <?php endif; ?>

                <?php if ( is_array($dates) && count($dates) ) :
                    $future = [];
                    foreach ( $dates as $d ) {
                        $from = '';
                        $to   = '';
                        if ( is_array($d) ) {
                            $from = $d['from'] ?? '';
                            $to   = $d['to'] ?? '';
                        } else {
                            $from = $d;
                        }
                        if ( ! $from ) continue;
                        $ts = strtotime( str_replace('/', '-', $from) );
                        if ( $ts >= strtotime('today') ) {
                            $future[] = [ 'from' => $from, 'to' => $to ];
                        }
                    }
                ?>
                <?php if ( count($future) ) : ?>
                <div class="tp-single-dates-cards">
                    <?php foreach ( array_slice($future, 0, 4) as $d ) : ?>
                    <div class="tp-date-card">
                        <div class="tp-date-card-icon">
                            <span class="dashicons dashicons-calendar-alt"></span>
                        </div>
                        <div class="tp-date-card-info">
                            <span class="tp-date-label"><?php _e('Salida', 'travel-packages'); ?></span>
                            <span class="tp-date-value"><?php echo esc_html( $d['from'] ); ?></span>
                        </div>
                        <div class="tp-date-card-arrow">→</div>
                        <div class="tp-date-card-info">
                            <span class="tp-date-label"><?php _e('Regreso', 'travel-packages'); ?></span>
                            <span class="tp-date-value"><?php echo esc_html( $d['to'] ?: '—' ); ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ( ! empty( $post->post_content ) ) : ?>
                <div class="tp-single-content">
                    <?php echo wpautop( wp_kses_post( $post->post_content ) ); ?>
                </div>
                <?php endif; ?>

                <?php
                $ids_arr = $gallery_ids ? array_filter( array_map('intval', explode(',', $gallery_ids)) ) : [];
                if ( count($ids_arr) ) : ?>
                <div class="tp-single-gallery-section">
                    <h3 class="tp-section-heading">
                        <span class="dashicons dashicons-format-gallery"></span>
                        <?php _e('Galería', 'travel-packages'); ?>
                    </h3>
                    <div class="tp-single-gallery tp-pinterest">
                        <?php foreach ( $ids_arr as $img_id ) :
                            $thumb = wp_get_attachment_image_url($img_id, 'medium');
                            $full  = wp_get_attachment_image_url($img_id, 'full') ?: wp_get_attachment_image_url($img_id, 'large');
                            if ( !$thumb ) continue; ?>
                        <a href="#" class="tp-gallery-item-link" data-full="<?php echo esc_url($full); ?>">
                            <img src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy">
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <aside class="tp-single-sidebar">

                <?php if ( ( is_array($tech_fields) && count($tech_fields) ) || ( ! empty( $custom_field_defs ) && ! empty( $custom_field_values ) ) ) : ?>
                <div class="tp-sidebar-card tp-sidebar-info">
                    <h3 class="tp-sidebar-heading">
                        <span class="dashicons dashicons-info"></span>
                        <?php _e('Detalles del paquete', 'travel-packages'); ?>
                    </h3>
                    <div class="tp-info-grid">
                        <?php if ( is_array( $tech_fields ) ) : foreach ( $tech_fields as $field ) : ?>
                        <div class="tp-info-item">
                            <span class="tp-info-key"><?php echo esc_html($field['key']); ?></span>
                            <span class="tp-info-val"><?php echo esc_html($field['val']); ?></span>
                        </div>
                        <?php endforeach; endif; ?>
                        <?php if ( ! empty( $custom_field_defs ) ) : foreach ( $custom_field_defs as $cf_def ) :
                            $cf_val = $custom_field_values[ $cf_def['key'] ] ?? '';
                            if ( $cf_val === '' || $cf_val === '0' ) continue;
                            if ( $cf_def['type'] === 'checkbox' ) $cf_val = ( $cf_val === '1' ? __( 'Sí', 'travel-packages' ) : __( 'No', 'travel-packages' ) );
                        ?>
                        <div class="tp-info-item">
                            <span class="tp-info-key"><?php echo esc_html( $cf_def['label'] ); ?></span>
                            <span class="tp-info-val"><?php echo esc_html( $cf_val ); ?></span>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $prices ) ) : ?>
                <div class="tp-sidebar-card tp-sidebar-price">
                    <h3 class="tp-sidebar-heading">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php _e('Precios', 'travel-packages'); ?>
                    </h3>
                    <div class="tp-price-list">
                        <?php foreach ( $prices as $p ) : ?>
                        <div class="tp-price-row <?php echo ! empty( $p['highlighted'] ) ? 'tp-price-row--highlighted' : ''; ?>">
                            <div class="tp-price-row-top">
                                <span class="tp-price-currency"><?php echo esc_html( $p['currency'] ); ?></span>
                                <span class="tp-price-amount"><?php echo esc_html( number_format( (float) $p['price'], 0, ',', '.' ) ); ?></span>
                            </div>
                            <div class="tp-price-row-bottom">
                                <?php if ( ! empty( $p['highlighted'] ) ) : ?>
                                <span class="tp-price-badge"><?php _e('Recomendado', 'travel-packages'); ?></span>
                                <?php endif; ?>
                                <span class="tp-price-note"><?php echo esc_html( $p['note'] ?: '' ); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <a href="/todos-paquetes-viajes" class="tp-single-cta">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Ver todos los paquetes', 'travel-packages'); ?>
                </a>

            </aside>

        </div>

    </div>
</div>
