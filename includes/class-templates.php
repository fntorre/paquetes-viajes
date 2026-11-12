<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Templates {

    public static function init() {
        add_filter( 'the_content', [ __CLASS__, 'render_package_detail' ] );
        add_action( 'wp_head', [ __CLASS__, 'render_lcp_preload' ], 1 );
        add_action( 'wp_head', [ __CLASS__, 'render_seo_meta' ], 5 );
        add_action( 'wp_head', [ __CLASS__, 'render_collection_schema' ], 6 );
        add_filter( 'document_title_parts', [ __CLASS__, 'filter_title_parts' ] );
        add_filter( 'wp_robots', [ __CLASS__, 'filter_robots' ] );
    }

    /** true si hay un plugin SEO que ya emite meta tags/entidades. */
    public static function has_seo_plugin() {
        return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' ) || defined( 'AIOSEO_VERSION' );
    }

    private static function schema_enabled() {
        return (bool) apply_filters( 'tp_enable_schema', true );
    }

    private static function site_name() {
        $name = trim( wp_strip_all_tags( get_bloginfo( 'name' ) ) );
        return $name !== '' ? $name : get_bloginfo( 'name' );
    }

    private static function in_language() {
        $lang = get_bloginfo( 'language' );
        return $lang ? $lang : str_replace( '_', '-', get_locale() );
    }

    private static function site_logo_url() {
        $logo_id = (int) get_theme_mod( 'custom_logo' );
        if ( $logo_id ) {
            $src = wp_get_attachment_image_url( $logo_id, 'full' );
            if ( $src ) {
                return $src;
            }
        }
        $icon = get_site_icon_url( 512 );
        return $icon ? $icon : '';
    }

    private static function org_id() {
        return home_url( '/#organization' );
    }

    private static function website_id() {
        return home_url( '/#website' );
    }

    /** Organización/agencia para brand, provider y sameAs. */
    private static function organization_inline() {
        $org = [
            '@type' => 'TravelAgency',
            'name'  => self::site_name(),
            'url'   => home_url( '/' ),
        ];

        $logo = self::site_logo_url();
        if ( $logo ) {
            $org['logo'] = esc_url_raw( $logo );
        }

        if ( class_exists( 'TP_Settings' ) ) {
            $phone = preg_replace( '/\D+/', '', (string) TP_Settings::get_whatsapp_number() );
            if ( $phone !== '' ) {
                $org['telephone'] = '+' . $phone;
            }
            $email = TP_Settings::get_inquiry_email();
            if ( $email ) {
                $org['email'] = $email;
            }
        }

        $same_as = apply_filters( 'tp_schema_same_as', [] );
        if ( is_array( $same_as ) ) {
            $same_as = array_values( array_filter( array_map( 'esc_url_raw', $same_as ) ) );
            if ( $same_as ) {
                $org['sameAs'] = $same_as;
            }
        }

        return $org;
    }

    private static function organization_node() {
        return array_merge( [ '@id' => self::org_id() ], self::organization_inline() );
    }

    private static function website_node() {
        $node = [
            '@type'      => 'WebSite',
            '@id'        => self::website_id(),
            'url'        => home_url( '/' ),
            'name'       => self::site_name(),
            'inLanguage' => self::in_language(),
            'publisher'  => [ '@id' => self::org_id() ],
        ];

        if ( class_exists( 'TP_Settings' ) ) {
            $search_url = TP_Settings::get_search_url();
            if ( $search_url ) {
                $separator = strpos( $search_url, '?' ) !== false ? '&' : '?';
                $node['potentialAction'] = [
                    '@type'       => 'SearchAction',
                    'target'      => [
                        '@type'       => 'EntryPoint',
                        'urlTemplate' => $search_url . $separator . 'tp_search={search_term_string}',
                    ],
                    'query-input' => 'required name=search_term_string',
                ];
            }
        }

        return $node;
    }

    private static function plain_title( $post_id ) {
        return trim( wp_strip_all_tags( get_the_title( $post_id ) ) );
    }

    /** Título SEO con fallback al título del paquete. */
    private static function seo_title( $post_id ) {
        $raw   = get_post_meta( $post_id, TP_Package_Data::META_SEO_TITLE, true );
        $title = is_scalar( $raw ) ? trim( wp_strip_all_tags( (string) $raw ) ) : '';
        return $title !== '' ? $title : self::plain_title( $post_id );
    }

    /** Descripción base: SEO, short desc, extracto o contenido. */
    private static function package_description( $post_id ) {
        $raw  = get_post_meta( $post_id, TP_Package_Data::META_SEO_DESCRIPTION, true );
        $desc = is_scalar( $raw ) ? trim( (string) $raw ) : '';
        if ( $desc === '' ) {
            $desc = trim( (string) get_post_meta( $post_id, '_tp_short_desc', true ) );
        }
        if ( $desc === '' ) {
            $desc = trim( (string) get_post_field( 'post_excerpt', $post_id ) );
        }
        if ( $desc === '' ) {
            $desc = (string) get_post_field( 'post_content', $post_id );
        }

        $desc = wp_strip_all_tags( $desc );
        $desc = preg_replace( '/\s+/u', ' ', $desc );
        return trim( $desc );
    }

    private static function meta_description( $text ) {
        $text = trim( (string) $text );
        if ( $text === '' ) {
            return '';
        }
        if ( function_exists( 'wp_html_excerpt' ) ) {
            return wp_html_excerpt( $text, 158, '…' );
        }
        return $text;
    }

    private static function package_images( $post_id ) {
        $ids = [];
        $raw_seo_image = get_post_meta( $post_id, TP_Package_Data::META_SEO_IMAGE, true );
        $seo_image     = is_scalar( $raw_seo_image ) ? (int) $raw_seo_image : 0;
        if ( $seo_image && wp_attachment_is_image( $seo_image ) ) {
            $ids[] = $seo_image;
        }
        $thumb = (int) get_post_thumbnail_id( $post_id );
        if ( $thumb && ! in_array( $thumb, $ids, true ) ) {
            $ids[] = $thumb;
        }
        if ( class_exists( 'TP_Package_Data' ) ) {
            foreach ( TP_Package_Data::get_gallery_ids( $post_id ) as $gallery_id ) {
                if ( ! in_array( $gallery_id, $ids, true ) ) {
                    $ids[] = (int) $gallery_id;
                }
            }
        }
        $ids = array_slice( $ids, 0, 8 );

        $images = [];
        foreach ( $ids as $id ) {
            $src = wp_get_attachment_image_src( $id, 'full' );
            if ( ! $src ) {
                continue;
            }
            $images[] = [
                '@type'  => 'ImageObject',
                'url'    => esc_url_raw( $src[0] ),
                'width'  => (int) $src[1],
                'height' => (int) $src[2],
            ];
        }

        return $images;
    }

    private static function package_term_names( $post_id, $taxonomy ) {
        $terms = get_the_terms( $post_id, $taxonomy );
        if ( ! $terms || is_wp_error( $terms ) ) {
            return [];
        }
        return array_values( array_filter( wp_list_pluck( $terms, 'name' ) ) );
    }

    /** Oferta única o AggregateOffer a partir de los precios configurados. */
    private static function package_offers( $post_id, $permalink ) {
        if ( ! class_exists( 'TP_Package_Data' ) ) {
            return null;
        }

        $currency = '';
        $amounts  = [];

        $pricing = TP_Package_Data::get_pricing( $post_id );
        if ( $pricing['price_adult'] !== '' && (float) $pricing['price_adult'] > 0 ) {
            $currency = $pricing['currency'] ? $pricing['currency'] : 'USD';
            $amounts[] = (float) $pricing['price_adult'];
        }

        foreach ( (array) TP_Package_Data::get_prices( $post_id ) as $price ) {
            if ( ! is_array( $price ) ) {
                continue;
            }
            $value = (float) ( $price['price'] ?? 0 );
            if ( $value <= 0 ) {
                continue;
            }
            $code = ! empty( $price['currency'] ) ? $price['currency'] : 'USD';
            if ( $currency === '' ) {
                $currency = $code;
            }
            if ( $code === $currency ) {
                $amounts[] = $value;
            }
        }

        if ( empty( $amounts ) ) {
            return null;
        }

        $amounts = array_values( array_unique( $amounts ) );
        sort( $amounts );

        $next      = TP_Package_Data::get_next_departure( $post_id );
        $available = $next
            && $next['status'] === 'open'
            && ( $next['seats_left'] === null || (int) $next['seats_left'] > 0 );

        $price_valid = '';
        if ( $next ) {
            $ts = ! empty( $next['to_ts'] ) ? (int) $next['to_ts'] : ( ! empty( $next['from_ts'] ) ? (int) $next['from_ts'] : 0 );
            if ( $ts ) {
                $price_valid = wp_date( 'Y-m-d', $ts );
            }
        }
        if ( $price_valid === '' ) {
            $price_valid = wp_date( 'Y-m-d', strtotime( '+1 year' ) );
        }

        $common = [
            'priceCurrency'    => $currency,
            'availability'     => $available ? 'https://schema.org/InStock' : 'https://schema.org/SoldOut',
            'url'              => $permalink,
            'priceValidUntil'  => $price_valid,
            'seller'           => self::organization_inline(),
        ];

        if ( count( $amounts ) === 1 ) {
            return array_merge( [ '@type' => 'Offer', 'price' => $amounts[0] ], $common );
        }

        return array_merge( [
            '@type'      => 'AggregateOffer',
            'lowPrice'   => $amounts[0],
            'highPrice'  => $amounts[ count( $amounts ) - 1 ],
            'offerCount' => count( $amounts ),
        ], $common );
    }

    private static function breadcrumb_node( $post_id, $permalink, $title ) {
        $items = [ [
            '@type'    => 'ListItem',
            'position' => 1,
            'name'     => __( 'Inicio', 'travel-packages' ),
            'item'     => home_url( '/' ),
        ] ];

        $position = 2;
        $archive  = get_post_type_archive_link( TP_Package_Data::POST_TYPE );
        if ( $archive ) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => __( 'Paquetes de viaje', 'travel-packages' ),
                'item'     => $archive,
            ];
        }

        $terms = get_the_terms( $post_id, TP_Package_Data::TAX_DESTINO );
        if ( $terms && ! is_wp_error( $terms ) ) {
            $term = $terms[0];
            $link = get_term_link( $term );
            if ( ! is_wp_error( $link ) ) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => $term->name,
                    'item'     => $link,
                ];
            }
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $position,
            'name'     => $title,
            'item'     => $permalink,
        ];

        return [
            '@type'           => 'BreadcrumbList',
            '@id'             => $permalink . '#tp-breadcrumb',
            'itemListElement' => $items,
        ];
    }

    private static function product_node( $post_id, $title, $desc, $permalink, $images, $offers ) {
        $node = [
            '@type'       => 'Product',
            '@id'         => $permalink . '#tp-product',
            'name'        => $title,
            'description' => $desc,
            'url'         => $permalink,
            'sku'         => 'TP-' . $post_id,
            'brand'       => [
                '@type' => 'Organization',
                'name'  => self::site_name(),
            ],
            'datePublished' => get_post_time( 'c', true, $post_id ),
            'dateModified'  => get_post_modified_time( 'c', true, $post_id ),
        ];

        if ( $images ) {
            $node['image'] = count( $images ) === 1 ? $images[0] : $images;
        }

        $destinos = self::package_term_names( $post_id, TP_Package_Data::TAX_DESTINO );
        if ( $destinos ) {
            $node['category'] = implode( ', ', $destinos );
        }

        if ( $offers ) {
            $node['offers'] = $offers;
        }

        $tech_fields = get_post_meta( $post_id, '_tp_tech_fields', true );
        if ( is_array( $tech_fields ) ) {
            $properties = [];
            foreach ( $tech_fields as $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }
                $key   = trim( (string) ( $field['key'] ?? '' ) );
                $value = trim( (string) ( $field['val'] ?? '' ) );
                if ( $key !== '' && $value !== '' ) {
                    $properties[] = [
                        '@type' => 'PropertyValue',
                        'name'  => $key,
                        'value' => $value,
                    ];
                }
            }
            if ( $properties ) {
                $node['additionalProperty'] = $properties;
            }
        }

        return $node;
    }

    private static function tourist_trip_node( $post_id, $title, $desc, $permalink, $images, $offers ) {
        $node = [
            '@type'       => 'TouristTrip',
            '@id'         => $permalink . '#tp-trip',
            'name'        => $title,
            'description' => $desc,
            'url'         => $permalink,
            'provider'    => self::organization_inline(),
            'inLanguage'  => self::in_language(),
        ];

        if ( $images ) {
            $node['image'] = count( $images ) === 1 ? $images[0] : $images;
        }

        $destinos = self::package_term_names( $post_id, TP_Package_Data::TAX_DESTINO );
        if ( $destinos ) {
            $node['areaServed'] = array_map( function( $name ) {
                return [ '@type' => 'Place', 'name' => $name ];
            }, $destinos );
        }

        $tipologias = self::package_term_names( $post_id, TP_Package_Data::TAX_TIPOLOGIA );
        if ( $tipologias ) {
            $node['touristType'] = count( $tipologias ) === 1 ? $tipologias[0] : $tipologias;
        }

        $itinerary = TP_Package_Data::get_itinerary( $post_id );
        if ( $itinerary ) {
            $items = [];
            foreach ( $itinerary as $index => $day ) {
                $name = ! empty( $day['title'] ) ? $day['title'] : ( $day['day'] ?? '' );
                $name = trim( wp_strip_all_tags( (string) $name ) );
                if ( $name === '' && empty( $day['desc'] ) ) {
                    continue;
                }
                $item = [
                    '@type'    => 'ListItem',
                    'position' => $index + 1,
                    'name'     => $name,
                ];
                if ( ! empty( $day['desc'] ) ) {
                    $item['description'] = trim( wp_strip_all_tags( (string) $day['desc'] ) );
                }
                $items[] = $item;
            }
            if ( $items ) {
                $node['itinerary'] = [
                    '@type'           => 'ItemList',
                    'itemListElement' => $items,
                ];
            }
        }

        if ( $offers ) {
            $node['offers'] = $offers;
        }

        return $node;
    }

    private static function webpage_node( $post_id, $title, $desc, $permalink, $images ) {
        $node = [
            '@type'      => 'WebPage',
            '@id'        => $permalink . '#tp-webpage',
            'url'        => $permalink,
            'name'       => $title,
            'inLanguage' => self::in_language(),
            'breadcrumb' => [ '@id' => $permalink . '#tp-breadcrumb' ],
            'datePublished' => get_post_time( 'c', true, $post_id ),
            'dateModified'  => get_post_modified_time( 'c', true, $post_id ),
        ];

        if ( ! self::has_seo_plugin() ) {
            $node['isPartOf'] = [ '@id' => self::website_id() ];
        }

        if ( $desc !== '' ) {
            $node['description'] = $desc;
        }
        if ( $images ) {
            $node['primaryImageOfPage'] = $images[0];
        }

        return $node;
    }

    private static function print_json_ld_graph( $nodes ) {
        $nodes = array_values( array_filter( $nodes ) );
        if ( empty( $nodes ) ) {
            return;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@graph'   => $nodes,
        ];

        echo '<script type="application/ld+json">'
            . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT )
            . '</script>' . "\n";
    }

    /** Meta tags (solo si no hay plugin SEO) + JSON-LD de la ficha. */
    public static function render_seo_meta() {
        $nodes = [];

        if ( ! self::has_seo_plugin() ) {
            $nodes[] = self::organization_node();
            $nodes[] = self::website_node();
        }

        if ( is_singular( TP_Package_Data::POST_TYPE ) ) {
            $post_id = (int) get_queried_object_id();
            if ( $post_id ) {
                $title      = self::plain_title( $post_id );
                $meta_title = self::seo_title( $post_id );
                $desc       = self::package_description( $post_id );
                $permalink  = get_permalink( $post_id );
                $images     = self::package_images( $post_id );
                $offers     = self::package_offers( $post_id, $permalink );

                if ( ! self::has_seo_plugin() ) {
                    self::print_meta_tags( $meta_title, $desc, $permalink, $images, $offers );
                }

                if ( self::schema_enabled() ) {
                    if ( ! self::has_seo_plugin() ) {
                        $nodes[] = self::webpage_node( $post_id, $title, $desc, $permalink, $images );
                        $nodes[] = self::breadcrumb_node( $post_id, $permalink, $title );
                    }
                    $nodes[] = self::product_node( $post_id, $title, $desc, $permalink, $images, $offers );
                    $nodes[] = self::tourist_trip_node( $post_id, $title, $desc, $permalink, $images, $offers );
                }
            }
        }

        if ( self::schema_enabled() ) {
            self::print_json_ld_graph( $nodes );
        }
    }

    private static function print_meta_tags( $title, $desc, $permalink, $images, $offers ) {
        $short = self::meta_description( $desc );
        if ( $short === '' ) {
            $short = self::meta_description( get_bloginfo( 'description' ) );
        }

        $image_url = '';
        if ( $images ) {
            $image_url = $images[0]['url'];
        } else {
            $image_url = self::site_logo_url();
        }

        echo "\n<!-- Tureo SEO -->\n";

        if ( $short !== '' ) {
            echo '<meta name="description" content="' . esc_attr( $short ) . '">' . "\n";
        }

        echo '<meta property="og:locale" content="' . esc_attr( str_replace( '-', '_', self::in_language() ) ) . '">' . "\n";
        echo '<meta property="og:type" content="' . ( $offers ? 'product' : 'article' ) . '">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
        if ( $short !== '' ) {
            echo '<meta property="og:description" content="' . esc_attr( $short ) . '">' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url( $permalink ) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( self::site_name() ) . '">' . "\n";

        if ( $image_url ) {
            echo '<meta property="og:image" content="' . esc_url( $image_url ) . '">' . "\n";
            if ( ! empty( $images[0]['width'] ) ) {
                echo '<meta property="og:image:width" content="' . (int) $images[0]['width'] . '">' . "\n";
                echo '<meta property="og:image:height" content="' . (int) $images[0]['height'] . '">' . "\n";
            }
            echo '<meta property="og:image:alt" content="' . esc_attr( $title ) . '">' . "\n";
        }

        if ( $offers ) {
            $amount = isset( $offers['lowPrice'] ) ? $offers['lowPrice'] : ( $offers['price'] ?? '' );
            if ( $amount !== '' ) {
                echo '<meta property="product:price:amount" content="' . esc_attr( number_format( (float) $amount, 2, '.', '' ) ) . '">' . "\n";
            }
            if ( ! empty( $offers['priceCurrency'] ) ) {
                echo '<meta property="product:price:currency" content="' . esc_attr( $offers['priceCurrency'] ) . '">' . "\n";
            }
            echo '<meta property="product:availability" content="' . ( strpos( (string) $offers['availability'], 'InStock' ) !== false ? 'in stock' : 'out of stock' ) . '">' . "\n";
        }

        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
        if ( $short !== '' ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $short ) . '">' . "\n";
        }
        if ( $image_url ) {
            echo '<meta name="twitter:image" content="' . esc_url( $image_url ) . '">' . "\n";
        }
        $twitter_site = apply_filters( 'tp_schema_twitter_site', '' );
        if ( $twitter_site ) {
            echo '<meta name="twitter:site" content="' . esc_attr( $twitter_site ) . '">' . "\n";
        }
    }

    /** Precarga la imagen principal para mejorar el LCP. */
    public static function render_lcp_preload() {
        if ( ! is_singular( TP_Package_Data::POST_TYPE ) ) {
            return;
        }

        $post_id = (int) get_queried_object_id();
        $thumb   = $post_id ? (int) get_post_thumbnail_id( $post_id ) : 0;
        if ( ! $thumb ) {
            return;
        }

        $src = wp_get_attachment_image_src( $thumb, 'full' );
        if ( ! $src ) {
            return;
        }

        $srcset = wp_get_attachment_image_srcset( $thumb, 'full' );
        $sizes  = '(max-width: 1024px) 100vw, 1200px';

        echo '<link rel="preload" as="image" href="' . esc_url( $src[0] ) . '"';
        if ( $srcset ) {
            echo ' imagesrcset="' . esc_attr( $srcset ) . '"';
        }
        echo ' imagesizes="' . esc_attr( $sizes ) . '" fetchpriority="high">' . "\n";
    }

    /** ItemList de paquetes para catálogo/archivos. */
    public static function item_list_schema( $posts ) {
        $items = [];
        $position = 0;

        foreach ( (array) $posts as $post ) {
            $post_id = is_object( $post ) ? (int) $post->ID : (int) $post;
            if ( ! $post_id || get_post_status( $post_id ) !== 'publish' ) {
                continue;
            }
            $position++;
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $position,
                'url'      => get_permalink( $post_id ),
                'name'     => self::plain_title( $post_id ),
            ];
            if ( $position >= 50 ) {
                break;
            }
        }

        return [
            '@type'           => 'ItemList',
            'numberOfItems'   => $position,
            'itemListElement' => $items,
        ];
    }

    /** Imprime un ItemList inline (para el shortcode de catálogo). */
    public static function print_item_list_schema( $posts, $name = '' ) {
        if ( ! self::schema_enabled() ) {
            return;
        }

        $list = self::item_list_schema( $posts );
        if ( empty( $list['itemListElement'] ) ) {
            return;
        }

        $page_url = get_permalink( (int) get_queried_object_id() );
        $node     = [
            '@type'      => 'CollectionPage',
            'name'       => $name !== '' ? $name : __( 'Catálogo de paquetes', 'travel-packages' ),
            'mainEntity' => $list,
        ];
        if ( $page_url ) {
            $node['url'] = $page_url;
            $node['@id'] = $page_url . '#tp-webpage';
        }

        self::print_json_ld_graph( [ $node ] );
    }

    /** CollectionPage + ItemList + canonical en archivos y taxonomías. */
    public static function render_collection_schema() {
        $is_tax     = is_tax( TP_Package_Data::TAX_DESTINO ) || is_tax( TP_Package_Data::TAX_TIPOLOGIA );
        $is_archive = is_post_type_archive( TP_Package_Data::POST_TYPE );

        if ( ! $is_tax && ! $is_archive ) {
            return;
        }

        $url  = '';
        $name = '';
        $desc = '';

        if ( $is_tax ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) ) {
                $link = get_term_link( $term );
                if ( ! is_wp_error( $link ) ) {
                    $url = $link;
                }
                $name = $term->taxonomy === TP_Package_Data::TAX_DESTINO
                    ? sprintf( __( 'Paquetes a %s', 'travel-packages' ), $term->name )
                    : sprintf( __( 'Paquetes de %s', 'travel-packages' ), $term->name );
                $desc = trim( wp_strip_all_tags( term_description( $term ) ) );
            }
        } else {
            $url  = get_post_type_archive_link( TP_Package_Data::POST_TYPE );
            $name = __( 'Paquetes de viaje', 'travel-packages' );
            $type = get_post_type_object( TP_Package_Data::POST_TYPE );
            if ( $type && ! empty( $type->description ) ) {
                $desc = trim( wp_strip_all_tags( $type->description ) );
            }
        }

        if ( ! $url ) {
            return;
        }

        if ( ! self::has_seo_plugin() && ! is_paged() ) {
            echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
        }

        if ( ! self::schema_enabled() ) {
            return;
        }

        $query = isset( $GLOBALS['wp_query'] ) ? $GLOBALS['wp_query'] : null;
        if ( ! $query || empty( $query->posts ) ) {
            return;
        }

        $page = [
            '@type'      => 'CollectionPage',
            '@id'        => $url . '#tp-webpage',
            'url'        => $url,
            'name'       => $name,
            'inLanguage' => self::in_language(),
            'mainEntity' => self::item_list_schema( $query->posts ),
        ];

        if ( $desc !== '' ) {
            $page['description'] = $desc;
        }
        if ( ! self::has_seo_plugin() ) {
            $page['isPartOf'] = [ '@id' => self::website_id() ];
        }

        self::print_json_ld_graph( [ $page ] );
    }

    /** Títulos de archivo/taxonomía cuando no hay plugin SEO. */
    public static function filter_title_parts( $parts ) {
        if ( self::has_seo_plugin() ) {
            return $parts;
        }

        if ( is_singular( TP_Package_Data::POST_TYPE ) ) {
            $seo_title = self::seo_title( (int) get_queried_object_id() );
            if ( $seo_title !== '' ) {
                $parts['title'] = $seo_title;
            }
            return $parts;
        }

        if ( is_tax( TP_Package_Data::TAX_DESTINO ) || is_tax( TP_Package_Data::TAX_TIPOLOGIA ) ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) ) {
                $parts['title'] = $term->taxonomy === TP_Package_Data::TAX_DESTINO
                    ? sprintf( __( 'Paquetes a %s', 'travel-packages' ), $term->name )
                    : sprintf( __( 'Paquetes de %s', 'travel-packages' ), $term->name );
            }
        } elseif ( is_post_type_archive( TP_Package_Data::POST_TYPE ) ) {
            $parts['title'] = __( 'Paquetes de viaje', 'travel-packages' );
        }

        return $parts;
    }

    /** Evita indexar búsquedas internas y variantes de consulta. */
    public static function filter_robots( $robots ) {
        if ( is_singular( TP_Package_Data::POST_TYPE ) ) {
            $robots['max-image-preview'] = 'large';
            if ( get_post_meta( (int) get_queried_object_id(), TP_Package_Data::META_SEO_NOINDEX, true ) === '1' ) {
                $robots['noindex'] = true;
                $robots['follow']  = true;
                unset( $robots['index'], $robots['all'] );
            }
            return $robots;
        }

        $search = isset( $_GET['tp_search'] ) && $_GET['tp_search'] !== '';
        if ( is_search() || $search || isset( $_GET['tp_inquiry'] ) || isset( $_GET['tp_inquiry_msg'] ) ) {
            $robots['noindex']  = true;
            $robots['follow']   = true;
            unset( $robots['index'], $robots['all'] );
        }

        if ( is_tax( TP_Package_Data::TAX_DESTINO ) || is_tax( TP_Package_Data::TAX_TIPOLOGIA ) || is_post_type_archive( TP_Package_Data::POST_TYPE ) ) {
            $robots['max-image-preview'] = 'large';
        }

        return $robots;
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
