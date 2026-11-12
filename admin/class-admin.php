<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * TP_Admin
 *
 * Menú, assets y páginas de renderizado del gestor de paquetes.
 * Los handlers de datos viven en TP_Admin_Handlers y la lógica de datos en TP_Package_Data.
 */
class TP_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_menu',            [ __CLASS__, 'remove_duplicate_dashboard_submenu' ], 99 );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_filter( 'admin_body_class',      [ __CLASS__, 'admin_body_class' ] );
        add_action( 'in_admin_header',       [ __CLASS__, 'suppress_foreign_notices' ], 0 );
    }

    private static function is_plugin_page() {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        return in_array( $page, [ 'tp-dashboard', 'tp-packages', 'tp-package-new', 'tp-calendar', 'tp-shortcodes', 'tp-reservations', 'tp-settings', 'tp-custom-fields' ], true );
    }

    private static function is_plugin_screen() {
        if ( self::is_plugin_page() ) {
            return true;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        return ( $screen && isset( $screen->post_type ) && $screen->post_type === 'paquete_viaje' );
    }

    public static function suppress_foreign_notices() {
        if ( ! self::is_plugin_screen() ) {
            return;
        }

        remove_all_actions( 'admin_notices' );
        remove_all_actions( 'all_admin_notices' );
        remove_all_actions( 'network_admin_notices' );
        remove_all_actions( 'user_admin_notices' );
    }

    /** Agrega una clase de body para aplicar el tema neomórfico solo en pantallas del plugin. */
    public static function admin_body_class( $classes ) {
        $page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        $pages  = [ 'tp-dashboard', 'tp-packages', 'tp-package-new', 'tp-calendar', 'tp-shortcodes', 'tp-reservations', 'tp-settings', 'tp-custom-fields' ];
        $is_tp  = in_array( $page, $pages, true );

        if ( ! $is_tp && $screen && isset( $screen->post_type ) && $screen->post_type === 'paquete_viaje' ) {
            $is_tp = true;
        }

        if ( $is_tp ) {
            $classes .= ' tp-admin-neu tp-admin-tureo';
        }

        if ( self::is_plugin_page() ) {
            $classes .= ' tp-admin-clean-notices';
        }

        return $classes;
    }

    public static function register_menu() {
        add_menu_page(
            TP_BRAND,
            TP_BRAND,
            'manage_options',
            'tp-dashboard',
            [ __CLASS__, 'render_dashboard' ],
            self::menu_icon(),
            20
        );

        // Submenu: package manager
        add_submenu_page(
            'tp-dashboard',
            __( 'Paquetes', 'travel-packages' ),
            __( 'Paquetes', 'travel-packages' ),
            'edit_posts',
            'tp-packages',
            [ __CLASS__, 'render_packages_page' ]
        );

            // Submenu: add new
            add_submenu_page(
                'tp-dashboard',
                __( 'Agregar Paquete', 'travel-packages' ),
                __( '+ Agregar Paquete', 'travel-packages' ),
                'edit_posts',
                'tp-package-new',
                [ __CLASS__, 'render_packages_page' ]
            );

            // Submenu: departures calendar
            add_submenu_page(
                'tp-dashboard',
                __( 'Calendario de Salidas', 'travel-packages' ),
                __( 'Calendario de Salidas', 'travel-packages' ),
                'edit_posts',
                'tp-calendar',
                [ __CLASS__, 'render_calendar_page' ]
            );

            // Submenu: destinations
            add_submenu_page(
                'tp-dashboard',
                __( 'Destinos', 'travel-packages' ),
                __( 'Destinos', 'travel-packages' ),
                'manage_categories',
                'edit-tags.php?taxonomy=destino&post_type=paquete_viaje'
            );

            // Submenu: tipologies
            add_submenu_page(
                'tp-dashboard',
                __( 'Tipologías', 'travel-packages' ),
                __( 'Tipologías', 'travel-packages' ),
                'manage_categories',
                'edit-tags.php?taxonomy=tipologia&post_type=paquete_viaje'
            );

            // Submenu: shortcodes helper
            add_submenu_page(
                'tp-dashboard',
                __( 'Shortcodes', 'travel-packages' ),
                __( 'Guía de Shortcodes', 'travel-packages' ),
                'edit_posts',
                'tp-shortcodes',
                [ __CLASS__, 'render_shortcodes_page' ]
            );
    }

    /** El acceso al dashboard ya existe en el elemento raíz del menú. */
    public static function remove_duplicate_dashboard_submenu() {
        remove_submenu_page( 'tp-dashboard', 'tp-dashboard' );
    }

    public static function enqueue( $hook ) {
        $screen = get_current_screen();
        $page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $load_admin_assets = false;

        if ( strpos( $hook, 'tp-' ) !== false || ( $screen && $screen->post_type === 'paquete_viaje' ) ) {
            $load_admin_assets = true;
        }

        if ( $load_admin_assets ) {
            $css_file = TP_PLUGIN_DIR . 'admin/css/admin.css';
            $css_ver  = file_exists( $css_file ) ? filemtime( $css_file ) : TP_VERSION;
            wp_enqueue_style(  'tp-admin', TP_PLUGIN_URL . 'admin/css/admin.css', [], $css_ver );
            wp_enqueue_script( 'tp-admin', TP_PLUGIN_URL . 'admin/js/admin.js',   [ 'jquery' ], TP_VERSION, true );
            wp_localize_script( 'tp-admin', 'TP_ADMIN', [
                'nonce'                => wp_create_nonce('tp_admin_nonce'),
                'delete_nonce'         => wp_create_nonce('tp_delete_package'),
                'ajax_url'             => admin_url('admin-ajax.php'),
                'reservations_enabled' => ( class_exists( 'TP_Reservations' ) && TP_Reservations::is_enabled() ) ? '1' : '0',
                'site_name'            => get_bloginfo( 'name' ),
            ]);
        }

        if ( $page === 'tp-package-new' ) {
            wp_enqueue_editor();
            wp_enqueue_script( 'jquery-ui-datepicker' );
            wp_enqueue_style( 'jquery-ui', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css' );
            wp_enqueue_media();
            wp_enqueue_script( 'tp-admin-packages', TP_PLUGIN_URL . 'admin/js/packages.js', [ 'jquery', 'jquery-ui-datepicker', 'tp-admin' ], TP_VERSION . '.' . gmdate('YmdHis'), true );
        } elseif ( $page === 'tp-packages' ) {
            wp_enqueue_script( 'tp-admin-packages', TP_PLUGIN_URL . 'admin/js/packages.js', [ 'jquery', 'tp-admin' ], TP_VERSION . '.' . gmdate('YmdHis'), true );
        }
    }

    /** Ícono del menú con la marca Tureo en verde sobre el menú oscuro de WordPress. */
    private static function menu_icon() {
        $file = TP_PLUGIN_DIR . 'admin/img/tureo-mark.svg';

        if ( file_exists( $file ) ) {
            $svg = @file_get_contents( $file );
            if ( $svg ) {
                $svg = str_replace( '#233d32', '#c9ef74', $svg );
                return 'data:image/svg+xml;base64,' . base64_encode( $svg );
            }
        }

        return 'dashicons-airplane';
    }

    /** Marca Tureo reutilizable en los encabezados del admin. */
    public static function render_brand() {
        $logo = TP_PLUGIN_URL . 'admin/img/tureo-mark.svg';
        ?>
        <div class="tp-tureo-brand" aria-label="<?php echo esc_attr( TP_BRAND ); ?>">
            <span class="tp-tureo-mark" aria-hidden="true">
                <img src="<?php echo esc_url( $logo ); ?>" alt="">
            </span>
            <span class="tp-tureo-word"><?php echo esc_html( TP_BRAND ); ?><span class="tp-tureo-dot">.</span></span>
        </div>
        <?php
    }

    /** Badge de versión/build para identificar la versión en prueba en el admin. */
    public static function render_version_badge() {
        ?>
        <span class="tp-version-badge" title="<?php echo esc_attr( sprintf( __( 'Versión de %s', 'travel-packages' ), TP_BRAND ) ); ?>">
            <span class="dashicons dashicons-superhero"></span>
            <?php echo esc_html( TP_BRAND . ' v' . TP_VERSION ); ?>
        </span>
        <?php
    }

    public static function render_dashboard() {
        // Stats
        $total    = wp_count_posts('paquete_viaje');
        $published = isset($total->publish) ? $total->publish : 0;

        $slider_q = new WP_Query(['post_type'=>'paquete_viaje','post_status'=>'publish','posts_per_page'=>-1,'meta_query'=>[['key'=>'_tp_slider','value'=>'1']],'fields'=>'ids']);
        $featured_q = new WP_Query(['post_type'=>'paquete_viaje','post_status'=>'publish','posts_per_page'=>-1,'meta_query'=>[['key'=>'_tp_featured','value'=>'1']],'fields'=>'ids']);

        $slider_count   = $slider_q->found_posts;
        $featured_count = $featured_q->found_posts;

        // Recent packages
        $recent = new WP_Query(['post_type'=>'paquete_viaje','post_status'=>'publish','posts_per_page'=>5,'orderby'=>'date','order'=>'DESC']);
        ?>
        <div class="wrap tp-admin-wrap">
            <div class="tp-admin-header">
                <div class="tp-header-left tp-tureo-header-left">
                    <?php self::render_brand(); ?>
                    <div class="tp-tureo-heading">
                        <h1><?php _e( 'Panel de control', 'travel-packages' ); ?></h1>
                        <p><?php echo esc_html( TP_BRAND_TAGLINE ); ?></p>
                    </div>
                    <?php self::render_version_badge(); ?>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=tp-package-new')); ?>" class="tp-btn-primary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Nuevo Paquete', 'travel-packages'); ?>
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="tp-stats-grid">
                <div class="tp-stat-card">
                    <div class="tp-stat-icon">✈</div>
                    <div class="tp-stat-info">
                        <span class="tp-stat-number"><?php echo $published; ?></span>
                        <span class="tp-stat-label"><?php _e('Paquetes Publicados', 'travel-packages'); ?></span>
                    </div>
                </div>
                <div class="tp-stat-card">
                    <div class="tp-stat-icon">🖼</div>
                    <div class="tp-stat-info">
                        <span class="tp-stat-number"><?php echo $slider_count; ?></span>
                        <span class="tp-stat-label"><?php _e('En el Slider', 'travel-packages'); ?></span>
                    </div>
                </div>
                <div class="tp-stat-card">
                    <div class="tp-stat-icon">⭐</div>
                    <div class="tp-stat-info">
                        <span class="tp-stat-number"><?php echo $featured_count; ?></span>
                        <span class="tp-stat-label"><?php _e('Destacados', 'travel-packages'); ?></span>
                    </div>
                </div>
            </div>

            <div class="tp-dashboard-grid">
                <!-- Recent Packages -->
                <div class="tp-dashboard-card">
                    <div class="tp-card-header">
                        <h2><?php _e('Últimos Paquetes', 'travel-packages'); ?></h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=tp-packages')); ?>"><?php _e('Ver todos →', 'travel-packages'); ?></a>
                    </div>
                    <table class="tp-recent-table">
                        <thead>
                            <tr>
                                <th><?php _e('Paquete', 'travel-packages'); ?></th>
                                <th><?php _e('Precio', 'travel-packages'); ?></th>
                                <th><?php _e('Slider', 'travel-packages'); ?></th>
                                <th><?php _e('Destacado', 'travel-packages'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( $recent->have_posts() ) : while ( $recent->have_posts() ) : $recent->the_post();
                                $best_p   = TP_Package_Data::get_best_price( get_the_ID() );
                                $slider   = get_post_meta( get_the_ID(), '_tp_slider', true );
                                $featured = get_post_meta( get_the_ID(), '_tp_featured', true );
                            ?>
                            <tr>
                                <td>
                                    <div class="tp-table-title">
                                        <?php if ( has_post_thumbnail() ) : ?>
                                        <img src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(),'thumbnail')); ?>" class="tp-table-thumb">
                                        <?php endif; ?>
                                        <strong><?php the_title(); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo $best_p ? esc_html(TP_Package_Data::currency_symbol($best_p['currency']).' '.number_format((float)$best_p['price'],0,',','.')) : '—'; ?></td>
                                <td>
                                    <button class="tp-quick-toggle <?php echo $slider=='1' ? 'active' : ''; ?>" data-post="<?php the_ID(); ?>" data-action="tp_toggle_slider" title="<?php esc_attr_e('Toggle slider','travel-packages'); ?>">
                                        <?php echo $slider=='1' ? '✓' : '○'; ?>
                                    </button>
                                </td>
                                <td>
                                    <button class="tp-quick-toggle <?php echo $featured=='1' ? 'active' : ''; ?>" data-post="<?php the_ID(); ?>" data-action="tp_toggle_featured" title="<?php esc_attr_e('Toggle destacado','travel-packages'); ?>">
                                        <?php echo $featured=='1' ? '⭐' : '☆'; ?>
                                    </button>
                                </td>
                                <td class="tp-table-actions">
                                    <a href="<?php echo esc_url( TP_Package_Data::get_view_url( get_the_ID() ) ); ?>" class="tp-table-link" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Ver paquete en una pestaña nueva', 'travel-packages' ); ?>"><?php _e('Ver', 'travel-packages'); ?></a>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=tp-package-new&package_id=' . get_the_ID())); ?>" class="tp-table-link"><?php _e('Editar', 'travel-packages'); ?></a>
                                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=tp-packages&tp_action=delete_package&tp_package_id=' . get_the_ID()), 'tp_delete_package', 'tp_delete_nonce')); ?>" class="tp-table-link tp-table-delete" data-confirm="<?php esc_attr_e('¿Estás seguro de eliminar este paquete?', 'travel-packages'); ?>"><?php _e('Eliminar', 'travel-packages'); ?></a>
                                </td>
                            </tr>
                            <?php endwhile; wp_reset_postdata();
                            else : ?>
                            <tr><td colspan="5" class="tp-empty"><?php _e('Aún no hay paquetes. ¡Creá el primero!', 'travel-packages'); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Shortcodes Quick Reference -->
                <div class="tp-dashboard-card">
                    <div class="tp-card-header">
                        <h2><?php _e('Shortcodes Rápidos', 'travel-packages'); ?></h2>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=tp-shortcodes')); ?>"><?php _e('Ver guía completa →', 'travel-packages'); ?></a>
                    </div>
                    <div class="tp-shortcode-list">
                        <div class="tp-shortcode-item">
                            <code>[tp_slider]</code>
                            <p><?php _e('Slider de paquetes marcados para home.', 'travel-packages'); ?></p>
                        </div>
                        <div class="tp-shortcode-item">
                            <code>[tp_grid destacados="true"]</code>
                            <p><?php _e('Grilla con paquetes destacados.', 'travel-packages'); ?></p>
                        </div>
                        <div class="tp-shortcode-item">
                            <code>[tp_grid limit="6" columns="3"]</code>
                            <p><?php _e('Grilla con últimos 6 paquetes en 3 columnas.', 'travel-packages'); ?></p>
                        </div>
                        <div class="tp-shortcode-item">
                            <code>[tp_paquete id="123"]</code>
                            <p><?php _e('Paquete individual embebido.', 'travel-packages'); ?></p>
                        </div>
                        <div class="tp-shortcode-item">
                            <code>[tp_buscador]</code>
                            <p><?php _e('Buscador de paquetes para cualquier parte del template.', 'travel-packages'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /** Construye los argumentos del listado de paquetes según los filtros de la URL. */
    private static function packages_query_args() {
        $paged     = isset( $_GET['tp_paged'] ) ? max( 1, intval( $_GET['tp_paged'] ) ) : 1;
        $orderby   = isset( $_GET['tp_orderby'] ) ? sanitize_key( $_GET['tp_orderby'] ) : '';
        $order     = isset( $_GET['tp_order'] ) && $_GET['tp_order'] === 'ASC' ? 'ASC' : 'DESC';

        $args = [
            'post_type'      => 'paquete_viaje',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => 40,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ( $orderby === 'meta_value_num' ) {
            $args['orderby']  = 'meta_value_num';
            $args['meta_key'] = '_tp_price';
            $args['order']    = $order;
        } elseif ( $orderby ) {
            $args['orderby'] = $orderby;
            $args['order']   = $order;
        }

        $tax_query = [];
        foreach ( [ 'tp_filtro_destino' => 'destino', 'tp_filtro_tipologia' => 'tipologia' ] as $get_key => $taxonomy ) {
            $value = isset( $_GET[ $get_key ] ) ? sanitize_text_field( $_GET[ $get_key ] ) : '';
            if ( $value ) {
                $tax_query[] = [ 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $value ];
            }
        }
        if ( $tax_query ) {
            $args['tax_query'] = $tax_query;
        }

        $filtro_search = isset( $_GET['tp_filtro_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tp_filtro_search'] ) ) : '';
        if ( $filtro_search ) {
            $args['s'] = $filtro_search;
        }

        $meta_query = [];
        $precio_min = isset( $_GET['tp_filtro_precio_min'] ) ? floatval( $_GET['tp_filtro_precio_min'] ) : 0;
        $precio_max = isset( $_GET['tp_filtro_precio_max'] ) ? floatval( $_GET['tp_filtro_precio_max'] ) : 0;
        $filtro_slider    = isset( $_GET['tp_filtro_slider'] ) ? sanitize_text_field( $_GET['tp_filtro_slider'] ) : '';
        $filtro_featured  = isset( $_GET['tp_filtro_featured'] ) ? sanitize_text_field( $_GET['tp_filtro_featured'] ) : '';

        if ( $precio_min > 0 ) {
            $meta_query[] = [ 'key' => '_tp_price', 'value' => $precio_min, 'type' => 'NUMERIC', 'compare' => '>=' ];
        }
        if ( $precio_max > 0 ) {
            $meta_query[] = [ 'key' => '_tp_price', 'value' => $precio_max, 'type' => 'NUMERIC', 'compare' => '<=' ];
        }
        foreach ( [ '_tp_slider' => $filtro_slider, '_tp_featured' => $filtro_featured ] as $meta_key => $value ) {
            if ( $value === 'yes' ) {
                $meta_query[] = [ 'key' => $meta_key, 'value' => '1' ];
            } elseif ( $value === 'no' ) {
                $meta_query[] = [ 'key' => $meta_key, 'value' => '0' ];
            }
        }

        $filtro_tipo_viaje_q = isset( $_GET['tp_filtro_tipo_viaje'] ) ? sanitize_text_field( $_GET['tp_filtro_tipo_viaje'] ) : '';
        if ( in_array( $filtro_tipo_viaje_q, [ 'nacional', 'internacional' ], true ) ) {
            $meta_query[] = [ 'key' => TP_Package_Data::META_TIPO_VIAJE, 'value' => $filtro_tipo_viaje_q ];
        }
        if ( $meta_query ) {
            $args['meta_query'] = $meta_query;
        }

        return $args;
    }

    /** Renderiza una tarjeta de paquete con acciones directas. */
    private static function render_package_card( $package ) {
        $admin_data   = TP_Package_Data::get_admin_data( $package->ID );
        $view_url     = ! empty( $admin_data['view_url'] ) ? $admin_data['view_url'] : get_permalink( $package->ID );
        $edit_url     = admin_url( 'admin.php?page=tp-package-new&package_id=' . $package->ID );
        $best_price   = TP_Package_Data::get_best_price( $package->ID );
        $status_label = $package->post_status === 'publish' ? __( 'Publicado', 'travel-packages' ) : __( 'Borrador', 'travel-packages' );
        $thumb        = get_the_post_thumbnail_url( $package->ID, 'thumbnail' );
        $destinos     = TP_Package_Data::get_term_names( $package->ID, TP_Package_Data::TAX_DESTINO );
        $tipologias   = TP_Package_Data::get_term_names( $package->ID, TP_Package_Data::TAX_TIPOLOGIA );
        $tipo_viaje   = get_post_meta( $package->ID, TP_Package_Data::META_TIPO_VIAJE, true );
        ?>
        <div class="tp-package-card">
            <div class="tp-package-card-thumb">
                <?php if ( $thumb ) : ?>
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $package->post_title ); ?>">
                <?php else : ?>
                <span>🧳</span>
                <?php endif; ?>
            </div>
            <div class="tp-package-card-body">
                <a class="tp-card-edit-link" href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $package->post_title ); ?></a>
                <span class="tp-package-card-meta"><?php echo esc_html( $status_label ); ?> · <?php echo $best_price ? esc_html( TP_Package_Data::currency_symbol( $best_price['currency'] ) . ' ' . number_format( (float) $best_price['price'], 0, ',', '.' ) ) : '—'; ?></span>
                <span class="tp-package-card-tags"><?php echo esc_html( implode( ', ', $destinos ) ); ?></span>
                <span class="tp-package-card-tags tp-tags-tipologia"><?php echo esc_html( implode( ', ', $tipologias ) ); ?></span>
                <?php if ( $tipo_viaje ) : ?>
                <span class="tp-package-card-tags tp-tags-tipo-viaje"><?php echo esc_html( $tipo_viaje === 'nacional' ? 'Nacional' : 'Internacional' ); ?></span>
                <?php endif; ?>
                <span class="tp-package-card-actions">
                    <a href="<?php echo esc_url( $edit_url ); ?>" class="tp-card-edit-action"><?php _e( 'Editar', 'travel-packages' ); ?></a>
                    <a href="<?php echo esc_url( $view_url ); ?>" class="tp-card-view-link" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Ver paquete en una pestaña nueva', 'travel-packages' ); ?>" onclick="event.stopPropagation();"><?php _e( 'Ver paquete', 'travel-packages' ); ?></a>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_duplicate_package&tp_package_id=' . $package->ID ), 'tp_duplicate_package', 'tp_duplicate_nonce' ) ); ?>" class="tp-card-dup-link" title="<?php esc_attr_e( 'Duplicar paquete', 'travel-packages' ); ?>" onclick="event.stopPropagation();">📋 <?php _e( 'Duplicar', 'travel-packages' ); ?></a>
                </span>
            </div>
        </div>
        <?php
    }

    public static function render_packages_page() {
        $page          = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'tp-packages';
        $is_editor_page = $page === 'tp-package-new';
        if ( ! $is_editor_page && isset( $_GET['package_id'] ) ) {
            $legacy_package_id = absint( $_GET['package_id'] );
            wp_safe_redirect( admin_url( 'admin.php?page=tp-package-new&package_id=' . $legacy_package_id ) );
            exit;
        }
        $package_query = $is_editor_page ? null : new WP_Query( self::packages_query_args() );
        $packages      = $package_query ? $package_query->posts : [];
        $total_pages   = $package_query ? $package_query->max_num_pages : 0;
        $paged         = isset( $_GET['tp_paged'] ) ? max( 1, intval( $_GET['tp_paged'] ) ) : 1;

        $destinos   = get_terms( [ 'taxonomy' => 'destino', 'hide_empty' => false ] );
        $tipologias = get_terms( [ 'taxonomy' => 'tipologia', 'hide_empty' => false ] );
        $status     = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $base_url   = admin_url( 'admin.php?page=tp-packages' );

        // Paquete a precargar en el formulario (viene de la URL tras guardar o editar).
        $selected_package_data = [];
        if ( isset( $_GET['package_id'] ) ) {
            $selected_package_data = TP_Package_Data::get_admin_data( intval( $_GET['package_id'] ) );
        }

        $restore_form_data = [];
        if ( $is_editor_page && isset( $_GET['tp_form_restore'] ) ) {
            $restore_token = sanitize_key( wp_unslash( $_GET['tp_form_restore'] ) );
            $restore_key   = 'tp_pkg_form_' . get_current_user_id() . '_' . $restore_token;
            $stored_data   = get_transient( $restore_key );
            if ( is_array( $stored_data ) ) {
                $restore_form_data = $stored_data;
                delete_transient( $restore_key );
            }
        }

        // Filtros activos (para mostrar/ocultar el panel).
        $filtro_destino    = isset( $_GET['tp_filtro_destino'] ) ? sanitize_text_field( $_GET['tp_filtro_destino'] ) : '';
        $filtro_tipologia  = isset( $_GET['tp_filtro_tipologia'] ) ? sanitize_text_field( $_GET['tp_filtro_tipologia'] ) : '';
        $filtro_precio_min = isset( $_GET['tp_filtro_precio_min'] ) ? floatval( $_GET['tp_filtro_precio_min'] ) : 0;
        $filtro_precio_max = isset( $_GET['tp_filtro_precio_max'] ) ? floatval( $_GET['tp_filtro_precio_max'] ) : 0;
        $filtro_slider     = isset( $_GET['tp_filtro_slider'] ) ? sanitize_text_field( $_GET['tp_filtro_slider'] ) : '';
        $filtro_featured   = isset( $_GET['tp_filtro_featured'] ) ? sanitize_text_field( $_GET['tp_filtro_featured'] ) : '';
        $filtro_tipo_viaje = isset( $_GET['tp_filtro_tipo_viaje'] ) ? sanitize_text_field( $_GET['tp_filtro_tipo_viaje'] ) : '';
        $filtro_search     = isset( $_GET['tp_filtro_search'] ) ? sanitize_text_field( wp_unslash( $_GET['tp_filtro_search'] ) ) : '';
        $orderby_url       = isset( $_GET['tp_orderby'] ) ? sanitize_key( $_GET['tp_orderby'] ) : '';
        $order_url         = isset( $_GET['tp_order'] ) && $_GET['tp_order'] === 'ASC' ? 'ASC' : 'DESC';

        $has_active_filters = $filtro_destino || $filtro_tipologia || $filtro_tipo_viaje || $filtro_precio_min > 0 || $filtro_precio_max > 0 || $filtro_slider || $filtro_featured || $filtro_search || $orderby_url;
        ?>
        <div class="wrap tp-admin-wrap tp-packages-wrap">
            <div class="tp-admin-header">
                <div class="tp-header-left tp-tureo-header-left">
                    <?php self::render_brand(); ?>
                    <div class="tp-tureo-heading">
                        <h1><?php echo $is_editor_page ? ( ! empty( $selected_package_data['ID'] ) ? esc_html__( 'Editar paquete', 'travel-packages' ) : esc_html__( 'Nuevo paquete', 'travel-packages' ) ) : esc_html__( 'Gestión de Paquetes', 'travel-packages' ); ?></h1>
                        <p><?php echo $is_editor_page ? esc_html__( 'Completá la información del viaje y guardá los cambios.', 'travel-packages' ) : esc_html__( 'Administrá tus paquetes, salidas y contenido desde un único lugar.', 'travel-packages' ); ?></p>
                    </div>
                    <?php self::render_version_badge(); ?>
                </div>
                <?php if ( $is_editor_page ) : ?>
                <a href="<?php echo esc_url( $base_url ); ?>" class="tp-btn tp-btn-secondary tp-back-to-packages">
                    <span class="dashicons dashicons-arrow-left-alt2"></span> <?php _e( 'Volver al listado', 'travel-packages' ); ?>
                </a>
                <?php else : ?>
                <a id="tp-new-package" href="<?php echo esc_url( admin_url( 'admin.php?page=tp-package-new' ) ); ?>" class="tp-btn-primary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Nuevo paquete', 'travel-packages'); ?>
                </a>
                <?php endif; ?>
            </div>

            <?php if ( $status === 'success' && $is_editor_page ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php _e('Paquete guardado correctamente.', 'travel-packages'); ?></p></div>
            <?php elseif ( $status === 'error' && $is_editor_page ) : ?>
            <div class="notice notice-error" role="alert"><p><?php _e('No se pudo guardar el paquete. Revisá los campos indicados y volvé a intentarlo.', 'travel-packages'); ?></p></div>
            <?php elseif ( $status === 'success' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php _e('Paquete guardado correctamente.', 'travel-packages'); ?></p></div>
            <?php elseif ( $status === 'deleted' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php _e('Paquete eliminado correctamente.', 'travel-packages'); ?></p></div>
            <?php elseif ( $status === 'duplicated' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php _e('Paquete duplicado correctamente.', 'travel-packages'); ?></p></div>
            <?php elseif ( $status === 'exported' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php _e('Paquetes exportados correctamente.', 'travel-packages'); ?></p></div>
            <?php elseif ( $status === 'imported' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php _e('Paquetes importados correctamente.', 'travel-packages'); ?></p></div>
            <?php endif; ?>

            <div class="tp-packages-grid <?php echo $is_editor_page ? 'is-editor-only' : 'is-list-only'; ?>">
                <?php if ( ! $is_editor_page ) : ?>
                <div class="tp-packages-list">
                    <div class="tp-card-header tp-filters-header">
                        <h2><?php _e('Listado de paquetes', 'travel-packages'); ?></h2>
                        <div class="tp-filters-bar">
                            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="tp-package-search-form">
                                <input type="hidden" name="page" value="tp-packages">
                                <input type="hidden" name="tp_filtro_destino" value="<?php echo esc_attr( $filtro_destino ); ?>">
                                <input type="hidden" name="tp_filtro_tipologia" value="<?php echo esc_attr( $filtro_tipologia ); ?>">
                                <input type="hidden" name="tp_filtro_tipo_viaje" value="<?php echo esc_attr( $filtro_tipo_viaje ); ?>">
                                <input type="hidden" name="tp_filtro_precio_min" value="<?php echo esc_attr( $filtro_precio_min ?: '' ); ?>">
                                <input type="hidden" name="tp_filtro_precio_max" value="<?php echo esc_attr( $filtro_precio_max ?: '' ); ?>">
                                <input type="hidden" name="tp_filtro_slider" value="<?php echo esc_attr( $filtro_slider ); ?>">
                                <input type="hidden" name="tp_filtro_featured" value="<?php echo esc_attr( $filtro_featured ); ?>">
                                <input type="hidden" name="tp_orderby" value="<?php echo esc_attr( $orderby_url ); ?>">
                                <input type="hidden" name="tp_order" value="<?php echo esc_attr( $order_url ); ?>">
                                <input type="search" id="tp-package-search" name="tp_filtro_search" class="tp-package-search" value="<?php echo esc_attr( $filtro_search ); ?>" placeholder="<?php esc_attr_e('Buscar paquetes por nombre...', 'travel-packages'); ?>">
                                <button type="submit" class="button button-primary"><?php _e( 'Buscar', 'travel-packages' ); ?></button>
                            </form>
                            <button type="button" id="tp-toggle-filters" class="button tp-bar-btn <?php if ( $has_active_filters ) echo 'button-primary'; ?>">
                                <span class="dashicons dashicons-filter"></span> <?php _e( 'Filtros', 'travel-packages' ); ?>
                            </button>
                            <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=tp_export_packages' ) . '&tp_export_nonce=' . wp_create_nonce( 'tp_export_packages' ) ); ?>" class="button tp-bar-btn">
                                <span class="dashicons dashicons-download"></span> <?php _e( 'Exportar', 'travel-packages' ); ?>
                            </a>
                            <button type="button" id="tp-import-btn" class="button tp-bar-btn">
                                <span class="dashicons dashicons-upload"></span> <?php _e( 'Importar', 'travel-packages' ); ?>
                            </button>
                            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="tp-import-form" style="display:none;">
                                <input type="hidden" name="action" value="tp_import_packages">
                                <?php wp_nonce_field( 'tp_import_packages', 'tp_import_nonce' ); ?>
                                <input type="file" name="tp_import_file" accept=".json" required>
                                <button type="submit" class="button button-primary"><?php _e( 'Subir e importar', 'travel-packages' ); ?></button>
                            </form>
                        </div>
                    </div>

                    <div class="tp-filters-form" style="display:<?php echo $has_active_filters ? 'block' : 'none'; ?>;">
                        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="tp-filters-inline">
                            <input type="hidden" name="page" value="tp-packages">
                            <input type="hidden" name="tp_filtro_search" value="<?php echo esc_attr( $filtro_search ); ?>">

                            <div class="tp-filter-field">
                                <label><?php _e( 'Destino', 'travel-packages' ); ?></label>
                                <select name="tp_filtro_destino">
                                    <option value=""><?php _e( 'Todos los destinos', 'travel-packages' ); ?></option>
                                    <?php foreach ( $destinos as $destino ) : ?>
                                    <option value="<?php echo esc_attr( $destino->slug ); ?>" <?php selected( $filtro_destino, $destino->slug ); ?>><?php echo esc_html( $destino->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="tp-filter-field">
                                <label><?php _e( 'Tipología', 'travel-packages' ); ?></label>
                                <select name="tp_filtro_tipologia">
                                    <option value=""><?php _e( 'Todas las tipologías', 'travel-packages' ); ?></option>
                                    <?php foreach ( $tipologias as $tipologia ) : ?>
                                    <option value="<?php echo esc_attr( $tipologia->slug ); ?>" <?php selected( $filtro_tipologia, $tipologia->slug ); ?>><?php echo esc_html( $tipologia->name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="tp-filter-field">
                                <label><?php _e( 'Tipo de viaje', 'travel-packages' ); ?></label>
                                <select name="tp_filtro_tipo_viaje">
                                    <option value=""><?php _e( 'Todos', 'travel-packages' ); ?></option>
                                    <option value="nacional" <?php selected( $filtro_tipo_viaje, 'nacional' ); ?>><?php _e( 'Nacional', 'travel-packages' ); ?></option>
                                    <option value="internacional" <?php selected( $filtro_tipo_viaje, 'internacional' ); ?>><?php _e( 'Internacional', 'travel-packages' ); ?></option>
                                </select>
                            </div>

                            <div class="tp-filter-field">
                                <label><?php _e( 'Precio min', 'travel-packages' ); ?></label>
                                <input type="number" name="tp_filtro_precio_min" placeholder="0" value="<?php echo $filtro_precio_min ?: ''; ?>" min="0" step="1">
                            </div>

                            <div class="tp-filter-field">
                                <label><?php _e( 'Precio max', 'travel-packages' ); ?></label>
                                <input type="number" name="tp_filtro_precio_max" placeholder="0" value="<?php echo $filtro_precio_max ?: ''; ?>" min="0" step="1">
                            </div>

                            <div class="tp-filter-field">
                                <label><?php _e( 'Slider', 'travel-packages' ); ?></label>
                                <select name="tp_filtro_slider">
                                    <option value=""><?php _e( 'Todos', 'travel-packages' ); ?></option>
                                    <option value="yes" <?php selected( $filtro_slider, 'yes' ); ?>><?php _e( 'Sí', 'travel-packages' ); ?></option>
                                    <option value="no" <?php selected( $filtro_slider, 'no' ); ?>><?php _e( 'No', 'travel-packages' ); ?></option>
                                </select>
                            </div>

                            <div class="tp-filter-field">
                                <label><?php _e( 'Destacado', 'travel-packages' ); ?></label>
                                <select name="tp_filtro_featured">
                                    <option value=""><?php _e( 'Todos', 'travel-packages' ); ?></option>
                                    <option value="yes" <?php selected( $filtro_featured, 'yes' ); ?>><?php _e( 'Sí', 'travel-packages' ); ?></option>
                                    <option value="no" <?php selected( $filtro_featured, 'no' ); ?>><?php _e( 'No', 'travel-packages' ); ?></option>
                                </select>
                            </div>

                            <div class="tp-filter-field">
                                <label><?php _e( 'Ordenar por', 'travel-packages' ); ?></label>
                                <select name="tp_orderby">
                                    <option value="date" <?php selected( $orderby_url ?: 'date', 'date' ); ?>><?php _e( 'Fecha', 'travel-packages' ); ?></option>
                                    <option value="title" <?php selected( $orderby_url, 'title' ); ?>><?php _e( 'Título', 'travel-packages' ); ?></option>
                                    <option value="meta_value_num" <?php selected( $orderby_url, 'meta_value_num' ); ?>><?php _e( 'Precio', 'travel-packages' ); ?></option>
                                </select>
                            </div>

                            <div class="tp-filter-field">
                                <label><?php _e( 'Dirección', 'travel-packages' ); ?></label>
                                <select name="tp_order">
                                    <option value="DESC" <?php selected( $order_url, 'DESC' ); ?>>DESC</option>
                                    <option value="ASC" <?php selected( $order_url, 'ASC' ); ?>>ASC</option>
                                </select>
                            </div>

                            <div class="tp-filter-field tp-filter-actions">
                                <button type="submit" class="button button-primary"><?php _e( 'Aplicar filtros', 'travel-packages' ); ?></button>
                                <a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php _e( 'Limpiar', 'travel-packages' ); ?></a>
                            </div>
                        </form>
                    </div>

                    <div class="tp-package-cards">
                        <?php if ( empty( $packages ) ) : ?>
                        <div class="tp-empty-state">
                            <span class="dashicons dashicons-<?php echo $has_active_filters ? 'search' : 'palmtree'; ?>"></span>
                            <?php if ( $has_active_filters ) : ?>
                            <strong><?php _e( 'No encontramos paquetes', 'travel-packages' ); ?></strong>
                            <p><?php _e( 'Probá con otra búsqueda o ajustá los filtros.', 'travel-packages' ); ?></p>
                            <a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php _e( 'Limpiar búsqueda y filtros', 'travel-packages' ); ?></a>
                            <?php else : ?>
                            <strong><?php _e( 'Todavía no hay paquetes', 'travel-packages' ); ?></strong>
                            <p><?php _e( 'Creá tu primer paquete para empezar a armar viajes inolvidables.', 'travel-packages' ); ?></p>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-package-new' ) ); ?>" id="tp-new-package-empty" class="tp-btn-primary">
                                <span class="dashicons dashicons-plus-alt"></span> <?php _e( 'Crear paquete', 'travel-packages' ); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                        <?php else : ?>
                            <?php foreach ( $packages as $package ) :
                                self::render_package_card( $package );
                            endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ( $total_pages > 1 ) : ?>
                    <div class="tp-pagination">
                        <?php
                        $pagination_args = [
                            'tp_filtro_search'     => $filtro_search,
                            'tp_filtro_destino'    => $filtro_destino,
                            'tp_filtro_tipologia'  => $filtro_tipologia,
                            'tp_filtro_tipo_viaje' => $filtro_tipo_viaje,
                            'tp_filtro_precio_min' => $filtro_precio_min,
                            'tp_filtro_precio_max' => $filtro_precio_max,
                            'tp_filtro_slider'     => $filtro_slider,
                            'tp_filtro_featured'   => $filtro_featured,
                            'tp_orderby'           => $orderby_url,
                            'tp_order'             => $order_url,
                        ];
                        $pagination_base = add_query_arg( $pagination_args, $base_url );
                        $page_links = paginate_links( [
                            'base'      => add_query_arg( 'tp_paged', '%#%', $pagination_base ),
                            'format'    => '',
                            'current'   => $paged,
                            'total'     => $total_pages,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                        ] );
                        if ( $page_links ) echo $page_links;
                        ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ( $is_editor_page ) : ?>
                <div class="tp-package-form">
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="tp-package-form">
                        <input type="hidden" name="action" value="tp_save_package">
                        <input type="hidden" name="tp_package_id" id="tp_package_id" value="0">
                        <input type="hidden" id="tp-selected-package-data" value="<?php echo esc_attr( wp_json_encode( $selected_package_data ) ); ?>">
                        <input type="hidden" id="tp-invalid-form-data" value="<?php echo esc_attr( wp_json_encode( $restore_form_data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) ); ?>">
                        <?php wp_nonce_field( 'tp_package_save', 'tp_package_nonce' ); ?>

                        <div class="tp-form-heading">
                            <h2 id="tp-form-title"><?php _e('Nuevo paquete', 'travel-packages'); ?></h2>
                            <p><?php _e('Completá las secciones de abajo. Las opciones avanzadas están plegadas para mantener el formulario ordenado.', 'travel-packages'); ?></p>
                            <a href="#" id="tp-view-package" class="tp-form-view-link" target="_blank" rel="noopener" style="display:none;">
                                <span class="dashicons dashicons-external"></span> <?php _e('Ver paquete', 'travel-packages'); ?>
                            </a>
                        </div>

                        <nav class="tp-form-nav" aria-label="<?php esc_attr_e('Secciones del paquete', 'travel-packages'); ?>">
                            <a href="#tp-section-content"><?php _e('Contenido', 'travel-packages'); ?></a>
                            <a href="#tp-section-images"><?php _e('Imágenes', 'travel-packages'); ?></a>
                            <a href="#tp-section-pricing"><?php _e('Precios', 'travel-packages'); ?></a>
                            <a href="#tp-section-publish"><?php _e('Publicación', 'travel-packages'); ?></a>
                            <a href="#tp-section-seo"><?php _e('SEO', 'travel-packages'); ?></a>
                            <a href="#tp-section-departures"><?php _e('Salidas', 'travel-packages'); ?></a>
                            <a href="#tp-section-itinerary"><?php _e('Itinerario', 'travel-packages'); ?></a>
                            <a href="#tp-section-extra"><?php _e('Adicional', 'travel-packages'); ?></a>
                            <a href="#tp-section-promo"><?php _e('Imagen promocional', 'travel-packages'); ?></a>
                        </nav>

                        <div class="tp-panel" id="tp-section-content">
                            <h2><?php _e('Contenido', 'travel-packages'); ?></h2>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_title"><?php _e('Título del paquete', 'travel-packages'); ?></label>
                                <input type="text" name="tp_title" id="tp_title" required pattern=".*\S.*" aria-describedby="tp-title-error" aria-invalid="<?php echo $status === 'error' ? 'true' : 'false'; ?>" placeholder="<?php esc_attr_e('Ej: Tour por Europa 12 días', 'travel-packages'); ?>">
                                <span id="tp-title-error" class="tp-inline-error" role="alert" style="<?php echo $status === 'error' ? '' : 'display:none;'; ?>"><?php _e( 'El título es obligatorio para guardar el paquete.', 'travel-packages' ); ?></span>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_short_desc"><?php _e('Descripción corta', 'travel-packages'); ?></label>
                                <textarea name="tp_short_desc" id="tp_short_desc" rows="3" maxlength="200" placeholder="<?php esc_attr_e('Resumen atractivo del paquete...', 'travel-packages'); ?>"></textarea>
                                <div class="tp-char-count"><span id="tp-short-count">0</span>/200</div>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_video_url"><?php _e('Video del paquete (YouTube/Vimeo)', 'travel-packages'); ?></label>
                                <input type="url" name="tp_video_url" id="tp_video_url" placeholder="https://www.youtube.com/watch?v=...">
                                <small><?php _e('Opcional. También acepta un archivo MP4/WebM. Se muestra en la ficha del paquete.', 'travel-packages'); ?></small>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label"><?php _e('Descripción completa', 'travel-packages'); ?></label>
                                <?php
                                wp_editor( '', 'tp_content', [
                                    'textarea_name' => 'tp_content',
                                    'textarea_rows' => 10,
                                    'media_buttons' => true,
                                    'editor_class'  => 'tp-editor-main',
                                    'tinymce'       => [ 'wpautop' => false ],
                                ] );
                                ?>
                                <small><?php _e('Usá el editor visual o la pestaña de texto para pegar HTML, videos o código.', 'travel-packages'); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="tp-panel" id="tp-section-images">
                            <h2><?php _e('Imágenes', 'travel-packages'); ?></h2>
                            <div class="tp-field-group">
                                <label class="tp-label"><?php _e('Imagen principal', 'travel-packages'); ?></label>
                                <div class="tp-featured-wrap">
                                    <div id="tp-featured-preview-main"></div>
                                    <input type="hidden" name="tp_featured_image" id="tp_featured_image" value="">
                                    <input type="hidden" id="tp_featured_image_url" value="">
                                    <p>
                                        <button type="button" class="button" id="tp-set-featured-main"><?php _e('Seleccionar imagen principal', 'travel-packages'); ?></button>
                                        <button type="button" class="button" id="tp-remove-featured-main"><?php _e('Eliminar imagen', 'travel-packages'); ?></button>
                                    </p>
                                </div>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label"><?php _e('Galería', 'travel-packages'); ?></label>
                                <div id="tp-gallery-preview" class="tp-gallery-grid"></div>
                                <input type="hidden" name="tp_gallery" id="tp_gallery_ids" value="">
                                <button type="button" id="tp-add-gallery" class="tp-btn tp-btn-secondary">
                                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Agregar imágenes', 'travel-packages'); ?>
                                </button>
                                <small><?php _e('Las imágenes de la galería se muestran en la ficha del paquete.', 'travel-packages'); ?></small>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_map_location"><?php _e('Ubicación del hotel en Google Maps', 'travel-packages'); ?></label>
                                <input type="text" name="tp_map_location" id="tp_map_location" placeholder="<?php esc_attr_e('Dirección, coordenadas o enlace de Google Maps', 'travel-packages'); ?>">
                                <small><?php _e('Opcional. Acepta la dirección, coordenadas (lat,lng), un enlace de Google Maps o el código iframe de "Compartir > Insertar mapa". Se muestra debajo de la galería en la ficha del paquete.', 'travel-packages'); ?></small>
                            </div>
                        </div>

                        <div class="tp-panel" id="tp-section-pricing">
                            <h2><?php _e('Precios y seña', 'travel-packages'); ?></h2>
                            <div class="tp-field-grid tp-two-columns">
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_currency_base"><?php _e('Moneda', 'travel-packages'); ?></label>
                                    <select name="tp_currency_base" id="tp_currency_base">
                                        <option value="USD">US$ – Dólar</option>
                                        <option value="ARS">$ – Peso Arg.</option>
                                        <option value="EUR">€ – Euro</option>
                                        <option value="BRL">R$ – Real</option>
                                    </select>
                                </div>
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_price_adult"><?php _e('Precio adulto (por persona)', 'travel-packages'); ?></label>
                                    <input type="number" step="0.01" min="0" name="tp_price_adult" id="tp_price_adult" placeholder="0.00">
                                </div>
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_price_child"><?php _e('Precio niño', 'travel-packages'); ?></label>
                                    <input type="number" step="0.01" min="0" name="tp_price_child" id="tp_price_child" placeholder="0.00">
                                </div>
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_single_supplement"><?php _e('Suplemento single', 'travel-packages'); ?></label>
                                    <input type="number" step="0.01" min="0" name="tp_single_supplement" id="tp_single_supplement" placeholder="0.00">
                                </div>
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_deposit_type"><?php _e('Seña / depósito', 'travel-packages'); ?></label>
                                    <select name="tp_deposit_type" id="tp_deposit_type">
                                        <option value="none"><?php _e('Sin seña', 'travel-packages'); ?></option>
                                        <option value="percent"><?php _e('Porcentaje del total (%)', 'travel-packages'); ?></option>
                                        <option value="amount"><?php _e('Monto fijo', 'travel-packages'); ?></option>
                                    </select>
                                </div>
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_deposit_value"><?php _e('Valor de la seña', 'travel-packages'); ?></label>
                                    <input type="number" step="0.01" min="0" name="tp_deposit_value" id="tp_deposit_value" placeholder="0">
                                </div>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_deposit_note"><?php _e('Nota de seña / saldo', 'travel-packages'); ?></label>
                                <input type="text" name="tp_deposit_note" id="tp_deposit_note" placeholder="<?php esc_attr_e('Ej: seña 30%, saldo 7 días antes de la salida', 'travel-packages'); ?>">
                            </div>
                            <small><?php _e('El precio adulto se usa como precio "desde" del paquete. Las salidas pueden sobrescribirlo.', 'travel-packages'); ?></small>
                            <details class="tp-advanced">
                                <summary><?php _e('Variantes de precio adicionales (opcional)', 'travel-packages'); ?></summary>
                                <p class="tp-help"><?php _e('Mostralas solo si necesitás categorías extra además del precio por pasajero.', 'travel-packages'); ?></p>
                                <div id="tp-prices-list" class="tp-prices-wrap"></div>
                                <button type="button" id="tp-add-price" class="tp-btn tp-btn-secondary" onclick="if(window.tpAddPriceField){tpAddPriceField('','','USD',false);}">
                                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Agregar variante', 'travel-packages'); ?>
                                </button>
                            </details>
                        </div>

                        <div class="tp-panel" id="tp-section-publish">
                            <h2><?php _e('Publicación y clasificación', 'travel-packages'); ?></h2>
                            <div class="tp-toggle-grid">
                                <label class="tp-toggle-row">
                                    <span><?php _e('Slider Home', 'travel-packages'); ?></span>
                                    <input type="checkbox" name="tp_slider" id="tp_slider" value="1">
                                </label>
                                <label class="tp-toggle-row">
                                    <span><?php _e('Destacado', 'travel-packages'); ?></span>
                                    <input type="checkbox" name="tp_featured" id="tp_featured" value="1">
                                </label>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_post_status"><?php _e('Estado del paquete', 'travel-packages'); ?></label>
                                <select name="tp_post_status" id="tp_post_status">
                                    <option value="publish"><?php _e('Publicado', 'travel-packages'); ?></option>
                                    <option value="draft"><?php _e('Borrador', 'travel-packages'); ?></option>
                                </select>
                            </div>
                            <div class="tp-field-group" style="margin-bottom:16px;">
                                <label class="tp-label"><?php _e('Tipo de viaje', 'travel-packages'); ?></label>
                                <div class="tp-radio-group">
                                    <label class="tp-radio-pill">
                                        <input type="radio" name="tp_tipo_viaje" value="nacional">
                                        <span><?php _e('Nacional', 'travel-packages'); ?></span>
                                    </label>
                                    <label class="tp-radio-pill">
                                        <input type="radio" name="tp_tipo_viaje" value="internacional">
                                        <span><?php _e('Internacional', 'travel-packages'); ?></span>
                                    </label>
                                    <label class="tp-radio-pill">
                                        <input type="radio" name="tp_tipo_viaje" value="" checked>
                                        <span><?php _e('No definido', 'travel-packages'); ?></span>
                                    </label>
                                </div>
                            </div>
                            <div class="tp-field-grid tp-two-columns">
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_destino"><?php _e('Destino', 'travel-packages'); ?></label>
                                    <select name="tp_destino" id="tp_destino">
                                        <option value=""><?php _e('-- Seleccionar destino --', 'travel-packages'); ?></option>
                                        <?php foreach ( $destinos as $destino ) : ?>
                                        <option value="<?php echo esc_attr( $destino->slug ); ?>"><?php echo esc_html( $destino->name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_new_destino"><?php _e('Crear nuevo destino', 'travel-packages'); ?></label>
                                    <input type="text" name="tp_new_destino" id="tp_new_destino" placeholder="<?php esc_attr_e('Ej: Caribe, Europa, Mendoza', 'travel-packages'); ?>">
                                </div>
                            </div>
                            <div class="tp-field-grid tp-two-columns" style="margin-top:12px;">
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_tipologia"><?php _e('Tipología existente', 'travel-packages'); ?></label>
                                    <select name="tp_tipologia" id="tp_tipologia">
                                        <option value=""><?php _e('-- Seleccionar tipología --', 'travel-packages'); ?></option>
                                        <?php foreach ( $tipologias as $tipologia ) : ?>
                                        <option value="<?php echo esc_attr( $tipologia->slug ); ?>"><?php echo esc_html( $tipologia->name ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_new_tipologia"><?php _e('Crear nueva tipología', 'travel-packages'); ?></label>
                                    <input type="text" name="tp_new_tipologia" id="tp_new_tipologia" placeholder="<?php esc_attr_e('Ej: Playa, Montaña, Ciudad', 'travel-packages'); ?>">
                                </div>
                            </div>
                            <div class="tp-field-group" style="margin-top:12px;">
                                <div class="tp-similar-destinos-head">
                                    <label class="tp-label"><?php _e('Destinos similares', 'travel-packages'); ?></label>
                                    <button type="button" class="tp-similar-clear" id="tp-similar-clear"><?php _e('Limpiar selección', 'travel-packages'); ?></button>
                                </div>
                                <div class="tp-similar-destinos-list" id="tp_similar_destinos">
                                    <?php foreach ( $destinos as $destino ) : ?>
                                    <label class="tp-check-pill">
                                        <input type="checkbox" name="tp_similar_destinos[]" value="<?php echo esc_attr( $destino->term_id ); ?>">
                                        <span><?php echo esc_html( $destino->name ); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <small class="tp-help"><?php _e('Opcional. Marcá destinos para armar el carrusel "Destinos similares" de la ficha. Si no marcás ninguno, se usan paquetes del mismo destino.', 'travel-packages'); ?></small>
                            </div>
                        </div>

                        <div class="tp-panel" id="tp-section-seo">
                            <h2><?php _e('SEO y redes sociales', 'travel-packages'); ?></h2>
                            <p class="tp-help"><?php _e('Opcional. Si dejás estos campos vacíos se usan el título, la descripción corta y la imagen principal del paquete.', 'travel-packages'); ?></p>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_seo_title"><?php _e('Título SEO', 'travel-packages'); ?></label>
                                <input type="text" name="tp_seo_title" id="tp_seo_title" maxlength="70" placeholder="<?php esc_attr_e('Ej: Tour por Europa 12 días | Tureo', 'travel-packages'); ?>">
                                <div class="tp-char-count"><span id="tp-seo-title-count">0</span>/70</div>
                                <small><?php _e('Se usa en el título del documento y en Open Graph/Twitter.', 'travel-packages'); ?></small>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_seo_description"><?php _e('Meta descripción', 'travel-packages'); ?></label>
                                <textarea name="tp_seo_description" id="tp_seo_description" rows="3" maxlength="160" placeholder="<?php esc_attr_e('Resumen que aparece en Google y al compartir el paquete.', 'travel-packages'); ?>"></textarea>
                                <div class="tp-char-count"><span id="tp-seo-description-count">0</span>/160</div>
                                <small><?php _e('Si se deja vacía se usa la descripción corta del paquete.', 'travel-packages'); ?></small>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label"><?php _e('Imagen para redes sociales', 'travel-packages'); ?></label>
                                <div class="tp-featured-wrap">
                                    <div id="tp-seo-image-preview"></div>
                                    <input type="hidden" name="tp_seo_image" id="tp_seo_image" value="">
                                    <p>
                                        <button type="button" class="button" id="tp-set-seo-image"><?php _e('Seleccionar imagen SEO', 'travel-packages'); ?></button>
                                        <button type="button" class="button" id="tp-remove-seo-image"><?php _e('Eliminar imagen', 'travel-packages'); ?></button>
                                    </p>
                                </div>
                                <small><?php _e('Recomendado: 1200 x 630 px. Si no se define, se usa la imagen principal.', 'travel-packages'); ?></small>
                            </div>
                            <label class="tp-toggle-row" style="margin-top:12px;">
                                <span><?php _e('No indexar este paquete', 'travel-packages'); ?></span>
                                <input type="checkbox" name="tp_seo_noindex" id="tp_seo_noindex" value="1">
                            </label>
                            <small><?php _e('Oculta el paquete de Google y de otros buscadores. Usalo solo para contenido temporal o duplicado.', 'travel-packages'); ?></small>
                        </div>

                        <div class="tp-panel" id="tp-section-departures">
                            <h2><?php _e('Salidas y disponibilidad', 'travel-packages'); ?></h2>
                            <small><?php _e('Cada salida puede tener cupos, precio propio, mínimo/máximo de pasajeros, cierre de venta y estado.', 'travel-packages'); ?></small>
                            <?php if ( class_exists( 'TP_Reservations' ) && TP_Reservations::is_enabled() ) : ?>
                            <small class="tp-res-departures-hint"><?php _e('Las reservas registradas en el módulo de Reservas se suman automáticamente a los cupos ocupados.', 'travel-packages'); ?></small>
                            <?php endif; ?>
                            <div id="tp-dates-list" class="tp-dates-wrap" style="margin-top:12px;"></div>
                            <button type="button" id="tp-add-date" class="tp-btn tp-btn-secondary">
                                <span class="dashicons dashicons-plus-alt"></span> <?php _e('Agregar salida', 'travel-packages'); ?>
                            </button>
                        </div>

                        <div class="tp-panel" id="tp-section-itinerary">
                            <h2><?php _e('Itinerario día por día', 'travel-packages'); ?></h2>
                            <p class="tp-help"><?php _e('Agregá cada día con un título y una descripción. Se muestra como acordeón en la ficha del paquete.', 'travel-packages'); ?></p>
                            <div id="tp-itinerary-list" class="tp-itinerary-wrap"></div>
                            <button type="button" id="tp-add-itinerary" class="tp-btn tp-btn-secondary">
                                <span class="dashicons dashicons-plus-alt"></span> <?php _e('Agregar día', 'travel-packages'); ?>
                            </button>
                        </div>

                        <div class="tp-panel" id="tp-section-extra">
                            <h2><?php _e('Información adicional', 'travel-packages'); ?></h2>
                            <div class="tp-subsection">
                                <h3><?php _e('WhatsApp del paquete', 'travel-packages'); ?></h3>
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_whatsapp_number"><?php _e('Número de WhatsApp (opcional)', 'travel-packages'); ?></label>
                                    <input type="text" name="tp_whatsapp_number" id="tp_whatsapp_number" placeholder="5491123456789">
                                    <small><?php _e('Código de país + número, sin +, espacios ni guiones. Si lo dejás vacío, se usa el número general de Ajustes.', 'travel-packages'); ?></small>
                                </div>
                            </div>
                            <div class="tp-subsection">
                                <h3><?php _e('Ficha técnica', 'travel-packages'); ?></h3>
                                <p class="tp-help"><?php _e('Pares de etiqueta y valor que se muestran en la ficha del paquete (ej: Duración / 12 días).', 'travel-packages'); ?></p>
                                <div id="tp-tech-fields" class="tp-tech-wrap"></div>
                                <button type="button" id="tp-add-tech-field" class="tp-btn tp-btn-secondary">
                                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Agregar campo técnico', 'travel-packages'); ?>
                                </button>
                            </div>
                            <div class="tp-subsection">
                                <h3><?php _e('Campos personalizados', 'travel-packages'); ?></h3>
                                <p class="tp-help"><?php _e('Campos globales definidos para todos los paquetes. Podés reordenarlos arrastrando.', 'travel-packages'); ?></p>
                                <div id="tp-custom-fields-wrap">
                                    <?php
                                    $cf_defs = TP_CustomFields::get_definitions();
                                    if ( empty( $cf_defs ) ) : ?>
                                    <p style="color:#999;"><?php _e('No hay campos personalizados definidos.', 'travel-packages'); ?> <a href="<?php echo esc_url( admin_url('admin.php?page=tp-custom-fields') ); ?>"><?php _e('Configurar campos', 'travel-packages'); ?></a></p>
                                    <?php else : ?>
                                        <?php foreach ( $cf_defs as $cf_field ) :
                                            echo TP_CustomFields::render_field_input( $cf_field, '', 'tp_cf' );
                                        endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="hidden" name="tp_cf_order" id="tp_cf_order" value="">
                        </div>

                        <div class="tp-panel" id="tp-section-promo">
                            <h2><?php _e('Imagen promocional', 'travel-packages'); ?></h2>
                            <p class="tp-help"><?php _e('Genera una imagen usando la imagen principal como fondo, con las fechas, el título, la descripción corta y los precios principales. Se puede descargar como PNG.', 'travel-packages'); ?></p>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_promo_format"><?php _e('Formato', 'travel-packages'); ?></label>
                                <select id="tp_promo_format">
                                    <option value="square"><?php _e('Cuadrado — 1080 x 1080 px', 'travel-packages'); ?></option>
                                    <option value="story"><?php _e('Vertical — 1080 x 1920 px', 'travel-packages'); ?></option>
                                </select>
                                <small><?php _e('Elegí cuadrado para el feed o vertical para historias y reels.', 'travel-packages'); ?></small>
                            </div>
                            <div class="tp-promo-actions">
                                <button type="button" class="tp-btn tp-btn-primary" id="tp-generate-promo">
                                    <span class="dashicons dashicons-format-image"></span> <?php _e('Crear imagen', 'travel-packages'); ?>
                                </button>
                                <button type="button" class="tp-btn tp-btn-secondary" id="tp-download-promo" disabled>
                                    <span class="dashicons dashicons-download"></span> <?php _e('Descargar PNG', 'travel-packages'); ?>
                                </button>
                            </div>
                            <p id="tp-promo-status" class="tp-help" role="status" aria-live="polite"></p>
                            <div class="tp-promo-preview-wrap">
                                <canvas id="tp-promo-canvas" width="1080" height="1080" aria-label="<?php esc_attr_e('Vista previa de la imagen promocional', 'travel-packages'); ?>"></canvas>
                            </div>
                        </div>

                        <div class="tp-panel tp-panel-actions">
                            <a href="#" class="tp-form-view-link tp-sticky-view-package" target="_blank" rel="noopener" style="display:none;">
                                <span class="dashicons dashicons-external"></span> <?php _e('Vista previa', 'travel-packages'); ?>
                            </a>
                            <button type="submit" class="tp-btn-primary"><?php _e('Guardar paquete', 'travel-packages'); ?></button>
                            <button type="button" id="tp-reset-package" class="tp-btn tp-btn-secondary"><?php _e('Limpiar formulario', 'travel-packages'); ?></button>
                            <button type="button" id="tp-delete-package" class="tp-btn tp-btn-danger" style="display:none;"><?php _e('Eliminar paquete', 'travel-packages'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_calendar_page() {
        $month = isset( $_GET['tp_month'] ) ? max( 1, min( 12, intval( $_GET['tp_month'] ) ) ) : (int) current_time( 'n' );
        $year  = isset( $_GET['tp_year'] ) ? max( 2000, min( 2100, intval( $_GET['tp_year'] ) ) ) : (int) current_time( 'Y' );

        $first       = mktime( 0, 0, 0, $month, 1, $year );
        $days_in_month = (int) date( 't', $first );
        $week_start  = (int) get_option( 'start_of_week', 1 );
        $first_dow   = (int) date( 'w', $first );
        $offset      = ( $first_dow - $week_start + 7 ) % 7;

        $packages = get_posts( [
            'post_type'      => TP_Package_Data::POST_TYPE,
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $events        = [];
        $month_total   = 0;
        $seats_open    = 0;
        $seats_booked  = 0;
        $package_ids   = [];

        foreach ( $packages as $package ) {
            foreach ( TP_Package_Data::get_departures( $package->ID ) as $departure ) {
                if ( ! $departure['from_ts'] ) {
                    continue;
                }
                if ( (int) date( 'n', $departure['from_ts'] ) !== $month || (int) date( 'Y', $departure['from_ts'] ) !== $year ) {
                    continue;
                }
                $day = (int) date( 'j', $departure['from_ts'] );
                $events[ $day ][] = [ 'package' => $package, 'departure' => $departure ];
                $month_total++;
                $package_ids[ $package->ID ] = true;
                if ( $departure['status'] === 'open' && $departure['seats_left'] !== null ) {
                    $seats_open += (int) $departure['seats_left'];
                }
                $seats_booked += (int) $departure['seats_booked'];
            }
        }

        $prev_month = mktime( 0, 0, 0, $month - 1, 1, $year );
        $next_month = mktime( 0, 0, 0, $month + 1, 1, $year );
        $base_url   = admin_url( 'admin.php?page=tp-calendar' );

        $prev_url = add_query_arg( [ 'tp_month' => (int) date( 'n', $prev_month ), 'tp_year' => (int) date( 'Y', $prev_month ) ], $base_url );
        $next_url = add_query_arg( [ 'tp_month' => (int) date( 'n', $next_month ), 'tp_year' => (int) date( 'Y', $next_month ) ], $base_url );
        $today_url = $base_url;

        $weekday_labels = [];
        for ( $i = 0; $i < 7; $i++ ) {
            $dow = ( $week_start + $i ) % 7;
            $weekday_labels[] = date_i18n( 'D', mktime( 0, 0, 0, 1, 7 + $dow, 2024 ) );
        }

        $status_labels = [
            'open'    => __( 'Abierta', 'travel-packages' ),
            'soldout' => __( 'Agotada', 'travel-packages' ),
            'closed'  => __( 'Cerrada', 'travel-packages' ),
        ];
        ?>
        <div class="wrap tp-admin-wrap tp-calendar-wrap">
            <div class="tp-admin-header">
                <div class="tp-header-left tp-tureo-header-left">
                    <?php self::render_brand(); ?>
                    <div class="tp-tureo-heading">
                        <h1><?php printf( esc_html__( 'Calendario de Salidas — %s %s', 'travel-packages' ), esc_html( date_i18n( 'F', $first ) ), esc_html( $year ) ); ?></h1>
                        <p><?php _e( 'Cupos, estados y precios de todas las salidas del mes.', 'travel-packages' ); ?></p>
                    </div>
                    <?php self::render_version_badge(); ?>
                </div>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-package-new' ) ); ?>" class="tp-btn-primary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e( 'Nuevo Paquete', 'travel-packages' ); ?>
                </a>
            </div>

            <div class="tp-stats-grid tp-stats-grid-4">
                <div class="tp-stat-card">
                    <div class="tp-stat-icon">🗓</div>
                    <div class="tp-stat-info">
                        <span class="tp-stat-number"><?php echo (int) $month_total; ?></span>
                        <span class="tp-stat-label"><?php _e( 'Salidas del mes', 'travel-packages' ); ?></span>
                    </div>
                </div>
                <div class="tp-stat-card">
                    <div class="tp-stat-icon">🎟</div>
                    <div class="tp-stat-info">
                        <span class="tp-stat-number"><?php echo (int) $seats_open; ?></span>
                        <span class="tp-stat-label"><?php _e( 'Cupos disponibles', 'travel-packages' ); ?></span>
                    </div>
                </div>
                <div class="tp-stat-card">
                    <div class="tp-stat-icon">👥</div>
                    <div class="tp-stat-info">
                        <span class="tp-stat-number"><?php echo (int) $seats_booked; ?></span>
                        <span class="tp-stat-label"><?php _e( 'Pasajeros reservados', 'travel-packages' ); ?></span>
                    </div>
                </div>
                <div class="tp-stat-card">
                    <div class="tp-stat-icon">📦</div>
                    <div class="tp-stat-info">
                        <span class="tp-stat-number"><?php echo (int) count( $package_ids ); ?></span>
                        <span class="tp-stat-label"><?php _e( 'Paquetes con salidas', 'travel-packages' ); ?></span>
                    </div>
                </div>
            </div>

            <div class="tp-calendar-toolbar">
                <div class="tp-calendar-nav">
                    <a href="<?php echo esc_url( $prev_url ); ?>" class="button">&laquo; <?php _e( 'Mes anterior', 'travel-packages' ); ?></a>
                    <a href="<?php echo esc_url( $today_url ); ?>" class="button"><?php _e( 'Hoy', 'travel-packages' ); ?></a>
                    <a href="<?php echo esc_url( $next_url ); ?>" class="button"><?php _e( 'Mes siguiente', 'travel-packages' ); ?> &raquo;</a>
                </div>
                <div class="tp-calendar-legend">
                    <span class="tp-cal-dot tp-cal-dot-open"></span> <?php _e( 'Abierta', 'travel-packages' ); ?>
                    <span class="tp-cal-dot tp-cal-dot-low"></span> <?php _e( 'Últimos lugares', 'travel-packages' ); ?>
                    <span class="tp-cal-dot tp-cal-dot-soldout"></span> <?php _e( 'Agotada', 'travel-packages' ); ?>
                    <span class="tp-cal-dot tp-cal-dot-closed"></span> <?php _e( 'Cerrada', 'travel-packages' ); ?>
                </div>
            </div>

            <?php if ( $month_total === 0 ) : ?>
            <div class="tp-calendar-empty">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php _e( 'No hay salidas cargadas para este mes. Podés navegar a otro mes o crear un paquete nuevo.', 'travel-packages' ); ?>
            </div>
            <?php endif; ?>

            <div class="tp-calendar-scroll">
                <table class="tp-calendar-table" aria-label="<?php esc_attr_e( 'Calendario de salidas del mes', 'travel-packages' ); ?>">
                    <thead>
                        <tr>
                            <?php foreach ( $weekday_labels as $label ) : ?>
                            <th scope="col"><?php echo esc_html( $label ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $cell = 0;
                        $rows = (int) ceil( ( $offset + $days_in_month ) / 7 );
                        for ( $row = 0; $row < $rows; $row++ ) :
                            echo '<tr>';
                            for ( $col = 0; $col < 7; $col++, $cell++ ) :
                                $day = $cell - $offset + 1;
                                $is_current_month = ( $day >= 1 && $day <= $days_in_month );
                                $is_today = $is_current_month && (int) current_time( 'j' ) === $day && (int) current_time( 'n' ) === $month && (int) current_time( 'Y' ) === $year;
                                ?>
                                <td class="tp-cal-cell <?php echo $is_current_month ? '' : 'tp-cal-cell-empty'; ?> <?php echo $is_today ? 'tp-cal-cell-today' : ''; ?>" <?php echo $is_today ? 'aria-current="date"' : ''; ?>>
                                    <?php if ( $is_current_month ) : ?>
                                    <span class="tp-cal-day"><?php echo (int) $day; ?></span>
                                    <?php if ( ! empty( $events[ $day ] ) ) : ?>
                                    <div class="tp-cal-events">
                                        <?php foreach ( $events[ $day ] as $event ) :
                                            $departure = $event['departure'];
                                            $package   = $event['package'];
                                            $status    = $departure['status'];
                                            $seats     = $departure['seats_left'];
                                            $class     = 'tp-cal-event tp-cal-event-' . $status;
                                            if ( $status === 'open' && $seats !== null && $seats > 0 && $seats <= 5 ) {
                                                $class .= ' tp-cal-event-low';
                                            }
                                            $edit_url = admin_url( 'admin.php?page=tp-package-new&package_id=' . $package->ID );
                                        ?>
                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="<?php echo esc_attr( $class ); ?>" title="<?php echo esc_attr( $package->post_title ); ?>">
                                            <span class="tp-cal-event-title"><?php echo esc_html( $package->post_title ); ?></span>
                                            <span class="tp-cal-event-meta">
                                                <?php echo esc_html( $status_labels[ $status ] ?? $status ); ?>
                                                <?php if ( $seats !== null ) : ?>
                                                    · <?php printf( esc_html__( '%d cupos', 'travel-packages' ), (int) $seats ); ?>
                                                <?php endif; ?>
                                            </span>
                                        </a>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <?php
                            endfor;
                            echo '</tr>';
                        endfor;
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    public static function render_shortcodes_page() {
        ?>
        <div class="wrap tp-admin-wrap">
            <div class="tp-admin-header">
                <div class="tp-header-left tp-tureo-header-left">
                    <?php self::render_brand(); ?>
                    <div class="tp-tureo-heading">
                        <h1><?php _e('Guía de Shortcodes', 'travel-packages'); ?></h1>
                        <p><?php _e('Copiá y pegá estos shortcodes en cualquier página o widget.', 'travel-packages'); ?></p>
                    </div>
                    <?php self::render_version_badge(); ?>
                </div>
            </div>

            <div class="tp-shortcodes-guide">

                <div class="tp-sc-section">
                    <div class="tp-sc-header">
                        <span class="tp-sc-icon">🎠</span>
                        <div>
                            <h2>[tp_slider]</h2>
                            <p><?php _e('Muestra un slider/carrusel con los paquetes marcados como "Slider del Home".', 'travel-packages'); ?></p>
                        </div>
                    </div>
                    <div class="tp-sc-body">
                        <h3><?php _e('Parámetros disponibles:', 'travel-packages'); ?></h3>
                        <table class="tp-params-table">
                            <thead><tr><th>Parámetro</th><th>Defecto</th><th>Descripción</th></tr></thead>
                            <tbody>
                                <tr><td><code>limit</code></td><td><code>5</code></td><td><?php _e('Cantidad máxima de slides', 'travel-packages'); ?></td></tr>
                                <tr><td><code>autoplay</code></td><td><code>true</code></td><td><?php _e('Avance automático (true/false)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>interval</code></td><td><code>5000</code></td><td><?php _e('Tiempo entre slides en milisegundos', 'travel-packages'); ?></td></tr>
                            </tbody>
                        </table>
                        <h3><?php _e('Ejemplos:', 'travel-packages'); ?></h3>
                        <div class="tp-code-block"><code>[tp_slider]</code></div>
                        <div class="tp-code-block"><code>[tp_slider limit="3" autoplay="true" interval="4000"]</code></div>
                    </div>
                </div>

                <div class="tp-sc-section">
                    <div class="tp-sc-header">
                        <span class="tp-sc-icon">⊞</span>
                        <div>
                            <h2>[tp_grid]</h2>
                            <p><?php _e('Muestra una grilla de tarjetas de paquetes.', 'travel-packages'); ?></p>
                        </div>
                    </div>
                    <div class="tp-sc-body">
                        <h3><?php _e('Parámetros disponibles:', 'travel-packages'); ?></h3>
                        <table class="tp-params-table">
                            <thead><tr><th>Parámetro</th><th>Defecto</th><th>Descripción</th></tr></thead>
                            <tbody>
                                <tr><td><code>limit</code></td><td><code>6</code></td><td><?php _e('Cantidad de paquetes a mostrar', 'travel-packages'); ?></td></tr>
                                <tr><td><code>columns</code></td><td><code>3</code></td><td><?php _e('Columnas: 1, 2, 3 o 4', 'travel-packages'); ?></td></tr>
                                <tr><td><code>destacados</code></td><td><code>false</code></td><td><?php _e('Solo mostrar paquetes destacados (true/false)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>carousel</code></td><td><code>false</code></td><td><?php _e('Modo carrusel horizontal con swipe y flechas (true/false)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>desktop</code></td><td><code>columns</code></td><td><?php _e('Carrusel: paquetes visibles por página en desktop', 'travel-packages'); ?></td></tr>
                                <tr><td><code>movile</code></td><td><code>desktop</code></td><td><?php _e('Carrusel: paquetes visibles por página en mobile', 'travel-packages'); ?></td></tr>
                                <tr><td><code>destino</code></td><td><em><?php _e('vacío', 'travel-packages'); ?></em></td><td><?php _e('Filtrar por slug de destino (ej: europa)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>tipologia</code></td><td><em><?php _e('vacío', 'travel-packages'); ?></em></td><td><?php _e('Filtrar por slug de tipología (ej: playa)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>ids</code></td><td><em><?php _e('vacío', 'travel-packages'); ?></em></td><td><?php _e('IDs específicos separados por coma (ej: 12,34,56)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>orderby</code></td><td><code>date</code></td><td><?php _e('Ordenar por: date, title, meta_value_num', 'travel-packages'); ?></td></tr>
                                <tr><td><code>order</code></td><td><code>DESC</code></td><td><?php _e('Dirección: ASC o DESC', 'travel-packages'); ?></td></tr>
                            </tbody>
                        </table>
                        <h3><?php _e('Ejemplos:', 'travel-packages'); ?></h3>
                        <div class="tp-code-block"><code>[tp_grid]</code><span class="tp-code-comment"><?php _e('// últimos 6 paquetes, 3 columnas', 'travel-packages'); ?></span></div>
                        <div class="tp-code-block"><code>[tp_grid destacados="true" limit="4" columns="2"]</code></div>
                        <div class="tp-code-block"><code>[tp_grid destacados="true" carousel="true" limit="8" columns="4"]</code><span class="tp-code-comment"><?php _e('// carrusel de destacados', 'travel-packages'); ?></span></div>
                        <div class="tp-code-block"><code>[tp_grid destacados="true" carousel="true" limit="12" desktop="3" movile="1"]</code><span class="tp-code-comment"><?php _e('// carrusel responsive: 3 en desktop, 1 en mobile', 'travel-packages'); ?></span></div>
                        <div class="tp-code-block"><code>[tp_grid destino="caribe" limit="8" columns="4"]</code></div>
                        <div class="tp-code-block"><code>[tp_grid tipologia="playa" limit="12" columns="3"]</code></div>
                        <div class="tp-code-block"><code>[tp_grid ids="12,34,56" columns="3"]</code></div>
                    </div>
                </div>

                <div class="tp-sc-section">
                    <div class="tp-sc-header">
                        <span class="tp-sc-icon">📦</span>
                        <div>
                            <h2>[tp_paquete]</h2>
                            <p><?php _e('Muestra un paquete individual embebido con todos sus detalles.', 'travel-packages'); ?></p>
                        </div>
                    </div>
                    <div class="tp-sc-body">
                        <h3><?php _e('Parámetros disponibles:', 'travel-packages'); ?></h3>
                        <table class="tp-params-table">
                            <thead><tr><th>Parámetro</th><th>Descripción</th></tr></thead>
                            <tbody>
                                <tr><td><code>id</code></td><td><?php _e('ID del paquete (requerido). Lo encontrás en la URL al editar el paquete.', 'travel-packages'); ?></td></tr>
                            </tbody>
                        </table>
                        <h3><?php _e('Ejemplo:', 'travel-packages'); ?></h3>
                        <div class="tp-code-block"><code>[tp_paquete id="123"]</code></div>
                    </div>
                </div>

                <div class="tp-sc-section">
                    <div class="tp-sc-header">
                        <span class="tp-sc-icon">📂</span>
                        <div>
                            <h2>[tp_catalogo]</h2>
                            <p><?php _e('Landing page completa con buscador, filtros y grilla de todos los paquetes.', 'travel-packages'); ?></p>
                        </div>
                    </div>
                    <div class="tp-sc-body">
                        <h3><?php _e('Parámetros disponibles:', 'travel-packages'); ?></h3>
                        <table class="tp-params-table">
                            <thead><tr><th>Parámetro</th><th>Defecto</th><th>Descripción</th></tr></thead>
                            <tbody>
                                <tr><td><code>limit</code></td><td><code>-1</code></td><td><?php _e('Cantidad de paquetes (-1 = todos)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>columns</code></td><td><code>3</code></td><td><?php _e('Columnas: 1, 2, 3 o 4', 'travel-packages'); ?></td></tr>
                                <tr><td><code>show_search</code></td><td><code>true</code></td><td><?php _e('Mostrar buscador por texto', 'travel-packages'); ?></td></tr>
                                <tr><td><code>show_filters</code></td><td><code>true</code></td><td><?php _e('Mostrar filtros (destino, orden)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>orderby</code></td><td><code>date</code></td><td><?php _e('Ordenar por: date, title', 'travel-packages'); ?></td></tr>
                                <tr><td><code>order</code></td><td><code>DESC</code></td><td><?php _e('Dirección: ASC o DESC', 'travel-packages'); ?></td></tr>
                            </tbody>
                        </table>
                        <h3><?php _e('Ejemplos:', 'travel-packages'); ?></h3>
                        <div class="tp-code-block"><code>[tp_catalogo]</code><span class="tp-code-comment"><?php _e('// catálogo completo con todos los paquetes', 'travel-packages'); ?></span></div>
                        <div class="tp-code-block"><code>[tp_catalogo columns="4" limit="12"]</code></div>
                        <div class="tp-code-block"><code>[tp_catalogo show_search="false" show_filters="false"]</code></div>
                    </div>
                </div>

                <div class="tp-sc-section">
                    <div class="tp-sc-header">
                        <span class="tp-sc-icon">🔎</span>
                        <div>
                            <h2>[tp_buscador]</h2>
                            <p><?php _e('Buscador de paquetes con sugerencias en vivo vía AJAX para colocar en cualquier parte del template. Envía la búsqueda a la página de resultados configurada en Ajustes.', 'travel-packages'); ?></p>
                        </div>
                    </div>
                    <div class="tp-sc-body">
                        <h3><?php _e('Parámetros disponibles:', 'travel-packages'); ?></h3>
                        <table class="tp-params-table">
                            <thead><tr><th>Parámetro</th><th>Defecto</th><th>Descripción</th></tr></thead>
                            <tbody>
                                <tr><td><code>url</code></td><td><em><?php _e('Ajustes', 'travel-packages'); ?></em></td><td><?php _e('URL de la página de resultados. Si se omite, usa la configurada en Ajustes o la página actual.', 'travel-packages'); ?></td></tr>
                                <tr><td><code>placeholder</code></td><td><em><?php _e('Ajustes', 'travel-packages'); ?></em></td><td><?php _e('Texto de ayuda dentro del campo de búsqueda', 'travel-packages'); ?></td></tr>
                                <tr><td><code>button</code></td><td><code>Buscar</code></td><td><?php _e('Texto del botón de búsqueda', 'travel-packages'); ?></td></tr>
                                <tr><td><code>show_destino</code></td><td><code>true</code></td><td><?php _e('Mostrar selector de destinos (true/false)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>show_tipo_viaje</code></td><td><code>true</code></td><td><?php _e('Mostrar filtro nacional/internacional (true/false)', 'travel-packages'); ?></td></tr>
                                <tr><td><code>class</code></td><td><em><?php _e('vacío', 'travel-packages'); ?></em></td><td><?php _e('Clase CSS adicional para el formulario', 'travel-packages'); ?></td></tr>
                            </tbody>
                        </table>
                        <h3><?php _e('Ejemplos:', 'travel-packages'); ?></h3>
                        <div class="tp-code-block"><code>[tp_buscador]</code><span class="tp-code-comment"><?php _e('// usa la configuración de Ajustes', 'travel-packages'); ?></span></div>
                        <div class="tp-code-block"><code>[tp_buscador placeholder="Tu próximo viaje" button="Buscar" show_destino="true"]</code></div>
                        <div class="tp-code-block"><code>[tp_buscador url="/paquetes/" show_tipo_viaje="false"]</code></div>
                        <div class="tp-code-block"><code>[tp_buscador class="mi-buscador-hero"]</code></div>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }

}
