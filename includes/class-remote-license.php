<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_Remote_License {

    private static $instance = null;
    private static $github_token = '';
    private static $github_owner = 'fntorre';
    private static $github_repo = 'travel';
    private static $github_file = 'plugin-config.json';
    private static $github_branch = 'main';
    private static $config_cache = null;
    private static $cache_transient = 'tp_remote_config';

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function init() {
        $instance = self::instance();
        add_action( 'admin_init', [ $instance, 'maybe_clear_cache' ] );
    }

    public static function is_licensed() {
        $config = self::get_config();
        return ! empty( $config ) && isset( $config->active ) && $config->active === true;
    }

    public static function get_config() {
        if ( null !== self::$config_cache ) {
            return self::$config_cache;
        }

        $cached = get_transient( self::$cache_transient );
        if ( false !== $cached ) {
            self::$config_cache = $cached;
            return $cached;
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/contents/%s?ref=%s',
            self::$github_owner,
            self::$github_repo,
            self::$github_file,
            self::$github_branch
        );

        $response = wp_remote_get( $api_url, [
            'timeout' => 15,
            'sslverify' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . self::$github_token,
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'Travel-Packages-Plugin',
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( 'TP Remote License Error: ' . $response->get_error_message() );
            self::$config_cache = false;
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        error_log( 'TP Remote License Response Code: ' . $code );
        error_log( 'TP Remote License Response Body: ' . substr( $body, 0, 500 ) );

        if ( $code !== 200 ) {
            self::$config_cache = false;
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body );

        if ( json_last_error() !== JSON_ERROR_NONE || empty( $data->content ) ) {
            self::$config_cache = false;
            return false;
        }

        $content = base64_decode( $data->content );
        $config = json_decode( $content );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            self::$config_cache = false;
            return false;
        }

        set_transient( self::$cache_transient, $config, HOUR_IN_SECONDS );
        self::$config_cache = $config;

        return $config;
    }

    public static function get_setting( $key, $default = null ) {
        $config = self::get_config();
        if ( ! empty( $config ) && isset( $config->$key ) ) {
            return $config->$key;
        }
        return $default;
    }

    public function maybe_clear_cache() {
        if ( isset( $_GET['tp_clear_license_cache'] ) && current_user_can( 'manage_options' ) ) {
            delete_transient( self::$cache_transient );
            self::$config_cache = null;
            wp_redirect( admin_url( 'admin.php?page=tp-license&cache_cleared=1' ) );
            exit;
        }
    }

    public static function get_status_html() {
        $licensed = self::is_licensed();
        $status = $licensed ? 'Conectado' : 'Sin conexión';
        $class = $licensed ? 'tp-connected' : 'tp-disconnected';

        return '<span class="' . $class . '">&#9679; ' . $status . '</span>';
    }
}
