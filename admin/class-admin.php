<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Admin {

    public static function init() {
        add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
        add_action( 'admin_post_tp_save_package',     [ __CLASS__, 'save_package' ] );
        add_action( 'admin_post_tp_duplicate_package', [ __CLASS__, 'duplicate_package' ] );
        add_action( 'admin_post_tp_export_packages',   [ __CLASS__, 'export_packages' ] );
        add_action( 'admin_post_tp_import_packages',   [ __CLASS__, 'import_packages' ] );
        add_action( 'admin_init',            [ __CLASS__, 'handle_delete_package' ] );
        add_action( 'admin_init',            [ __CLASS__, 'redirect_native_editor' ] );
    }

    public static function register_menu() {
        // Main menu
        add_menu_page(
            __( 'Paquetes de Viaje', 'travel-packages' ),
            __( 'Paquetes de Viaje', 'travel-packages' ),
            'manage_options',
            'tp-dashboard',
            [ __CLASS__, 'render_dashboard' ],
            'dashicons-airplane',
            20
        );

        // Submenu: list
        add_submenu_page(
            'tp-dashboard',
            __( 'Gestión de Paquetes', 'travel-packages' ),
            __( 'Gestión de Paquetes', 'travel-packages' ),
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

    public static function enqueue( $hook ) {
        $screen = get_current_screen();
        $page   = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $load_admin_assets = false;

        if ( strpos( $hook, 'tp-' ) !== false || ( $screen && $screen->post_type === 'paquete_viaje' ) ) {
            $load_admin_assets = true;
        }

        if ( $load_admin_assets ) {
            wp_enqueue_style(  'tp-admin', TP_PLUGIN_URL . 'admin/css/admin.css', [], TP_VERSION );
            wp_enqueue_script( 'tp-admin', TP_PLUGIN_URL . 'admin/js/admin.js',   [ 'jquery' ], TP_VERSION, true );
        }

        $is_packages_page = in_array( $page, [ 'tp-packages', 'tp-package-new' ], true );

        if ( $is_packages_page ) {
            wp_enqueue_script( 'jquery-ui-datepicker' );
            wp_enqueue_style( 'jquery-ui', 'https://code.jquery.com/ui/1.13.2/themes/smoothness/jquery-ui.css' );
            wp_enqueue_media();
            wp_enqueue_script( 'tp-admin-packages', TP_PLUGIN_URL . 'admin/js/packages.js', [ 'jquery', 'jquery-ui-datepicker' ], TP_VERSION, true );
            wp_localize_script( 'tp-admin-packages', 'TP_ADMIN', [
                'nonce'        => wp_create_nonce('tp_admin_nonce'),
                'delete_nonce' => wp_create_nonce('tp_delete_package'),
                'ajax_url'     => admin_url('admin-ajax.php'),
            ]);
        }
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
                <div class="tp-header-left">
                    <span class="tp-logo-icon">✈</span>
                    <div>
                        <h1><?php _e('Panel de Paquetes de Viaje', 'travel-packages'); ?></h1>
                        <p><?php _e('Gestioná todos tus paquetes turísticos desde acá.', 'travel-packages'); ?></p>
                    </div>
                </div>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=paquete_viaje')); ?>" class="tp-btn-primary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Nuevo Paquete', 'travel-packages'); ?>
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="tp-stats-grid">
                <div class="tp-stat-card">
                    <div class="tp-stat-icon" style="background:#e8f4fd;color:#0073aa;">✈</div>
                    <div class="tp-stat-info">
                        <span class="tp-stat-number"><?php echo $published; ?></span>
                        <span class="tp-stat-label"><?php _e('Paquetes Publicados', 'travel-packages'); ?></span>
                    </div>
                </div>
                <div class="tp-stat-card">
                    <div class="tp-stat-icon" style="background:#fff3e0;color:#f57c00;">🖼</div>
                    <div class="tp-stat-info">
                        <span class="tp-stat-number"><?php echo $slider_count; ?></span>
                        <span class="tp-stat-label"><?php _e('En el Slider', 'travel-packages'); ?></span>
                    </div>
                </div>
                <div class="tp-stat-card">
                    <div class="tp-stat-icon" style="background:#f3e5f5;color:#7b1fa2;">⭐</div>
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
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=paquete_viaje')); ?>"><?php _e('Ver todos →', 'travel-packages'); ?></a>
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
                                $price    = get_post_meta(get_the_ID(), '_tp_price', true);
                                $currency = get_post_meta(get_the_ID(), '_tp_currency', true) ?: 'USD';
                                $slider   = get_post_meta(get_the_ID(), '_tp_slider', true);
                                $featured = get_post_meta(get_the_ID(), '_tp_featured', true);
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
                                <td><?php echo $price ? esc_html($currency.' '.number_format((float)$price,0,',','.')) : '—'; ?></td>
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
                                    <a href="<?php echo esc_url(get_edit_post_link()); ?>" class="tp-table-link"><?php _e('Editar', 'travel-packages'); ?></a>
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
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public static function render_packages_page() {
        $paged   = isset( $_GET['tp_paged'] ) ? max( 1, intval( $_GET['tp_paged'] ) ) : 1;
        $per_page = 40;

        $args = [
            'post_type'      => 'paquete_viaje',
            'post_status'    => ['publish','draft'],
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        // Advanced filters
        $filtro_destino   = isset( $_GET['tp_filtro_destino'] ) ? sanitize_text_field( $_GET['tp_filtro_destino'] ) : '';
        $filtro_tipologia = isset( $_GET['tp_filtro_tipologia'] ) ? sanitize_text_field( $_GET['tp_filtro_tipologia'] ) : '';
        $filtro_precio_min = isset( $_GET['tp_filtro_precio_min'] ) ? floatval( $_GET['tp_filtro_precio_min'] ) : 0;
        $filtro_precio_max = isset( $_GET['tp_filtro_precio_max'] ) ? floatval( $_GET['tp_filtro_precio_max'] ) : 0;
        $filtro_slider    = isset( $_GET['tp_filtro_slider'] ) ? sanitize_text_field( $_GET['tp_filtro_slider'] ) : '';
        $filtro_featured  = isset( $_GET['tp_filtro_featured'] ) ? sanitize_text_field( $_GET['tp_filtro_featured'] ) : '';
        $filtro_search    = isset( $_GET['tp_filtro_search'] ) ? sanitize_text_field( $_GET['tp_filtro_search'] ) : '';
        $orderby_url      = isset( $_GET['tp_orderby'] ) ? sanitize_key( $_GET['tp_orderby'] ) : '';
        $order_url        = isset( $_GET['tp_order'] ) && $_GET['tp_order'] === 'ASC' ? 'ASC' : 'DESC';

        if ( $orderby_url === 'meta_value_num' ) {
            $args['orderby']  = 'meta_value_num';
            $args['meta_key'] = '_tp_price';
            $args['order']    = $order_url;
        } elseif ( $orderby_url ) {
            $args['orderby'] = $orderby_url;
            $args['order']   = $order_url;
        }

        $tax_query = [];

        if ( $filtro_destino ) {
            $tax_query[] = [
                'taxonomy' => 'destino',
                'field'    => 'slug',
                'terms'    => $filtro_destino,
            ];
        }

        if ( $filtro_tipologia ) {
            $tax_query[] = [
                'taxonomy' => 'tipologia',
                'field'    => 'slug',
                'terms'    => $filtro_tipologia,
            ];
        }

        if ( $tax_query ) {
            $args['tax_query'] = $tax_query;
        }

        if ( $filtro_search ) {
            $args['s'] = $filtro_search;
        }

        $meta_query = [];
        if ( $filtro_precio_min > 0 ) {
            $meta_query[] = [ 'key' => '_tp_price', 'value' => $filtro_precio_min, 'type' => 'NUMERIC', 'compare' => '>=' ];
        }
        if ( $filtro_precio_max > 0 ) {
            $meta_query[] = [ 'key' => '_tp_price', 'value' => $filtro_precio_max, 'type' => 'NUMERIC', 'compare' => '<=' ];
        }
        if ( $filtro_slider === 'yes' ) {
            $meta_query[] = [ 'key' => '_tp_slider', 'value' => '1' ];
        } elseif ( $filtro_slider === 'no' ) {
            $meta_query[] = [ 'key' => '_tp_slider', 'value' => '0' ];
        }
        if ( $filtro_featured === 'yes' ) {
            $meta_query[] = [ 'key' => '_tp_featured', 'value' => '1' ];
        } elseif ( $filtro_featured === 'no' ) {
            $meta_query[] = [ 'key' => '_tp_featured', 'value' => '0' ];
        }
        if ( count( $meta_query ) > 0 ) {
            $args['meta_query'] = $meta_query;
        }

        $package_query = new WP_Query( $args );
        $packages      = $package_query->posts;
        $total_pages   = $package_query->max_num_pages;

        $destinos = get_terms([ 'taxonomy' => 'destino', 'hide_empty' => false ]);
        $tipologias = get_terms([ 'taxonomy' => 'tipologia', 'hide_empty' => false ]);
        $status   = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $base_url = admin_url( 'admin.php?page=tp-packages' );
        ?>
        <div class="wrap tp-admin-wrap tp-packages-wrap">
            <div class="tp-admin-header">
                <div class="tp-header-left">
                    <span class="tp-logo-icon">🧳</span>
                    <div>
                        <h1><?php _e('Gestión de Paquetes', 'travel-packages'); ?></h1>
                        <p><?php _e('Un gestor visual y rápido para crear y editar tus paquetes como en un portal de viajes.', 'travel-packages'); ?></p>
                    </div>
                </div>
                <button id="tp-new-package" class="tp-btn-primary">
                    <span class="dashicons dashicons-plus-alt"></span> <?php _e('Nuevo paquete', 'travel-packages'); ?>
                </button>
            </div>

            <?php if ( $status === 'success' ) : ?>
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

            <div class="tp-packages-grid">
                <div class="tp-packages-list">
                    <div class="tp-card-header tp-filters-header">
                        <h2><?php _e('Listado de paquetes', 'travel-packages'); ?></h2>
                        <div class="tp-filters-bar">
                            <input type="search" id="tp-package-search" class="tp-package-search" placeholder="<?php esc_attr_e('Buscar paquete por nombre...', 'travel-packages'); ?>">
                            <button type="button" id="tp-toggle-filters" class="button tp-bar-btn <?php if ($filtro_destino || $filtro_precio_min > 0 || $filtro_precio_max > 0 || $filtro_slider || $filtro_featured || $filtro_search || $orderby_url) echo 'button-primary'; ?>">
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

                    <?php
                    $has_active_filters = $filtro_destino || $filtro_tipologia || $filtro_precio_min > 0 || $filtro_precio_max > 0 || $filtro_slider || $filtro_featured || $filtro_search || $orderby_url;
                    ?>
                    <div class="tp-filters-form" style="display:<?php echo $has_active_filters ? 'block' : 'none'; ?>;">
                        <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="tp-filters-inline">
                            <input type="hidden" name="page" value="tp-packages">

                            <div class="tp-filter-field">
                                <label><?php _e( 'Buscar', 'travel-packages' ); ?></label>
                                <input type="search" name="tp_filtro_search" placeholder="<?php esc_attr_e( 'Nombre del paquete…', 'travel-packages' ); ?>" value="<?php echo esc_attr( $filtro_search ); ?>">
                            </div>

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
                        <?php foreach ( $packages as $package ) :
                            $price    = get_post_meta( $package->ID, '_tp_price', true );
                            $currency = get_post_meta( $package->ID, '_tp_currency', true ) ?: 'USD';
                            $status_label = $package->post_status === 'publish' ? __( 'Publicado', 'travel-packages' ) : __( 'Borrador', 'travel-packages' );
                            $thumb = get_the_post_thumbnail_url( $package->ID, 'thumbnail' );
                            $gallery_raw = get_post_meta( $package->ID, '_tp_gallery', true );
                        $gallery_ids = $gallery_raw ? array_filter( array_map( 'intval', explode( ',', $gallery_raw ) ) ) : [];
                        $gallery_urls = [];
                        foreach ( $gallery_ids as $gallery_id ) {
                            $url = wp_get_attachment_image_url( $gallery_id, 'thumbnail' );
                            if ( $url ) {
                                $gallery_urls[] = [ 'id' => $gallery_id, 'url' => $url ];
                            }
                        }

                        $package_data = wp_json_encode([
                                'ID'            => $package->ID,
                                'post_title'    => $package->post_title,
                                'post_content'  => $package->post_content,
                                'post_status'   => $package->post_status,
                                'tp_short_desc' => get_post_meta( $package->ID, '_tp_short_desc', true ),
                                'tp_price'      => get_post_meta( $package->ID, '_tp_price', true ),
                                'tp_price_note' => get_post_meta( $package->ID, '_tp_price_note', true ),
                                'tp_currency'   => get_post_meta( $package->ID, '_tp_currency', true ) ?: 'USD',
                                'tp_slider'     => get_post_meta( $package->ID, '_tp_slider', true ),
                                'tp_featured'   => get_post_meta( $package->ID, '_tp_featured', true ),
                                'tp_featured_image' => get_post_thumbnail_id( $package->ID ) ?: '',
                                'tp_gallery'    => $gallery_urls,
                                'tp_dates'      => get_post_meta( $package->ID, '_tp_dates', true ),
                                'tp_tech_fields'=> get_post_meta( $package->ID, '_tp_tech_fields', true ),
                                'tp_custom_fields' => TP_CustomFields::get_package_data( $package->ID ),
                                'destinos'      => wp_get_post_terms( $package->ID, 'destino', ['fields' => 'slugs'] ),
                                'tipologias'    => wp_get_post_terms( $package->ID, 'tipologia', ['fields' => 'slugs'] ),
                            ]);
                        ?>
                        <button type="button" class="tp-package-card" data-package='<?php echo esc_attr( $package_data ); ?>'>
                            <div class="tp-package-card-thumb">
                                <?php if ( $thumb ) : ?>
                                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $package->post_title ); ?>">
                                <?php else : ?>
                                <span>🧳</span>
                                <?php endif; ?>
                            </div>
                            <div class="tp-package-card-body">
                                <strong><?php echo esc_html( $package->post_title ); ?></strong>
                                <span class="tp-package-card-meta"><?php echo esc_html( $status_label ); ?> · <?php echo esc_html( $currency . ' ' . number_format((float)$price,0,',','.')); ?></span>
                                <span class="tp-package-card-tags"><?php echo esc_html( implode(', ', wp_get_post_terms($package->ID,'destino', ['fields'=>'names']) ) ); ?></span>
                                <span class="tp-package-card-tags tp-tags-tipologia"><?php echo esc_html( implode(', ', wp_get_post_terms($package->ID,'tipologia', ['fields'=>'names']) ) ); ?></span>
                                <span class="tp-package-card-actions">
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_duplicate_package&tp_package_id=' . $package->ID ), 'tp_duplicate_package', 'tp_duplicate_nonce' ) ); ?>" class="tp-card-dup-link" title="<?php esc_attr_e( 'Duplicar paquete', 'travel-packages' ); ?>" onclick="event.stopPropagation();">📋 <?php _e( 'Duplicar', 'travel-packages' ); ?></a>
                                </span>
                            </div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php if ( $total_pages > 1 ) : ?>
                    <div class="tp-pagination">
                        <?php
                        $page_links = paginate_links( [
                            'base'      => add_query_arg( 'tp_paged', '%#%', $base_url ),
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
                <div class="tp-package-form">
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="tp-package-form">
                        <input type="hidden" name="action" value="tp_save_package">
                        <input type="hidden" name="tp_package_id" id="tp_package_id" value="0">
                        <input type="hidden" id="tp-selected-package-data" value="<?php echo esc_attr( wp_json_encode( $selected_package_data ?? [] ) ); ?>">
                        <?php wp_nonce_field( 'tp_package_save', 'tp_package_nonce' ); ?>

                        <div class="tp-panel">
                            <h2><?php _e('Detalles del paquete', 'travel-packages'); ?></h2>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_title"><?php _e('Título del paquete', 'travel-packages'); ?></label>
                                <input type="text" name="tp_title" id="tp_title" placeholder="<?php esc_attr_e('Ej: Tour por Europa 12 días', 'travel-packages'); ?>">
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label"><?php _e('Imagen principal', 'travel-packages'); ?></label>
                                <div class="tp-featured-wrap">
                                    <div id="tp-featured-preview-main"></div>
                                    <input type="hidden" name="tp_featured_image" id="tp_featured_image" value="">
                                    <p>
                                        <button type="button" class="button" id="tp-set-featured-main"><?php _e('Seleccionar imagen principal', 'travel-packages'); ?></button>
                                        <button type="button" class="button" id="tp-remove-featured-main"><?php _e('Eliminar imagen', 'travel-packages'); ?></button>
                                    </p>
                                </div>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_short_desc"><?php _e('Descripción corta', 'travel-packages'); ?></label>
                                <textarea name="tp_short_desc" id="tp_short_desc" rows="3" maxlength="200" placeholder="<?php esc_attr_e('Resumen atractivo del paquete...', 'travel-packages'); ?>"></textarea>
                                <div class="tp-char-count"><span id="tp-short-count">0</span>/200</div>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label"><?php _e('Descripción completa (HTML permitido)', 'travel-packages'); ?></label>
                                <textarea name="tp_content" id="tp_content" rows="8" placeholder="<?php esc_attr_e('Texto largo, HTML, videos embed, código...', 'travel-packages'); ?>" class="tp-html-textarea"></textarea>
                                <small><?php _e('Podés pegar HTML, iframe de videos, código, etc.', 'travel-packages'); ?></small>
                            </div>
                        </div>

                        <div class="tp-panel">
                            <h2><?php _e('Precio & Condiciones', 'travel-packages'); ?></h2>
                            <div class="tp-field-grid tp-two-columns">
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_currency"><?php _e('Moneda', 'travel-packages'); ?></label>
                                    <select name="tp_currency" id="tp_currency">
                                        <option value="USD">USD – Dólar</option>
                                        <option value="ARS">ARS – Peso Arg.</option>
                                        <option value="EUR">EUR – Euro</option>
                                        <option value="BRL">BRL – Real</option>
                                    </select>
                                </div>
                                <div class="tp-field-group">
                                    <label class="tp-label" for="tp_price"><?php _e('Precio por persona', 'travel-packages'); ?></label>
                                    <input type="number" name="tp_price" id="tp_price" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                            <div class="tp-field-group">
                                <label class="tp-label" for="tp_price_note"><?php _e('Nota de precio', 'travel-packages'); ?></label>
                                <input type="text" name="tp_price_note" id="tp_price_note" placeholder="<?php esc_attr_e('por persona en doble, impuestos incluidos...', 'travel-packages'); ?>">
                            </div>
                        </div>

                        <div class="tp-panel">
                            <h2><?php _e('Visibilidad', 'travel-packages'); ?></h2>
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
                        </div>

                        <div class="tp-panel">
                            <h2><?php _e('Destino y tipología', 'travel-packages'); ?></h2>
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
                        </div>

                        <div class="tp-panel">
                            <h2><?php _e('Fechas disponibles', 'travel-packages'); ?></h2>
                            <div id="tp-dates-list" class="tp-dates-wrap"></div>
                            <button type="button" id="tp-add-date" class="tp-btn tp-btn-secondary">
                                <span class="dashicons dashicons-plus-alt"></span> <?php _e('Agregar fecha', 'travel-packages'); ?>
                            </button>
                        </div>

                        <div class="tp-panel">
                            <h2><?php _e('Campos adicionales', 'travel-packages'); ?></h2>
                            <div id="tp-tech-fields" class="tp-tech-wrap"></div>
                            <button type="button" id="tp-add-tech-field" class="tp-btn tp-btn-secondary">
                                <span class="dashicons dashicons-plus-alt"></span> <?php _e('Agregar campo', 'travel-packages'); ?>
                            </button>
                        </div>

                        <div class="tp-panel">
                            <h2><?php _e('Galería', 'travel-packages'); ?></h2>
                            <div id="tp-gallery-preview" class="tp-gallery-grid"></div>
                            <input type="hidden" name="tp_gallery" id="tp_gallery_ids" value="">
                            <button type="button" id="tp-add-gallery" class="tp-btn tp-btn-secondary">
                                <span class="dashicons dashicons-plus-alt"></span> <?php _e('Agregar imágenes', 'travel-packages'); ?>
                            </button>
                        </div>

                        <div class="tp-panel">
                            <h2><?php _e('Campos Personalizados', 'travel-packages'); ?></h2>
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

                        <div class="tp-panel tp-panel-actions">
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

    public static function save_package() {
        if ( ! isset( $_POST['tp_package_nonce'] ) || ! wp_verify_nonce( $_POST['tp_package_nonce'], 'tp_package_save' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'No tienes permisos para guardar este paquete.', 'travel-packages' ) );
        }

        $package_id = isset( $_POST['tp_package_id'] ) ? intval( $_POST['tp_package_id'] ) : 0;
        $title      = isset( $_POST['tp_title'] ) ? sanitize_text_field( wp_unslash($_POST['tp_title']) ) : '';
        $content    = isset( $_POST['tp_content'] ) ? wp_kses_post( wp_unslash($_POST['tp_content']) ) : '';
        $status     = isset( $_POST['tp_post_status'] ) && in_array( $_POST['tp_post_status'], [ 'publish', 'draft' ], true ) ? $_POST['tp_post_status'] : 'draft';

        if ( empty( $title ) ) {
            wp_redirect( admin_url( 'admin.php?page=tp-packages&status=error' ) );
            exit;
        }

        $post_data = [
            'post_type'   => 'paquete_viaje',
            'post_title'  => $title,
            'post_content'=> $content,
            'post_status' => $status,
        ];

        if ( $package_id ) {
            $post_data['ID'] = $package_id;
            $package_id = wp_update_post( $post_data );
        } else {
            $package_id = wp_insert_post( $post_data );
        }

        if ( is_wp_error( $package_id ) ) {
            wp_die( $package_id->get_error_message() );
        }

        $meta_map = [
            'tp_short_desc' => '_tp_short_desc',
            'tp_price'      => '_tp_price',
            'tp_price_note' => '_tp_price_note',
            'tp_currency'   => '_tp_currency',
            'tp_gallery'    => '_tp_gallery',
        ];

        foreach ( $meta_map as $field => $meta_key ) {
            if ( isset( $_POST[ $field ] ) ) {
                update_post_meta( $package_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
            } else {
                if ( in_array( $meta_key, [ '_tp_slider', '_tp_featured' ], true ) ) {
                    update_post_meta( $package_id, $meta_key, '0' );
                }
            }
        }

        update_post_meta( $package_id, '_tp_slider', isset( $_POST['tp_slider'] ) ? '1' : '0' );
        update_post_meta( $package_id, '_tp_featured', isset( $_POST['tp_featured'] ) ? '1' : '0' );

        $tech_keys = isset( $_POST['tp_tech_key'] ) ? (array) $_POST['tp_tech_key'] : [];
        $tech_vals = isset( $_POST['tp_tech_val'] ) ? (array) $_POST['tp_tech_val'] : [];
        $tech_data = [];
        foreach ( $tech_keys as $index => $key ) {
            $k = sanitize_text_field( $key );
            $v = sanitize_text_field( $tech_vals[ $index ] ?? '' );
            if ( $k ) {
                $tech_data[] = [ 'key' => $k, 'val' => $v ];
            }
        }
        update_post_meta( $package_id, '_tp_tech_fields', $tech_data );

        // Dates: paired from/to fields
        $dates_from = isset( $_POST['tp_dates_from'] ) ? (array) $_POST['tp_dates_from'] : [];
        $dates_to   = isset( $_POST['tp_dates_to'] )   ? (array) $_POST['tp_dates_to']   : [];
        $dates_data = [];
        $max = max( count($dates_from), count($dates_to) );
        for ( $i = 0; $i < $max; $i++ ) {
            $from = isset($dates_from[$i]) ? sanitize_text_field( wp_unslash($dates_from[$i]) ) : '';
            $to   = isset($dates_to[$i])   ? sanitize_text_field( wp_unslash($dates_to[$i]) )   : '';
            if ( $from || $to ) {
                if ( $from && $to ) {
                    $from_ts = strtotime( str_replace('/', '-', $from) );
                    $to_ts   = strtotime( str_replace('/', '-', $to) );
                    if ( $from_ts && $to_ts && $to_ts < $from_ts ) {
                        $to = $from;
                    }
                }
                $dates_data[] = [ 'from' => $from, 'to' => $to ];
            }
        }
        // Legacy support
        if ( empty($dates_data) && isset($_POST['tp_dates']) ) {
            $legacy = (array) $_POST['tp_dates'];
            foreach ( $legacy as $d ) {
                $d = sanitize_text_field( wp_unslash($d) );
                if ( $d ) $dates_data[] = [ 'from' => $d, 'to' => '' ];
            }
        }
        update_post_meta( $package_id, '_tp_dates', $dates_data );

        // Custom fields
        TP_CustomFields::save_post_fields( $package_id );

        // Featured image from packages manager
        if ( isset( $_POST['tp_featured_image'] ) && $_POST['tp_featured_image'] !== '' ) {
            $fid = intval( $_POST['tp_featured_image'] );
            if ( $fid ) {
                set_post_thumbnail( $package_id, $fid );
            }
        }

        $destino = isset( $_POST['tp_destino'] ) ? sanitize_text_field( $_POST['tp_destino'] ) : '';
        $new_destino = isset( $_POST['tp_new_destino'] ) ? sanitize_text_field( $_POST['tp_new_destino'] ) : '';
        $destinos = [];

        if ( $destino ) {
            $destinos[] = $destino;
        }

        if ( $new_destino ) {
            $term = term_exists( $new_destino, 'destino' );
            if ( ! $term ) {
                $term = wp_insert_term( $new_destino, 'destino' );
            }
            if ( ! is_wp_error( $term ) ) {
                $destinos[] = is_array( $term ) ? $term['term_id'] : $term;
            }
        }

        if ( $destinos ) {
            wp_set_post_terms( $package_id, $destinos, 'destino', false );
        }

        $tipologia = isset( $_POST['tp_tipologia'] ) ? sanitize_text_field( $_POST['tp_tipologia'] ) : '';
        $new_tipologia = isset( $_POST['tp_new_tipologia'] ) ? sanitize_text_field( $_POST['tp_new_tipologia'] ) : '';
        $tipologias = [];

        if ( $tipologia ) {
            $tipologias[] = $tipologia;
        }

        if ( $new_tipologia ) {
            $term = term_exists( $new_tipologia, 'tipologia' );
            if ( ! $term ) {
                $term = wp_insert_term( $new_tipologia, 'tipologia' );
            }
            if ( ! is_wp_error( $term ) ) {
                $tipologias[] = is_array( $term ) ? $term['term_id'] : $term;
            }
        }

        if ( $tipologias ) {
            wp_set_post_terms( $package_id, $tipologias, 'tipologia', false );
        }

        $redirect_url = admin_url( 'admin.php?page=tp-packages&status=success' );
        if ( $package_id ) {
            $redirect_url = add_query_arg( 'package_id', $package_id, $redirect_url );
        }

        wp_redirect( $redirect_url );
        exit;
    }

    public static function duplicate_package() {
        if ( ! isset( $_GET['tp_duplicate_nonce'] ) || ! wp_verify_nonce( $_GET['tp_duplicate_nonce'], 'tp_duplicate_package' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        $original_id = isset( $_GET['tp_package_id'] ) ? intval( $_GET['tp_package_id'] ) : 0;
        if ( ! $original_id ) {
            wp_redirect( admin_url( 'admin.php?page=tp-packages' ) );
            exit;
        }

        $original = get_post( $original_id );
        if ( ! $original || $original->post_type !== 'paquete_viaje' ) {
            wp_redirect( admin_url( 'admin.php?page=tp-packages' ) );
            exit;
        }

        $new_slug = $original->post_name ? $original->post_name . '-copia' : '';
        $new_id = wp_insert_post([
            'post_type'    => 'paquete_viaje',
            'post_title'   => $original->post_title . ' (' . __( 'copia', 'travel-packages' ) . ')',
            'post_content' => $original->post_content,
            'post_status'  => $original->post_status,
            'post_name'    => $new_slug,
        ]);

        if ( is_wp_error( $new_id ) ) {
            wp_die( $new_id->get_error_message() );
        }

        // Copy all meta fields
        $meta_keys = [
            '_tp_short_desc', '_tp_price', '_tp_price_note', '_tp_currency',
            '_tp_slider', '_tp_featured', '_tp_gallery', '_tp_tech_fields', '_tp_dates',
        ];
        foreach ( $meta_keys as $key ) {
            $val = get_post_meta( $original_id, $key, true );
            if ( $val !== '' ) {
                update_post_meta( $new_id, $key, $val );
            }
        }

        // Copy custom fields
        $cf_defs = TP_CustomFields::get_definitions();
        foreach ( $cf_defs as $cf ) {
            $val = TP_CustomFields::get_field_value( $original_id, $cf['key'] );
            if ( $val !== '' ) {
                update_post_meta( $new_id, '_tp_cf_' . $cf['key'], $val );
            }
        }

        // Copy featured image
        $thumb_id = get_post_thumbnail_id( $original_id );
        if ( $thumb_id ) {
            set_post_thumbnail( $new_id, $thumb_id );
        }

        // Copy terms
        $terms = wp_get_post_terms( $original_id, 'destino', [ 'fields' => 'slugs' ] );
        if ( $terms && ! is_wp_error( $terms ) ) {
            wp_set_post_terms( $new_id, $terms, 'destino' );
        }

        $tipologia_terms = wp_get_post_terms( $original_id, 'tipologia', [ 'fields' => 'slugs' ] );
        if ( $tipologia_terms && ! is_wp_error( $tipologia_terms ) ) {
            wp_set_post_terms( $new_id, $tipologia_terms, 'tipologia' );
        }

        wp_redirect( admin_url( 'admin.php?page=tp-packages&status=duplicated' ) );
        exit;
    }

    public static function export_packages() {
        if ( ! isset( $_GET['tp_export_nonce'] ) || ! wp_verify_nonce( $_GET['tp_export_nonce'], 'tp_export_packages' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        $packages = get_posts([
            'post_type'      => 'paquete_viaje',
            'post_status'    => [ 'publish', 'draft' ],
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $data = [];
        foreach ( $packages as $p ) {
            $gallery_raw = get_post_meta( $p->ID, '_tp_gallery', true );
            $gallery_ids = $gallery_raw ? array_filter( array_map( 'intval', explode( ',', $gallery_raw ) ) ) : [];
            $gallery_urls = [];
            foreach ( $gallery_ids as $gid ) {
                $url = wp_get_attachment_image_url( $gid, 'full' );
                if ( $url ) $gallery_urls[] = $url;
            }

            $item = [
                'title'         => $p->post_title,
                'content'       => $p->post_content,
                'status'        => $p->post_status,
                'short_desc'    => get_post_meta( $p->ID, '_tp_short_desc', true ),
                'price'         => get_post_meta( $p->ID, '_tp_price', true ),
                'price_note'    => get_post_meta( $p->ID, '_tp_price_note', true ),
                'currency'      => get_post_meta( $p->ID, '_tp_currency', true ) ?: 'USD',
                'slider'        => get_post_meta( $p->ID, '_tp_slider', true ),
                'featured'      => get_post_meta( $p->ID, '_tp_featured', true ),
                'destinos'      => wp_get_post_terms( $p->ID, 'destino', [ 'fields' => 'names' ] ),
                'tipologias'    => wp_get_post_terms( $p->ID, 'tipologia', [ 'fields' => 'names' ] ),
                'featured_image_url' => get_the_post_thumbnail_url( $p->ID, 'full' ) ?: '',
                'gallery_urls'  => $gallery_urls,
                'tech_fields'   => get_post_meta( $p->ID, '_tp_tech_fields', true ),
                'dates'         => get_post_meta( $p->ID, '_tp_dates', true ),
                'custom_fields' => TP_CustomFields::get_package_data( $p->ID ),
            ];
            $data[] = $item;
        }

        header( 'Content-Type: application/json' );
        header( 'Content-Disposition: attachment; filename="paquetes-de-viaje-' . date( 'Y-m-d' ) . '.json"' );
        echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    public static function import_packages() {
        if ( ! isset( $_POST['tp_import_nonce'] ) || ! wp_verify_nonce( $_POST['tp_import_nonce'], 'tp_import_packages' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        if ( ! isset( $_FILES['tp_import_file'] ) || $_FILES['tp_import_file']['error'] !== UPLOAD_ERR_OK ) {
            wp_die( __( 'Error al subir el archivo.', 'travel-packages' ) );
        }

        $json = file_get_contents( $_FILES['tp_import_file']['tmp_name'] );
        $data = json_decode( $json, true );
        if ( ! is_array( $data ) ) {
            wp_die( __( 'Formato JSON inválido.', 'travel-packages' ) );
        }

        $count = 0;
        foreach ( $data as $item ) {
            $title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
            if ( empty( $title ) ) continue;

            $post_id = wp_insert_post([
                'post_type'    => 'paquete_viaje',
                'post_title'   => $title,
                'post_content' => isset( $item['content'] ) ? wp_kses_post( $item['content'] ) : '',
                'post_status'  => isset( $item['status'] ) && in_array( $item['status'], [ 'publish', 'draft' ] ) ? $item['status'] : 'draft',
            ]);

            if ( is_wp_error( $post_id ) ) continue;

            $meta_map = [
                '_tp_short_desc' => 'short_desc',
                '_tp_price'      => 'price',
                '_tp_price_note' => 'price_note',
                '_tp_currency'   => 'currency',
                '_tp_slider'     => 'slider',
                '_tp_featured'   => 'featured',
                '_tp_tech_fields'=> 'tech_fields',
                '_tp_dates'      => 'dates',
            ];
            foreach ( $meta_map as $meta_key => $item_key ) {
                if ( isset( $item[ $item_key ] ) ) {
                    $val = is_string( $item[ $item_key ] ) ? sanitize_text_field( $item[ $item_key ] ) : $item[ $item_key ];
                    update_post_meta( $post_id, $meta_key, $val );
                }
            }

            // Import custom fields
            if ( isset( $item['custom_fields'] ) && is_array( $item['custom_fields'] ) ) {
                foreach ( $item['custom_fields'] as $cf_key => $cf_val ) {
                    update_post_meta( $post_id, '_tp_cf_' . sanitize_key( $cf_key ), sanitize_text_field( $cf_val ) );
                }
            }

            // Import destinations
            if ( isset( $item['destinos'] ) && is_array( $item['destinos'] ) ) {
                $term_ids = [];
                foreach ( $item['destinos'] as $dest_name ) {
                    $term = term_exists( $dest_name, 'destino' );
                    if ( ! $term ) {
                        $term = wp_insert_term( $dest_name, 'destino' );
                    }
                    if ( ! is_wp_error( $term ) ) {
                        $term_ids[] = is_array( $term ) ? $term['term_id'] : $term;
                    }
                }
                if ( $term_ids ) {
                    wp_set_post_terms( $post_id, $term_ids, 'destino' );
                }
            }

            // Import tipologias
            if ( isset( $item['tipologias'] ) && is_array( $item['tipologias'] ) ) {
                $term_ids = [];
                foreach ( $item['tipologias'] as $tip_name ) {
                    $term = term_exists( $tip_name, 'tipologia' );
                    if ( ! $term ) {
                        $term = wp_insert_term( $tip_name, 'tipologia' );
                    }
                    if ( ! is_wp_error( $term ) ) {
                        $term_ids[] = is_array( $term ) ? $term['term_id'] : $term;
                    }
                }
                if ( $term_ids ) {
                    wp_set_post_terms( $post_id, $term_ids, 'tipologia' );
                }
            }

            $count++;
        }

        wp_redirect( admin_url( 'admin.php?page=tp-packages&status=imported&tp_imported=' . $count ) );
        exit;
    }

    public static function handle_delete_package() {
        if ( ! isset( $_GET['tp_action'] ) || $_GET['tp_action'] !== 'delete_package' ) {
            return;
        }

        if ( ! isset( $_GET['tp_delete_nonce'] ) || ! wp_verify_nonce( $_GET['tp_delete_nonce'], 'tp_delete_package' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }

        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_die( __( 'No tienes permisos para eliminar paquetes.', 'travel-packages' ) );
        }

        $post_id = ! empty( $_GET['tp_package_id'] ) ? (int) $_GET['tp_package_id'] : 0;
        if ( ! $post_id ) {
            wp_die( __( 'ID de paquete no proporcionado.', 'travel-packages' ) );
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'paquete_viaje' ) {
            wp_die( __( 'Paquete no encontrado.', 'travel-packages' ) );
        }

        wp_delete_post( $post_id, true );

        wp_redirect( admin_url( 'admin.php?page=tp-packages&status=deleted' ) );
        exit;
    }

    public static function redirect_native_editor() {
        if ( ! is_admin() || defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            return;
        }

        global $pagenow;
        if ( $pagenow === 'edit.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'paquete_viaje' ) {
            wp_safe_redirect( admin_url( 'admin.php?page=tp-packages' ) );
            exit;
        }

        if ( $pagenow === 'post-new.php' && isset( $_GET['post_type'] ) && $_GET['post_type'] === 'paquete_viaje' ) {
            wp_safe_redirect( admin_url( 'admin.php?page=tp-package-new' ) );
            exit;
        }

        if ( $pagenow === 'post.php' && isset( $_GET['post'] ) ) {
            $post_id = intval( $_GET['post'] );
            $post    = get_post( $post_id );
            if ( $post && $post->post_type === 'paquete_viaje' ) {
                wp_safe_redirect( admin_url( 'admin.php?page=tp-packages&package_id=' . $post_id ) );
                exit;
            }
        }
    }

    public static function render_shortcodes_page() {
        ?>
        <div class="wrap tp-admin-wrap">
            <div class="tp-admin-header">
                <div class="tp-header-left">
                    <span class="tp-logo-icon">📋</span>
                    <div>
                        <h1><?php _e('Guía de Shortcodes', 'travel-packages'); ?></h1>
                        <p><?php _e('Copiá y pegá estos shortcodes en cualquier página o widget.', 'travel-packages'); ?></p>
                    </div>
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

            </div>
        </div>
        <?php
    }
}
