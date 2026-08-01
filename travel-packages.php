<?php
/**
 * Plugin Name: Paquetes de Viaje
 * Plugin URI:  https://tuagencia.com
 * Description: Gestión completa de paquetes de viaje para agencias. Incluye slider, grilla destacada, shortcodes y ficha técnica personalizable.
 * Version:     2.0.0
 * Author:      Tu Agencia de Viajes
 * Text Domain: travel-packages
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TP_VERSION',     '2.0.3' );
define( 'TP_BUILD',       '0.1.2' );
define( 'TP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'TP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'TP_PLUGIN_FILE', __FILE__ );

require_once TP_PLUGIN_DIR . 'includes/class-post-type.php';
require_once TP_PLUGIN_DIR . 'includes/class-package-data.php';
require_once TP_PLUGIN_DIR . 'includes/class-meta-boxes.php';
require_once TP_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once TP_PLUGIN_DIR . 'includes/class-ajax.php';
require_once TP_PLUGIN_DIR . 'includes/class-templates.php';
require_once TP_PLUGIN_DIR . 'includes/class-custom-fields.php';
require_once TP_PLUGIN_DIR . 'admin/class-admin.php';
require_once TP_PLUGIN_DIR . 'admin/class-admin-handlers.php';

register_activation_hook(   __FILE__, [ 'TP_PostType', 'flush_rules' ] );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', 'tp_init' );
function tp_init() {
    load_plugin_textdomain( 'travel-packages', false, basename( dirname( __FILE__ ) ) . '/languages' );

    TP_PostType::init();
    TP_MetaBoxes::init();
    TP_Shortcodes::init();
    TP_Ajax::init();
    TP_Templates::init();
    TP_CustomFields::init();
    TP_Admin::init();
    TP_Admin_Handlers::init();
}
