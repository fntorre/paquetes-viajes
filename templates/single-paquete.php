<?php
// Template: single package embedded via [tp_paquete id="X"]
if ( ! defined('ABSPATH') ) exit;

$tp_pricing    = TP_Package_Data::get_pricing( $post->ID );
$tp_departures = TP_Package_Data::get_departures( $post->ID, true, true );
$tp_status_labels = [
    'open'    => __( 'Disponible', 'travel-packages' ),
    'soldout' => __( 'Agotado', 'travel-packages' ),
    'closed'  => __( 'Cerrado', 'travel-packages' ),
];

$tp_video     = TP_Package_Data::get_video_data( $post->ID );
$tp_itinerary = TP_Package_Data::get_itinerary( $post->ID );
$tp_map_embed = TP_Package_Data::get_map_embed_url( $post->ID );

$tp_share_url   = get_permalink( $post->ID );
$tp_share_title = get_the_title( $post );
$tp_next        = TP_Package_Data::get_next_departure( $post->ID );

$tp_whatsapp = '';
$tp_whatsapp_number = TP_Package_Data::get_whatsapp_number( $post->ID );
if ( class_exists( 'TP_Settings' ) ) {
    $tp_whatsapp_msg = sprintf( __( 'Hola, quiero consultar por el paquete "%1$s".', 'travel-packages' ), $tp_share_title );
    if ( $tp_next && ! empty( $tp_next['from'] ) ) {
        $tp_whatsapp_msg .= "\n" . sprintf( __( 'Salida: %s', 'travel-packages' ), $tp_next['from'] );
    }
    $tp_whatsapp_msg .= "\n" . $tp_share_url;
    $tp_whatsapp      = TP_Package_Data::whatsapp_url( $post->ID, $tp_whatsapp_msg );
}

$tp_inquiry_enabled    = class_exists( 'TP_Settings' ) ? TP_Settings::is_inquiry_enabled() : false;
$tp_inquiry_departures = [];
foreach ( $tp_departures as $tp_dep ) {
    if ( $tp_dep['status'] === 'open' ) {
        $tp_inquiry_departures[] = $tp_dep;
    }
}
$tp_inquiry_status = isset( $_GET['tp_inquiry'] ) ? sanitize_key( wp_unslash( $_GET['tp_inquiry'] ) ) : '';
$tp_inquiry_code   = isset( $_GET['tp_inquiry_msg'] ) ? sanitize_key( wp_unslash( $_GET['tp_inquiry_msg'] ) ) : '';
$tp_payment_available = class_exists( 'TP_Payments' ) && TP_Payments::is_checkout_available();
$tp_payment_departure_available = false;
$tp_payment_form_available = false;
if ( $tp_payment_available ) {
    foreach ( $tp_inquiry_departures as $tp_payment_departure ) {
        if ( (float) $tp_payment_departure['price_adult'] > 0 && TP_Payments::is_currency_supported( $tp_payment_departure['currency'] ) ) {
            $tp_payment_departure_available = true;
            break;
        }
    }
}
$tp_payment_form_available = $tp_payment_available && $tp_payment_departure_available;
$tp_inquiry_notice = '';
if ( $tp_inquiry_status && class_exists( 'TP_Reservations' ) ) {
    $tp_inquiry_notice = TP_Reservations::get_inquiry_notice( $tp_inquiry_status === 'success' ? 'success' : $tp_inquiry_code );
}
$tp_payment_return_notice = '';
if ( class_exists( 'TP_Payments' ) && isset( $_GET['tp_payment_reservation'], $_GET['tp_payment_token'] ) ) {
    $tp_payment_return_notice = TP_Payments::return_notice(
        absint( wp_unslash( $_GET['tp_payment_reservation'] ) ),
        sanitize_text_field( wp_unslash( $_GET['tp_payment_token'] ) )
    );
}
$tp_payment_error_notice = isset( $_GET['tp_payment_error'] ) && class_exists( 'TP_Payments' )
    ? TP_Payments::error_notice( sanitize_key( wp_unslash( $_GET['tp_payment_error'] ) ) )
    : '';

