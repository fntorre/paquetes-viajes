<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Templates {

    public static function init() {
        add_filter( 'the_content', [ __CLASS__, 'render_package_detail' ] );
        add_action( 'wp_head', [ __CLASS__, 'render_seo_meta' ], 5 );
    }

    /** Open Graph, Twitter Card y JSON-LD para la ficha del paquete. */
    public static function render_seo_meta() {
        if ( ! is_singular( TP_Package_Data::POST_TYPE ) ) {
            return;
        }

        $has_seo = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
        if ( $has_seo ) {
            return;
        }

        $post_id = get_queried_object_id();
        if ( ! $post_id ) {
            return;
        }

        $title = get_the_title( $post_id );
        $desc  = trim( (string) get_post_meta( $post_id, '_tp_short_desc', true ) );
        if ( $desc === '' ) {
            $desc = wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', $post_id ) ), 30, '…' );
        }

        $url   = get_permalink( $post_id );
        $image = get_the_post_thumbnail_url( $post_id, 'large' );
        $best  = TP_Package_Data::get_best_price( $post_id );

        echo "\n<!-- TourFlow SEO -->\n";
        if ( $desc ) {
            echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
        }
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
        if ( $desc ) {
            echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
        if ( $image ) {
            echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
            echo '<meta property="og:image:alt" content="' . esc_attr( $title ) . '">' . "\n";
        }
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
        if ( $desc ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
        }
        if ( $image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'TouristTrip',
            'name'     => $title,
            'url'      => $url,
        ];
        if ( $desc ) {
            $schema['description'] = $desc;
        }
        if ( $image ) {
            $schema['image'] = $image;
        }

        $next = TP_Package_Data::get_next_departure( $post_id );
        if ( $best ) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => (float) $best['price'],
                'priceCurrency' => $best['currency'] ?: 'USD',
                'url'           => $url,
                'availability'  => $next ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            ];
            if ( $next && ! empty( $next['from_ts'] ) ) {
                $schema['offers']['availabilityStarts'] = wp_date( 'Y-m-d', (int) $next['from_ts'] );
            }
        }

        $itinerary = TP_Package_Data::get_itinerary( $post_id );
        if ( $itinerary ) {
            $items = [];
            foreach ( $itinerary as $i => $day ) {
                $items[] = [
                    '@type'       => 'ListItem',
                    'position'    => $i + 1,
                    'name'        => $day['title'] ?: $day['day'],
                    'description' => $day['desc'],
                ];
            }
            $schema['itinerary'] = [
                '@type'           => 'ItemList',
                'itemListElement' => $items,
            ];
        }

        echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
    }

    public static function render_package_detail( $content ) {
        if ( ! is_singular( 'paquete_viaje' ) || ! in_the_loop() ) {
            return $content;
        }

        // Ensure public assets are loaded for the single package view
        if ( function_exists('wp_enqueue_style') ) {
            // These handles are registered in TP_Shortcodes::enqueue_assets
            wp_enqueue_style( 'tp-public' );
            wp_enqueue_style( 'dashicons' );
            wp_enqueue_script( 'tp-public' );
        }

        global $post;
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
}
