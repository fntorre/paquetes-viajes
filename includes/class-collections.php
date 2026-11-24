<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Colecciones reutilizables de paquetes. */
class TP_Collections {
    const POST_TYPE = 'tp_coleccion';
    const META_CODE = '_tp_collection_code';
    const META_ITEMS = '_tp_collection_items';

    public static function init() {
        add_action( 'init', [ __CLASS__, 'register_post_type' ] );
        add_action( 'admin_post_tp_save_collection', [ __CLASS__, 'save_collection' ] );
        add_action( 'admin_post_tp_delete_collection', [ __CLASS__, 'delete_collection' ] );
        add_shortcode( 'tp_coleccion', [ __CLASS__, 'render_shortcode' ] );
    }

    public static function register_post_type() {
        register_post_type( self::POST_TYPE, [
            'labels' => [ 'name' => 'Colecciones', 'singular_name' => 'Colección' ],
            'public' => false, 'show_ui' => false, 'show_in_menu' => false,
            'supports' => [ 'title', 'editor' ], 'capability_type' => 'post',
            'map_meta_cap' => true,
        ] );
    }

    public static function get_code( $id ) {
        $code = get_post_meta( $id, self::META_CODE, true );
        if ( ! $code ) {
            $number = (int) get_option( 'tp_collection_sequence', 0 ) + 1;
            update_option( 'tp_collection_sequence', $number, false );
            $code = 'COL-' . str_pad( (string) $number, 4, '0', STR_PAD_LEFT );
            update_post_meta( $id, self::META_CODE, $code );
        }
        return $code;
    }