$tp_similar_packages = TP_Package_Data::get_similar_packages( $post->ID, 8 );
$tp_bottom_has_both  = $tp_inquiry_enabled && ! empty( $tp_similar_packages );
?>
<div class="tp-single-embed">

    <?php if ( $thumb_url ) : ?>
    <div class="tp-single-hero">
        <?php
        $tp_hero_id = (int) get_post_thumbnail_id( $post->ID );
        if ( $tp_hero_id ) {
            echo wp_get_attachment_image( $tp_hero_id, 'full', false, [
                'class'         => 'tp-single-hero-img',
                'alt'           => get_the_title( $post ),
                'loading'       => 'eager',
                'fetchpriority' => 'high',
                'decoding'      => 'async',
                'sizes'         => '(max-width: 1024px) 100vw, 1200px',
            ] );
        } else {
            echo '<img src="' . esc_url( $thumb_url ) . '" alt="' . esc_attr( get_the_title( $post ) ) . '" loading="eager" fetchpriority="high" decoding="async">';
        }
        ?>
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

                <?php if ( ! empty( $tp_video ) ) : ?>
                <div class="tp-single-video">
                    <?php if ( $tp_video['type'] === 'file' ) : ?>
                    <video controls preload="metadata" playsinline>
                        <source src="<?php echo esc_url( $tp_video['url'] ); ?>">
                    </video>
                    <?php else : ?>
                    <div class="tp-video-embed">
                        <iframe src="<?php echo esc_url( $tp_video['url'] ); ?>" title="<?php echo esc_attr( $tp_share_title ); ?>" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ( count( $tp_departures ) ) :
                    $tp_date_items     = array_slice( $tp_departures, 0, 6 );
                    $tp_date_count     = count( $tp_date_items );
                    $tp_dates_carousel = $tp_date_count > 1;
                ?>
                <div class="tp-dates-block" data-dates-carousel="<?php echo $tp_dates_carousel ? '1' : '0'; ?>">
                    <div class="tp-dates-viewport">
                        <div class="tp-single-dates-cards">
                            <?php foreach ( $tp_date_items as $d ) :
                                $status     = $d['status'];
                                $seats_left = $d['seats_left'];
                                $is_open    = $status === 'open';
                                $price      = $d['price_adult'];
                                $currency   = $d['currency'];
                                $short_date = $d['from'];
                                $short_to   = $d['to'] ?: '—';
                            ?>
                            <div class="tp-date-card tp-date-card--<?php echo esc_attr( $status ); ?>">
                                <div class="tp-date-card-icon">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                </div>
                                <div class="tp-date-card-info">
                                    <span class="tp-date-label"><?php _e( 'Salida', 'travel-packages' ); ?></span>
                                    <span class="tp-date-value">
                                        <?php if ( ! empty( $d['from_ts'] ) ) : ?>
                                        <time datetime="<?php echo esc_attr( wp_date( 'Y-m-d', (int) $d['from_ts'] ) ); ?>"><?php echo esc_html( $short_date ); ?></time>
                                        <?php else : ?>
                                        <?php echo esc_html( $short_date ); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="tp-date-card-arrow">→</div>
                                <div class="tp-date-card-info">
                                    <span class="tp-date-label"><?php _e( 'Regreso', 'travel-packages' ); ?></span>
                                    <span class="tp-date-value">
                                        <?php if ( ! empty( $d['to'] ) && ! empty( $d['to_ts'] ) ) : ?>
                                        <time datetime="<?php echo esc_attr( wp_date( 'Y-m-d', (int) $d['to_ts'] ) ); ?>"><?php echo esc_html( $short_to ); ?></time>
                                        <?php else : ?>
                                        <?php echo esc_html( $short_to ); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="tp-date-card-meta">
                                    <span class="tp-date-status tp-date-status--<?php echo esc_attr( $status ); ?>">
                                        <?php echo esc_html( $tp_status_labels[ $status ] ?? $status ); ?>
                                    </span>
                                    <?php if ( $seats_left !== null ) :
                                        $low_seats = $seats_left > 0 && $seats_left < 5;
                                    ?>
                                    <span class="tp-date-seats <?php echo $low_seats ? 'is-low' : ''; ?>">
                                        <?php
                                        if ( $low_seats ) {
                                            printf( esc_html__( '¡Últimos lugares! · %d disponibles', 'travel-packages' ), (int) $seats_left );
                                        } else {
                                            printf( esc_html__( '%d cupos', 'travel-packages' ), (int) $seats_left );
                                        }
                                        ?>
                                    </span>
                                    <?php endif; ?>
                                    <?php if ( $price !== '' && (float) $price > 0 ) : ?>
                                    <span class="tp-date-price">
                                        <?php echo esc_html( TP_Package_Data::currency_symbol( $currency ) ); ?>&nbsp;<?php echo esc_html( number_format( (float) $price, 0, ',', '.' ) ); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php if ( $tp_dates_carousel ) : ?>
                    <div class="tp-dates-controls">
                        <div class="tp-dates-nav">
                            <button type="button" class="tp-dates-prev" aria-label="<?php esc_attr_e( 'Salidas anteriores', 'travel-packages' ); ?>">
                                <span class="dashicons dashicons-arrow-left-alt2"></span>
                            </button>
                            <div class="tp-dates-dots"></div>
                            <button type="button" class="tp-dates-next" aria-label="<?php esc_attr_e( 'Siguientes salidas', 'travel-packages' ); ?>">
                                <span class="dashicons dashicons-arrow-right-alt2"></span>
                            </button>
                        </div>
                        <span class="tp-dates-more-label"><?php esc_html_e( 'Ver más fechas', 'travel-packages' ); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $post->post_content ) ) : ?>
                <div class="tp-single-content">
                    <?php echo wpautop( wp_kses_post( $post->post_content ) ); ?>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $tp_itinerary ) ) : ?>
                <div class="tp-single-itinerary">
                    <h3 class="tp-section-heading">
                        <span class="dashicons dashicons-location-alt"></span>
                        <?php _e( 'Itinerario', 'travel-packages' ); ?>
                    </h3>
                    <div class="tp-itinerary-accordion">
                        <?php foreach ( $tp_itinerary as $tp_i => $tp_day ) : ?>
                        <details class="tp-itinerary-day" <?php echo $tp_i === 0 ? 'open' : ''; ?>>
                            <summary>
                                <span class="tp-itinerary-num"><?php echo esc_html( $tp_day['day'] ?: sprintf( __( 'Día %d', 'travel-packages' ), $tp_i + 1 ) ); ?></span>
                                <span class="tp-itinerary-title"><?php echo esc_html( $tp_day['title'] ); ?></span>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </summary>
                            <?php if ( $tp_day['desc'] !== '' ) : ?>
                            <div class="tp-itinerary-desc"><?php echo wpautop( esc_html( $tp_day['desc'] ) ); ?></div>
                            <?php endif; ?>
                        </details>
                        <?php endforeach; ?>
                    </div>
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
                    <div class="tp-single-gallery">
                        <?php foreach ( $ids_arr as $img_id ) :
                            $thumb = wp_get_attachment_image_url($img_id, 'medium');
                            $full  = wp_get_attachment_image_url($img_id, 'full') ?: wp_get_attachment_image_url($img_id, 'large');
                            if ( !$thumb ) continue;
                            $tp_img_alt = (string) get_post_meta( $img_id, '_wp_attachment_image_alt', true );
                            if ( $tp_img_alt === '' ) {
                                $tp_img_alt = $tp_share_title;
                            }
                            ?>
                        <a href="#" class="tp-gallery-item-link" data-full="<?php echo esc_url($full); ?>">
                            <?php echo wp_get_attachment_image( $img_id, 'medium', false, [
                                'class'    => 'tp-gallery-img',
                                'alt'      => $tp_img_alt,
                                'loading'  => 'lazy',
                                'decoding' => 'async',
                            ] ); ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( $tp_map_embed ) : ?>
                <div class="tp-single-map-section">
                    <h3 class="tp-section-heading">
                        <span class="dashicons dashicons-location"></span>
                        <?php _e('Ubicación del hotel', 'travel-packages'); ?>
                    </h3>
                    <div class="tp-single-map">
                        <iframe
                            src="<?php echo esc_url( $tp_map_embed ); ?>"
                            title="<?php echo esc_attr( sprintf( __( 'Ubicación de %s en Google Maps', 'travel-packages' ), $tp_share_title ) ); ?>"
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                            allowfullscreen></iframe>
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
                                <span class="tp-price-currency"><?php echo esc_html( TP_Package_Data::currency_symbol( $p['currency'] ) ); ?></span>
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

                <?php if ( TP_Package_Data::has_pricing( $post->ID ) ) : ?>
                <div class="tp-sidebar-card tp-sidebar-price">
                    <h3 class="tp-sidebar-heading">
                        <span class="dashicons dashicons-groups"></span>
                        <?php _e( 'Precio por pasajero', 'travel-packages' ); ?>
                    </h3>
                    <div class="tp-price-list">
                        <?php if ( $tp_pricing['price_adult'] !== '' ) : ?>
                        <div class="tp-price-row tp-price-row--highlighted">
                            <div class="tp-price-row-top">
                                <span class="tp-price-currency"><?php echo esc_html( TP_Package_Data::currency_symbol( $tp_pricing['currency'] ) ); ?></span>
                                <span class="tp-price-amount"><?php echo esc_html( number_format( (float) $tp_pricing['price_adult'], 0, ',', '.' ) ); ?></span>
                            </div>
                            <div class="tp-price-row-bottom">
                                <span class="tp-price-badge"><?php _e( 'Adulto', 'travel-packages' ); ?></span>
                                <span class="tp-price-note"><?php _e( 'por persona', 'travel-packages' ); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ( $tp_pricing['price_child'] !== '' ) : ?>
                        <div class="tp-price-row">
                            <div class="tp-price-row-top">
                                <span class="tp-price-currency"><?php echo esc_html( TP_Package_Data::currency_symbol( $tp_pricing['currency'] ) ); ?></span>
                                <span class="tp-price-amount"><?php echo esc_html( number_format( (float) $tp_pricing['price_child'], 0, ',', '.' ) ); ?></span>
                            </div>
                            <div class="tp-price-row-bottom">
                                <span class="tp-price-badge"><?php _e( 'Niño', 'travel-packages' ); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ( $tp_pricing['single_supplement'] !== '' ) : ?>
                        <div class="tp-price-row">
                            <div class="tp-price-row-top">
                                <span class="tp-price-currency"><?php echo esc_html( TP_Package_Data::currency_symbol( $tp_pricing['currency'] ) ); ?></span>
                                <span class="tp-price-amount"><?php echo esc_html( number_format( (float) $tp_pricing['single_supplement'], 0, ',', '.' ) ); ?></span>
                            </div>
                            <div class="tp-price-row-bottom">
                                <span class="tp-price-badge"><?php _e( 'Suplemento single', 'travel-packages' ); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ( $tp_pricing['deposit_type'] !== 'none' && $tp_pricing['deposit_value'] !== '' ) : ?>
                    <p class="tp-deposit-note">
                        <strong><?php _e( 'Seña:', 'travel-packages' ); ?></strong>
                        <?php
                        if ( $tp_pricing['deposit_type'] === 'percent' ) {
                            echo esc_html( number_format( (float) $tp_pricing['deposit_value'], 0, ',', '.' ) . '%' );
                        } else {
                            echo '<span class="tp-deposit-amount">' . esc_html( TP_Package_Data::currency_symbol( $tp_pricing['currency'] ) ) . '&nbsp;' . esc_html( number_format( (float) $tp_pricing['deposit_value'], 0, ',', '.' ) ) . '</span>';
                        }
                        ?>
                        <?php if ( $tp_pricing['deposit_note'] ) : ?>
                        <br><span><?php echo esc_html( $tp_pricing['deposit_note'] ); ?></span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ( $tp_whatsapp ) : ?>
                <a href="<?php echo esc_url( $tp_whatsapp ); ?>" class="tp-wa-cta" target="_blank" rel="noopener">
                    <span class="dashicons dashicons-whatsapp"></span>
                    <?php _e( 'Consultar por WhatsApp', 'travel-packages' ); ?>
                </a>
                <?php if ( $tp_whatsapp_number ) : ?>
                <p class="tp-wa-number"><?php echo esc_html( sprintf( __( 'WhatsApp: +%s', 'travel-packages' ), $tp_whatsapp_number ) ); ?></p>
                <?php endif; ?>
                <?php endif; ?>

                <div class="tp-share-card">
                    <span class="tp-share-label"><?php _e( 'Compartir este paquete', 'travel-packages' ); ?></span>
                    <div class="tp-share-row">
                        <a class="tp-share-btn" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $tp_share_title . ' ' . $tp_share_url ) ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-whatsapp"></span><?php _e( 'WhatsApp', 'travel-packages' ); ?></a>
                        <button type="button" class="tp-share-btn tp-copy-link" data-url="<?php echo esc_url( $tp_share_url ); ?>"><span class="dashicons dashicons-admin-links"></span><?php _e( 'Copiar link', 'travel-packages' ); ?></button>
                        <button type="button" class="tp-share-btn" onclick="window.print()"><span class="dashicons dashicons-pdf"></span><?php _e( 'Imprimir / PDF', 'travel-packages' ); ?></button>
                    </div>
                </div>

                <a href="/todos-paquetes-viajes" class="tp-single-cta">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php _e('Ver todos los paquetes', 'travel-packages'); ?>
                </a>

            </aside>

        </div>

        <?php if ( $tp_inquiry_enabled || $tp_payment_form_available || ! empty( $tp_similar_packages ) ) : ?>
        <div class="tp-single-bottom<?php echo $tp_bottom_has_both ? ' has-similar' : ''; ?>">

            <?php if ( $tp_inquiry_enabled || $tp_payment_form_available ) : ?>
            <div class="tp-sidebar-card tp-inquiry-card tp-inquiry-full" id="tp-inquiry">
                <h3 class="tp-sidebar-heading">
                    <span class="dashicons dashicons-email-alt"></span>
                    <?php _e( 'Consultar / Reservar', 'travel-packages' ); ?>
                </h3>

                <?php if ( $tp_inquiry_notice ) : ?>
                <div class="tp-inquiry-notice <?php echo $tp_inquiry_status === 'success' ? 'is-success' : 'is-error'; ?>">
                    <?php echo esc_html( $tp_inquiry_notice ); ?>
                </div>
                <?php endif; ?>
                <?php if ( $tp_payment_return_notice ) : ?>
                    <?php echo wp_kses_post( $tp_payment_return_notice ); ?>
                <?php elseif ( $tp_payment_error_notice ) : ?>
                <div class="tp-inquiry-notice is-error"><?php echo esc_html( $tp_payment_error_notice ); ?></div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tp-inquiry-form">
                    <input type="hidden" name="tp_inquiry_package" value="<?php echo (int) $post->ID; ?>">
                    <?php wp_nonce_field( 'tp_public_inquiry', 'tp_inquiry_nonce' ); ?>
                    <div class="tp-inquiry-hp" aria-hidden="true">
                        <label><?php _e( 'No completar', 'travel-packages' ); ?><input type="text" name="tp_website" tabindex="-1" autocomplete="off"></label>
                    </div>

                    <label class="tp-inquiry-field">
                        <span><?php _e( 'Nombre y apellido', 'travel-packages' ); ?> *</span>
                        <input type="text" name="tp_inquiry_name" required>
                    </label>
                    <label class="tp-inquiry-field">
                        <span><?php _e( 'Email', 'travel-packages' ); ?> *</span>
                        <input type="email" name="tp_inquiry_email" required>
                    </label>
                    <label class="tp-inquiry-field">
                        <span><?php _e( 'Teléfono / WhatsApp', 'travel-packages' ); ?></span>
                        <input type="tel" name="tp_inquiry_phone">
                    </label>

                    <?php if ( ! empty( $tp_inquiry_departures ) ) : ?>
                    <label class="tp-inquiry-field">
                        <span><?php _e( 'Salida', 'travel-packages' ); ?></span>
                        <select name="tp_inquiry_uid">
                            <option value=""><?php _e( 'Consulta general', 'travel-packages' ); ?></option>
                            <?php foreach ( $tp_inquiry_departures as $tp_dep ) : ?>
                            <option value="<?php echo esc_attr( $tp_dep['uid'] ); ?>">
                                <?php echo esc_html( $tp_dep['from'] . ( $tp_dep['to'] ? ' → ' . $tp_dep['to'] : '' ) ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <?php endif; ?>

                    <div class="tp-inquiry-pax">
                        <label class="tp-inquiry-field">
                            <span><?php _e( 'Adultos', 'travel-packages' ); ?></span>
                            <input type="number" name="tp_inquiry_adults" min="1" value="1">
                        </label>
                        <label class="tp-inquiry-field">
                            <span><?php _e( 'Niños', 'travel-packages' ); ?></span>
                            <input type="number" name="tp_inquiry_children" min="0" value="0">
                        </label>
                    </div>

                    <label class="tp-inquiry-field tp-inquiry-field--full">
                        <span><?php _e( 'Mensaje', 'travel-packages' ); ?></span>
                        <textarea name="tp_inquiry_message" rows="3" placeholder="<?php esc_attr_e( 'Contanos qué necesitás...', 'travel-packages' ); ?>"></textarea>
                    </label>
                    <label class="tp-inquiry-consent">
                        <input type="checkbox" name="tp_inquiry_consent" value="1" required>
                        <span><?php _e( 'Acepto ser contactado por esta consulta.', 'travel-packages' ); ?></span>
                    </label>
                    <?php if ( $tp_inquiry_enabled ) : ?>
                    <button type="submit" name="action" value="tp_public_inquiry" class="tp-inquiry-submit"><?php _e( 'Enviar consulta', 'travel-packages' ); ?></button>
                    <?php endif; ?>
                    <?php if ( $tp_payment_form_available ) : ?>
                    <button type="submit" name="action" value="tp_start_payment" class="tp-inquiry-submit tp-inquiry-pay"><?php _e( 'Reservar y pagar total', 'travel-packages' ); ?></button>
                    <small class="tp-payment-disclaimer"><?php _e( 'El pago se procesa de forma segura en Mercado Pago. Al iniciar, retenemos los cupos durante 15 minutos.', 'travel-packages' ); ?></small>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $tp_similar_packages ) ) : ?>
            <aside class="tp-single-similar">
                <div class="tp-sidebar-card tp-similar-card">
                    <h3 class="tp-sidebar-heading">
                        <span class="dashicons dashicons-location-alt"></span>
                        <?php _e( 'Destinos similares', 'travel-packages' ); ?>
                    </h3>

                    <div class="tp-grid-carousel tp-similar-carousel" data-desktop="1" data-movile="1">
                        <div class="tp-grid-carousel-track">
                            <?php foreach ( $tp_similar_packages as $tp_similar_post ) :
                                $tp_similar_id       = $tp_similar_post->ID;
                                $tp_similar_link     = get_permalink( $tp_similar_id );
                                $tp_similar_title    = get_the_title( $tp_similar_id );
                                $tp_similar_desc     = get_post_meta( $tp_similar_id, '_tp_short_desc', true );
                                $tp_similar_featured = get_post_meta( $tp_similar_id, '_tp_featured', true );
                                $tp_similar_thumb    = get_the_post_thumbnail_url( $tp_similar_id, 'medium_large' );
                                $tp_similar_price    = TP_Package_Data::get_best_price( $tp_similar_id );
                                $tp_similar_next     = TP_Package_Data::get_next_departure( $tp_similar_id );
                                $tp_similar_date     = $tp_similar_next ? $tp_similar_next['from'] : '';
                                $tp_similar_seats    = ( $tp_similar_next && $tp_similar_next['seats_left'] !== null ) ? (int) $tp_similar_next['seats_left'] : null;

                                $tp_similar_wa = '';
                                if ( class_exists( 'TP_Settings' ) ) {
                                    $tp_similar_msg = sprintf( __( 'Hola, quiero consultar por el paquete "%s".', 'travel-packages' ), $tp_similar_title );
                                    if ( $tp_similar_date ) {
                                        $tp_similar_msg .= "\n" . sprintf( __( 'Salida: %s', 'travel-packages' ), $tp_similar_date );
                                    }
                                    $tp_similar_msg .= "\n" . $tp_similar_link;
                                    $tp_similar_wa   = TP_Package_Data::whatsapp_url( $tp_similar_id, $tp_similar_msg );
                                }
                            ?>
                            <div class="tp-grid-carousel-item">
                                <article class="tp-card tp-card-similar <?php echo $tp_similar_featured ? 'tp-card-featured' : ''; ?>">
                                    <?php if ( $tp_similar_featured ) : ?>
                                    <div class="tp-badge-featured">⭐ <?php _e( 'Destacado', 'travel-packages' ); ?></div>
                                    <?php endif; ?>
                                    <a href="<?php echo esc_url( $tp_similar_link ); ?>" class="tp-card-image-link">
                                        <?php if ( $tp_similar_thumb ) : ?>
                                        <img src="<?php echo esc_url( $tp_similar_thumb ); ?>" alt="<?php echo esc_attr( $tp_similar_title ); ?>" class="tp-card-img" loading="lazy">
                                        <?php else : ?>
                                        <div class="tp-card-img-placeholder"><span class="dashicons dashicons-airplane"></span></div>
                                        <?php endif; ?>
                                        <?php if ( $tp_similar_date ) : ?>
                                        <div class="tp-card-next-date">
                                            <span class="dashicons dashicons-calendar-alt"></span>
                                            <?php echo esc_html( $tp_similar_date ); ?>
                                            <?php if ( $tp_similar_seats !== null && $tp_similar_seats > 0 ) : ?>
                                            <span class="tp-card-seats <?php echo $tp_similar_seats < 5 ? 'is-low' : ''; ?>">
                                                <?php
                                                if ( $tp_similar_seats < 5 ) {
                                                    echo esc_html( sprintf( __( '¡Últimos lugares! · %d disponibles', 'travel-packages' ), $tp_similar_seats ) );
                                                } else {
                                                    echo esc_html( sprintf( __( '%d cupos disponibles', 'travel-packages' ), $tp_similar_seats ) );
                                                }
                                                ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                    </a>
                                    <div class="tp-card-body">
                                        <h3 class="tp-card-title"><a href="<?php echo esc_url( $tp_similar_link ); ?>"><?php echo esc_html( $tp_similar_title ); ?></a></h3>
                                        <?php if ( $tp_similar_desc ) : ?>
                                        <p class="tp-card-desc"><?php echo esc_html( $tp_similar_desc ); ?></p>
                                        <?php endif; ?>
                                        <?php if ( $tp_similar_price ) : ?>
                                        <div class="tp-card-price">
                                            <span class="tp-from-small"><?php _e( 'Desde', 'travel-packages' ); ?></span>
                                            <span class="tp-price-amount"><?php echo esc_html( TP_Package_Data::currency_symbol( $tp_similar_price['currency'] ) ); ?> <strong><?php echo number_format( (float) $tp_similar_price['price'], 0, ',', '.' ); ?></strong></span>
                                            <?php if ( ! empty( $tp_similar_price['note'] ) ) : ?><span class="tp-price-note"><?php echo esc_html( $tp_similar_price['note'] ); ?></span><?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="tp-card-actions">
                                            <a href="<?php echo esc_url( $tp_similar_link ); ?>" class="tp-btn-card"><?php _e( 'Ver detalles', 'travel-packages' ); ?></a>
                                            <?php if ( $tp_similar_wa !== '' ) : ?>
                                            <a href="<?php echo esc_url( $tp_similar_wa ); ?>" class="tp-btn-card-wa" target="_blank" rel="noopener"><span class="dashicons dashicons-whatsapp"></span><?php _e( 'Consultar', 'travel-packages' ); ?></a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </article>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="tp-carousel-controls">
                            <button type="button" class="tp-carousel-prev" aria-label="<?php esc_attr_e( 'Anterior', 'travel-packages' ); ?>">‹</button>
                            <div class="tp-carousel-dots"></div>
                            <button type="button" class="tp-carousel-next" aria-label="<?php esc_attr_e( 'Siguiente', 'travel-packages' ); ?>">›</button>
                        </div>
                    </div>
                </div>
            </aside>
            <?php endif; ?>

        </div>
        <?php endif; ?>

    </div>
</div>
