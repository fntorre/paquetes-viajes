<?php
/**
 * Plugin Name: TourFlow
 * Description: TourFlow — Gestión de paquetes de viaje para agencias. Incluye slider, grilla destacada, shortcodes, reservas y ficha técnica personalizable.
 * Version:     2.7.2
 * Author:      TourFlow
 * Text Domain: travel-packages
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'TP_BRAND',         'TourFlow' );
define( 'TP_BRAND_TAGLINE', 'Gestión de paquetes de viaje' );
define( 'TP_VERSION',       '2.7.2' );
define( 'TP_BUILD',         '0.4.0' );
define( 'TP_PLUGIN_DIR',    plugin_dir_path( __FILE__ ) );
define( 'TP_PLUGIN_URL',    plugin_dir_url( __FILE__ ) );
define( 'TP_PLUGIN_FILE',   __FILE__ );

require_once TP_PLUGIN_DIR . 'admin/class-admin.php';
require_once TP_PLUGIN_DIR . 'admin/class-admin-handlers.php';
require_once TP_PLUGIN_DIR . 'includes/class-post-type.php';
require_once TP_PLUGIN_DIR . 'includes/class-package-data.php';
require_once TP_PLUGIN_DIR . 'includes/class-reservations.php';
require_once TP_PLUGIN_DIR . 'includes/class-settings.php';
require_once TP_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once TP_PLUGIN_DIR . 'includes/class-ajax.php';
require_once TP_PLUGIN_DIR . 'includes/class-templates.php';
require_once TP_PLUGIN_DIR . 'includes/class-custom-fields.php';

register_activation_hook(   __FILE__, [ 'TP_PostType', 'flush_rules' ] );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

add_action( 'plugins_loaded', 'tp_init' );
function tp_init() {
    load_plugin_textdomain( 'travel-packages', false, basename( dirname( __FILE__ ) ) . '/languages' );

    TP_Admin::init();

    TP_PostType::init();
    TP_Shortcodes::init();
    TP_Ajax::init();
    TP_Templates::init();
    TP_CustomFields::init();
    TP_Reservations::init();
    TP_Settings::init();
    TP_Admin_Handlers::init();
}
