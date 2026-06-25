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

    <h2 class="tp-single-title">
        <a href="<?php echo esc_url( get_permalink( $post ) ); ?>">
            <?php echo esc_html( get_the_title( $post ) ); ?>
        </a>
    </h2>

    <div class="tp-single-body">

        <?php if ( $short_desc ) : ?>
        <p class="tp-single-short"><?php echo esc_html($short_desc); ?></p>
        <?php endif; ?>

        <?php if ( ! empty( $post->post_content ) ) : ?>
        <div class="tp-single-filas">
            <div class="tp-single-content">
                <?php echo wpautop( wp_kses_post( $post->post_content ) ); ?>

                <?php
                $ids_arr = $gallery_ids ? array_filter( array_map('intval', explode(',', $gallery_ids)) ) : [];
                if ( count($ids_arr) ) : ?>
                <div class="tp-gallery-tittle">
                        <h4>Conocé mejor el lugar</h4>
                    </div>
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
                <?php endif; ?>

            </div><!-- /.tp-single-content -->
 <div class="tp-single-price-box">
            <?php if ( ( is_array($tech_fields) && count($tech_fields) ) || ( ! empty( $custom_field_defs ) && ! empty( $custom_field_values ) ) ) : ?>
            <table class="tp-tech-table">
                <?php if ( is_array( $tech_fields ) ) : foreach ( $tech_fields as $field ) : ?>
                <tr>
                    <th><?php echo esc_html($field['key']); ?></th>
                    <td><?php echo esc_html($field['val']); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                <?php if ( ! empty( $custom_field_defs ) ) : foreach ( $custom_field_defs as $cf_def ) :
                    $cf_val = $custom_field_values[ $cf_def['key'] ] ?? '';
                    if ( $cf_val === '' || $cf_val === '0' ) continue;
                    if ( $cf_def['type'] === 'checkbox' ) $cf_val = '✓ ' . ( $cf_val === '1' ? __( 'Sí', 'travel-packages' ) : __( 'No', 'travel-packages' ) );
                ?>
                <tr>
                    <th><?php echo esc_html( $cf_def['label'] ); ?></th>
                    <td><?php echo esc_html( $cf_val ); ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </table>
            <?php endif; ?>

            <?php if ( $price ) : ?>
           
                <div class="tp-single-price-box-container">
                    <div class="tp-from-small"><?php _e('Precio desde', 'travel-packages'); ?></div>
                    <div class="tp-single-price-big"><?php echo esc_html($currency . ' ' . number_format((float)$price, 0, ',', '.')); ?></div>
                    <?php if ( $price_note ) : ?><div class="tp-single-price-note"><?php echo esc_html($price_note); ?></div><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /.tp-single-filas -->
        <?php endif; ?>

        <?php if ( is_array($dates) && count($dates) ) :
            $future = [];
            foreach ( $dates as $d ) {
                $from = '';
                $to = '';
                if ( is_array($d) ) {
                    $from = $d['from'] ?? '';
                    $to   = $d['to'] ?? '';
                } else {
                    $from = $d;
                }
                if ( ! $from ) continue;
                $ts = strtotime( str_replace('/', '-', $from) );
                if ( $ts >= strtotime('today') ) {
                    $label = $from;
                    if ( $to ) $label .= ' → ' . $to;
                    $future[] = $label;
                }
            }
        ?>
        <?php if ( count($future) ) : ?>
        <div class="tp-single-dates">
            <h4>📅 <?php _e('Próximas salidas', 'travel-packages'); ?></h4>
            <div class="tp-dates-chips">
                <?php foreach ( array_slice($future, 0, 8) as $d ) : ?>
                <span class="tp-date-chip"><?php echo esc_html($d); ?></span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </div><!-- /.tp-single-body -->
</div><!-- /.tp-single-embed -->