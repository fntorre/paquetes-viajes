<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_PostType {

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_type' ] );
        add_action( 'init', [ __CLASS__, 'register_taxonomy' ] );
        add_action( 'init', [ __CLASS__, 'register_taxonomy_tipologia' ] );
        add_filter( 'manage_paquete_viaje_posts_columns',       [ __CLASS__, 'custom_columns' ] );
        add_action( 'manage_paquete_viaje_posts_custom_column', [ __CLASS__, 'custom_column_content' ], 10, 2 );
        add_filter( 'manage_edit-paquete_viaje_sortable_columns', [ __CLASS__, 'sortable_columns' ] );
    }

    public static function register_post_type() {
        $labels = [
            'name'               => __( 'Paquetes de Viaje', 'travel-packages' ),
            'singular_name'      => __( 'Paquete de Viaje',  'travel-packages' ),
            'add_new'            => __( 'Agregar Paquete',   'travel-packages' ),
            'add_new_item'       => __( 'Nuevo Paquete de Viaje', 'travel-packages' ),
            'edit_item'          => __( 'Editar Paquete',    'travel-packages' ),
            'new_item'           => __( 'Nuevo Paquete',     'travel-packages' ),
            'view_item'          => __( 'Ver Paquete',       'travel-packages' ),
            'search_items'       => __( 'Buscar Paquetes',   'travel-packages' ),
            'not_found'          => __( 'No se encontraron paquetes', 'travel-packages' ),
            'not_found_in_trash' => __( 'No hay paquetes en la papelera', 'travel-packages' ),
            'menu_name'          => __( 'Paquetes de Viaje', 'travel-packages' ),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'query_var'          => true,
            'rewrite'            => [ 'slug' => 'paquetes-de-viaje' ],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'menu_icon'          => 'dashicons-airplane',
            'supports'           => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'show_in_rest'       => true,
        ];

        register_post_type( 'paquete_viaje', $args );
    }

    public static function register_taxonomy() {
        $labels = [
            'name'              => __( 'Destinos', 'travel-packages' ),
            'singular_name'     => __( 'Destino',  'travel-packages' ),
            'search_items'      => __( 'Buscar Destinos', 'travel-packages' ),
            'all_items'         => __( 'Todos los Destinos', 'travel-packages' ),
            'edit_item'         => __( 'Editar Destino', 'travel-packages' ),
            'add_new_item'      => __( 'Agregar Destino', 'travel-packages' ),
            'menu_name'         => __( 'Destinos', 'travel-packages' ),
        ];

        register_taxonomy( 'destino', 'paquete_viaje', [
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'destino' ],
        ]);
    }

    public static function custom_columns( $columns ) {
        $new = [];
        foreach ( $columns as $key => $label ) {
            $new[ $key ] = $label;
            if ( $key === 'title' ) {
                $new['tp_image']     = __( 'Imagen', 'travel-packages' );
                $new['tp_price']     = __( 'Precio', 'travel-packages' );
                $new['tp_slider']    = __( 'Slider', 'travel-packages' );
                $new['tp_featured']  = __( 'Destacado', 'travel-packages' );
            }
        }
        return $new;
    }

    public static function custom_column_content( $column, $post_id ) {
        switch ( $column ) {
            case 'tp_image':
                if ( has_post_thumbnail( $post_id ) ) {
                    echo '<img src="' . esc_url( get_the_post_thumbnail_url( $post_id, 'thumbnail' ) ) . '" style="width:60px;height:45px;object-fit:cover;border-radius:4px;">';
                } else {
                    echo '<span style="color:#999;">—</span>';
                }
                break;
            case 'tp_price':
                $best = TP_Package_Data::get_best_price( $post_id );
                if ( $best ) {
                    echo '<strong style="color:#0073aa;">' . esc_html( $best['currency'] . ' ' . number_format( (float) $best['price'], 2, ',', '.' ) ) . '</strong>';
                } else {
                    echo '—';
                }
                break;
            case 'tp_slider':
                $val = get_post_meta( $post_id, '_tp_slider', true );
                echo $val ? '<span class="tp-badge tp-badge-yes">✓ Slider</span>' : '<span class="tp-badge tp-badge-no">—</span>';
                break;
            case 'tp_featured':
                $val = get_post_meta( $post_id, '_tp_featured', true );
                echo $val ? '<span class="tp-badge tp-badge-yes">⭐ Destacado</span>' : '<span class="tp-badge tp-badge-no">—</span>';
                break;
        }
    }

    public static function sortable_columns( $columns ) {
        $columns['tp_price'] = 'tp_price';
        return $columns;
    }

    public static function register_taxonomy_tipologia() {
        $labels = [
            'name'              => __( 'Tipologías', 'travel-packages' ),
            'singular_name'     => __( 'Tipología',  'travel-packages' ),
            'search_items'      => __( 'Buscar Tipologías', 'travel-packages' ),
            'all_items'         => __( 'Todas las Tipologías', 'travel-packages' ),
            'edit_item'         => __( 'Editar Tipología', 'travel-packages' ),
            'add_new_item'      => __( 'Agregar Tipología', 'travel-packages' ),
            'menu_name'         => __( 'Tipologías', 'travel-packages' ),
        ];

        register_taxonomy( 'tipologia', 'paquete_viaje', [
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'rewrite'           => [ 'slug' => 'tipologia' ],
        ]);
    }

    public static function flush_rules() {
        self::register_post_type();
        self::register_taxonomy();
        self::register_taxonomy_tipologia();
        flush_rewrite_rules();
    }
}