    public static function save_collection() {
        if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['tp_collection_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tp_collection_nonce'] ) ), 'tp_save_collection' ) ) {
            wp_die( 'No se pudo validar el formulario.' );
        }
        $id = isset( $_POST['collection_id'] ) ? absint( $_POST['collection_id'] ) : 0;
        if ( $id && ( get_post_type( $id ) !== self::POST_TYPE || ! current_user_can( 'edit_post', $id ) ) ) wp_die( 'No tenés permisos para editar esta colección.' );
        $title = isset( $_POST['collection_title'] ) ? sanitize_text_field( wp_unslash( $_POST['collection_title'] ) ) : '';
        if ( $title === '' ) wp_die( 'Ingresá un nombre para la colección.' );
        $description = isset( $_POST['collection_description'] ) ? wp_kses_post( wp_unslash( $_POST['collection_description'] ) ) : '';
        $status = isset( $_POST['collection_status'] ) && $_POST['collection_status'] === 'publish' ? 'publish' : 'draft';
        $post_id = wp_insert_post( [ 'ID' => $id, 'post_type' => self::POST_TYPE, 'post_status' => $status, 'post_title' => $title, 'post_content' => $description ], true );
        if ( is_wp_error( $post_id ) ) wp_die( esc_html( $post_id->get_error_message() ) );
        self::get_code( $post_id );
        $positions = isset( $_POST['package_order'] ) && is_array( $_POST['package_order'] ) ? wp_unslash( $_POST['package_order'] ) : [];
        $chosen = isset( $_POST['package_selected'] ) && is_array( $_POST['package_selected'] ) ? array_map( 'absint', wp_unslash( $_POST['package_selected'] ) ) : [];
        $items = [];
        foreach ( $positions as $package_id => $position ) {
            if ( ! in_array( absint( $package_id ), $chosen, true ) ) continue;
            $package_id = absint( $package_id );
            if ( $package_id && get_post_type( $package_id ) === 'paquete_viaje' && current_user_can( 'edit_post', $package_id ) ) $items[ $package_id ] = max( 0, absint( $position ) );
        }
        asort( $items, SORT_NUMERIC );
        update_post_meta( $post_id, self::META_ITEMS, array_values( array_map( 'absint', array_keys( $items ) ) ) );
        wp_safe_redirect( admin_url( 'admin.php?page=tp-collections&collection_id=' . $post_id . '&status=saved' ) );
        exit;
    }

    public static function delete_collection() {
        $id = isset( $_GET['collection_id'] ) ? absint( $_GET['collection_id'] ) : 0;
        if ( ! $id || get_post_type( $id ) !== self::POST_TYPE || ! current_user_can( 'delete_post', $id ) || ! check_admin_referer( 'tp_delete_collection_' . $id ) ) wp_die( 'No se pudo eliminar la colección.' );
        wp_trash_post( $id );
        wp_safe_redirect( admin_url( 'admin.php?page=tp-collections&status=deleted' ) );
        exit;
    }

    public static function render_admin_page() {
        if ( ! current_user_can( 'edit_posts' ) ) return;
        $id = isset( $_GET['collection_id'] ) ? absint( $_GET['collection_id'] ) : 0;
        $collection = $id ? get_post( $id ) : null;
        if ( $collection && $collection->post_type !== self::POST_TYPE ) $collection = null;
        $editing = (bool) $collection;
        $selected = $editing ? (array) get_post_meta( $id, self::META_ITEMS, true ) : [];
        $package_args = [ 'post_type' => 'paquete_viaje', 'post_status' => [ 'publish', 'draft', 'pending', 'private' ], 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ];
        if ( ! current_user_can( 'edit_others_posts' ) ) $package_args['author'] = get_current_user_id();
        $packages = get_posts( $package_args );
        $collections = get_posts( [ 'post_type' => self::POST_TYPE, 'post_status' => [ 'publish', 'draft' ], 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ] );
        ?>
        <div class="wrap tp-admin-wrap tp-collections-wrap">
            <div class="tp-admin-header"><div class="tp-header-left"><h1>Colecciones</h1><p>Organizá paquetes en grupos reutilizables para mostrarlos en distintas secciones del sitio.</p></div>
            <?php if ( $editing ) : ?><a class="tp-btn tp-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=tp-collections' ) ); ?>">Volver al listado</a><?php else : ?><a class="tp-btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=tp-collections&new=1' ) ); ?>">+ Nueva colección</a><?php endif; ?></div>
            <?php if ( isset( $_GET['status'] ) && $_GET['status'] === 'saved' ) : ?><div class="notice notice-success"><p>Colección guardada correctamente.</p></div><?php endif; ?>
            <?php if ( $editing || isset( $_GET['new'] ) ) : ?>
                <form class="tp-collection-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="tp_save_collection"><input type="hidden" name="collection_id" value="<?php echo esc_attr( $id ); ?>"><?php wp_nonce_field( 'tp_save_collection', 'tp_collection_nonce' ); ?>
                    <div class="tp-collection-fields"><label>Nombre de la colección<input type="text" name="collection_title" required value="<?php echo esc_attr( $editing ? $collection->post_title : '' ); ?>" placeholder="Ej. Escapadas de invierno"></label>
                    <label>Estado<select name="collection_status"><option value="publish" <?php selected( $editing ? $collection->post_status : 'publish', 'publish' ); ?>>Activa</option><option value="draft" <?php selected( $editing ? $collection->post_status : '', 'draft' ); ?>>Borrador</option></select></label>
                    <label class="tp-collection-description">Descripción<textarea name="collection_description" rows="3" placeholder="Una breve presentación (opcional)"><?php echo esc_textarea( $editing ? $collection->post_content : '' ); ?></textarea></label></div>
                    <?php if ( $editing ) : ?><p class="description">Código de colección: <strong><?php echo esc_html( self::get_code( $id ) ); ?></strong></p><?php endif; ?>
                    <h2>Paquetes de la colección</h2><p class="description">Marcá los paquetes e indicá su posición. Los números más bajos aparecen primero. Un paquete puede pertenecer a varias colecciones.</p>
                    <div class="tp-collection-packages">
                    <?php if ( $packages ) : foreach ( $packages as $package ) : TP_Collections::ensure_package_sku( $package->ID ); $pos = array_search( $package->ID, $selected, true ); ?>
                        <label class="tp-collection-package"><input type="checkbox" class="tp-collection-check" name="package_selected[]" value="<?php echo esc_attr( $package->ID ); ?>" <?php checked( $pos !== false ); ?>><span class="tp-collection-package-title"><?php echo esc_html( $package->post_title ); ?><small><?php echo esc_html( get_post_meta( $package->ID, '_tp_sku', true ) ?: 'SKU pendiente al guardar el paquete' ); ?> · <?php echo esc_html( get_post_status_object( $package->post_status )->label ); ?></small></span><span class="tp-collection-position"><input type="number" min="1" name="package_order[<?php echo esc_attr( $package->ID ); ?>]" value="<?php echo esc_attr( $pos !== false ? $pos + 1 : '' ); ?>" placeholder="—" aria-label="Orden de <?php echo esc_attr( $package->post_title ); ?>"></span></label>
                    <?php endforeach; else : ?><p>Todavía no hay paquetes cargados.</p><?php endif; ?>
                    </div>
                    <p><button class="button button-primary button-large" type="submit">Guardar colección</button></p>
                </form>
            <?php else : ?>
                <div class="tp-collections-list"><div class="tp-collections-list-head"><strong>Nombre</strong><strong>Código</strong><strong>Paquetes</strong><strong>Estado</strong><strong></strong></div>
                <?php if ( $collections ) : foreach ( $collections as $item ) : $item_ids = (array) get_post_meta( $item->ID, self::META_ITEMS, true ); ?>
                    <div class="tp-collections-list-row"><strong><?php echo esc_html( $item->post_title ); ?></strong><code><?php echo esc_html( self::get_code( $item->ID ) ); ?></code><span><?php echo count( $item_ids ); ?></span><span><?php echo $item->post_status === 'publish' ? 'Activa' : 'Borrador'; ?></span><span><a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-collections&collection_id=' . $item->ID ) ); ?>">Editar</a> · <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_delete_collection&collection_id=' . $item->ID ), 'tp_delete_collection_' . $item->ID ) ); ?>" onclick="return confirm('¿Enviar esta colección a la papelera?')">Eliminar</a></span></div>
                <?php endforeach; else : ?><p class="tp-collections-empty">Todavía no creaste colecciones. Creá la primera para empezar a agrupar paquetes.</p><?php endif; ?></div>
            <?php endif; ?>
        </div>
        <script>document.addEventListener('change',function(e){if(!e.target.classList.contains('tp-collection-check'))return;var row=e.target.closest('.tp-collection-package'),field=row.querySelector('.tp-collection-position input');if(e.target.checked&&!field.value)field.value='99';if(!e.target.checked)field.value='';});</script>
        <?php
    }

    public static function ensure_package_sku( $post_id ) {
        if ( get_post_type( $post_id ) !== 'paquete_viaje' || get_post_meta( $post_id, '_tp_sku', true ) ) return;
        $number = (int) get_option( 'tp_package_sku_sequence', 0 ) + 1;
        update_option( 'tp_package_sku_sequence', $number, false );
        update_post_meta( $post_id, '_tp_sku', 'TUR-' . str_pad( (string) $number, 6, '0', STR_PAD_LEFT ) );
    }

    public static function render_shortcode( $atts ) {
        $atts = shortcode_atts( [ 'codigo' => '', 'vista' => 'grilla', 'columnas' => 3, 'limite' => 12, 'carousel' => 'false' ], $atts, 'tp_coleccion' );
        $code = strtoupper( sanitize_text_field( $atts['codigo'] ) );
        if ( ! $code ) return current_user_can( 'edit_posts' ) ? '<p class="tp-no-results">Indicá el código de una colección.</p>' : '';
        $collections = get_posts( [ 'post_type' => self::POST_TYPE, 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => self::META_CODE, 'meta_value' => $code ] );
        if ( ! $collections ) return current_user_can( 'edit_posts' ) ? '<p class="tp-no-results">No se encontró la colección ' . esc_html( $code ) . '.</p>' : '';
        $collection = $collections[0]; $ids = array_slice( array_map( 'absint', (array) get_post_meta( $collection->ID, self::META_ITEMS, true ) ), 0, max( 1, absint( $atts['limite'] ) ) );
        if ( ! $ids ) return '';
        $posts = get_posts( [ 'post_type' => 'paquete_viaje', 'post_status' => 'publish', 'post__in' => $ids, 'orderby' => 'post__in', 'numberposts' => -1 ] );
        if ( ! $posts ) return '';
        $columns = min( 4, max( 1, absint( $atts['columnas'] ) ) ); $carousel = $atts['vista'] === 'carrusel' || $atts['carousel'] === 'true';
        wp_enqueue_style( 'tp-public' ); wp_enqueue_script( 'tp-public' );
        ob_start(); ?>
        <section class="tp-collection-public"><div class="tp-collection-public-heading"><h2><?php echo esc_html( $collection->post_title ); ?></h2><?php if ( $collection->post_content ) : ?><p><?php echo esc_html( wp_strip_all_tags( $collection->post_content ) ); ?></p><?php endif; ?></div>
        <?php if ( $carousel ) : $carousel_id = 'tp-collection-carousel-' . (int) $collection->ID . '-' . wp_rand( 100, 9999 ); ?>
            <div class="tp-grid-carousel tp-collection-carousel" id="<?php echo esc_attr( $carousel_id ); ?>" data-desktop="<?php echo esc_attr( $columns ); ?>" data-movile="1"><div class="tp-grid-carousel-track">
                <?php foreach ( $posts as $package ) : ?><div class="tp-grid-carousel-item"><?php echo TP_Shortcodes::render_collection_card( $package->ID ); ?></div><?php endforeach; ?>
            </div><button class="tp-carousel-prev" aria-label="Anterior">‹</button><button class="tp-carousel-next" aria-label="Siguiente">›</button><div class="tp-carousel-dots"></div></div>
        <?php else : ?><div class="tp-grid tp-collection-grid tp-cols-<?php echo esc_attr( $columns ); ?>">
            <?php foreach ( $posts as $package ) : echo TP_Shortcodes::render_collection_card( $package->ID ); endforeach; ?>
        </div><?php endif; ?>
        </section><?php return ob_get_clean();
    }

    private static function render_package_card( $id ) {
        $price = TP_Package_Data::get_best_price( $id ); $desc = get_post_meta( $id, '_tp_short_desc', true ); $image = get_the_post_thumbnail_url( $id, 'large' );
        ob_start(); ?><article class="tp-card"><a class="tp-card-image-link" href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php if ( $image ) : ?><img class="tp-card-img" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( get_the_title( $id ) ); ?>" loading="lazy"><?php else : ?><div class="tp-card-img-placeholder">✈</div><?php endif; ?></a><div class="tp-card-body"><h3 class="tp-card-title"><a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></h3><?php if ( $desc ) : ?><p class="tp-card-desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?><?php if ( $price ) : ?><div class="tp-card-price"><span><?php echo esc_html( TP_Package_Data::currency_symbol( $price['currency'] ) . ' ' . number_format( (float) $price['price'], 0, ',', '.' ) ); ?></span></div><?php endif; ?><div class="tp-card-actions"><a class="tp-btn-card" href="<?php echo esc_url( get_permalink( $id ) ); ?>">Ver detalles</a></div></div></article><?php return ob_get_clean();
    }
}
