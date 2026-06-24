<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class TP_CustomFields {

    const OPTION_KEY = 'tp_custom_field_defs';

    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_submenu_page' ], 20 );
        add_action( 'admin_post_tp_save_custom_fields', [ __CLASS__, 'save_definitions' ] );
        add_action( 'admin_post_tp_load_default_fields', [ __CLASS__, 'load_default_fields' ] );
        add_filter( 'tp_custom_field_types', [ __CLASS__, 'default_field_types' ] );
    }

    public static function default_field_types() {
        return [
            'text'     => __( 'Texto', 'travel-packages' ),
            'number'   => __( 'Número', 'travel-packages' ),
            'textarea' => __( 'Área de texto', 'travel-packages' ),
            'date'     => __( 'Fecha', 'travel-packages' ),
            'select'   => __( 'Selección', 'travel-packages' ),
            'checkbox' => __( 'Checkbox', 'travel-packages' ),
        ];
    }

    public static function default_definitions() {
        return [
            [
                'label'    => 'Traslado',
                'key'      => 'traslado',
                'type'     => 'text',
                'required' => false,
                'options'  => '',
            ],
            [
                'label'    => 'Hotel',
                'key'      => 'hotel',
                'type'     => 'text',
                'required' => false,
                'options'  => '',
            ],
            [
                'label'    => 'Itinerario',
                'key'      => 'itinerario',
                'type'     => 'textarea',
                'required' => false,
                'options'  => '',
            ],
        ];
    }

    public static function get_definitions() {
        $defs = get_option( self::OPTION_KEY, null );
        if ( $defs === null ) {
            $defs = self::default_definitions();
            update_option( self::OPTION_KEY, $defs );
        }
        return $defs;
    }

    public static function get_field_value( $post_id, $field_key ) {
        return get_post_meta( $post_id, '_tp_cf_' . sanitize_key( $field_key ), true );
    }

    public static function get_all_values( $post_id ) {
        $defs = self::get_definitions();
        $values = [];
        foreach ( $defs as $field ) {
            $key = $field['key'];
            $values[ $key ] = self::get_field_value( $post_id, $key );
        }
        return $values;
    }

    public static function add_submenu_page() {
        add_submenu_page(
            'tp-dashboard',
            __( 'Campos Personalizados', 'travel-packages' ),
            __( 'Campos Personalizados', 'travel-packages' ),
            'manage_options',
            'tp-custom-fields',
            [ __CLASS__, 'render_settings_page' ]
        );
    }

    public static function render_settings_page() {
        $definitions = self::get_definitions();
        $types = apply_filters( 'tp_custom_field_types', [] );
        ?>
        <div class="wrap tp-admin-wrap">
            <div class="tp-admin-header">
                <div class="tp-header-left">
                    <span class="tp-logo-icon">⚙</span>
                    <div>
                        <h1><?php _e( 'Campos Personalizados', 'travel-packages' ); ?></h1>
                        <p><?php _e( 'Definí campos adicionales que se mostrarán en todos los paquetes de viaje.', 'travel-packages' ); ?></p>
                    </div>
                </div>
            </div>

            <?php if ( isset( $_GET['status'] ) && $_GET['status'] === 'saved' ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php _e( 'Campos guardados correctamente.', 'travel-packages' ); ?></p></div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" id="tp-cf-form">
                <input type="hidden" name="action" value="tp_save_custom_fields">
                <?php wp_nonce_field( 'tp_save_custom_fields', 'tp_cf_nonce' ); ?>

                <div class="tp-cf-table-wrap" style="background:#fff;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,.08);overflow:hidden;">
                    <table class="wp-list-table widefat fixed striped" id="tp-cf-table">
                        <thead>
                            <tr>
                                <th style="width:30px;">#</th>
                                <th><?php _e( 'Etiqueta', 'travel-packages' ); ?></th>
                                <th><?php _e( 'Clave interna', 'travel-packages' ); ?></th>
                                <th style="width:140px;"><?php _e( 'Tipo', 'travel-packages' ); ?></th>
                                <th style="width:80px;"><?php _e( 'Requerido', 'travel-packages' ); ?></th>
                                <th><?php _e( 'Opciones (para selección)', 'travel-packages' ); ?></th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tp-cf-rows">
                            <?php if ( empty( $definitions ) ) : ?>
                            <tr class="tp-cf-empty">
                                <td colspan="7" style="text-align:center;color:#999;padding:32px;">
                                    <?php _e( 'No hay campos definidos. Hacé clic en "Agregar campo" para empezar.', 'travel-packages' ); ?>
                                </td>
                            </tr>
                            <?php else : ?>
                                <?php foreach ( $definitions as $i => $field ) : ?>
                                <tr class="tp-cf-row">
                                    <td class="tp-cf-order"><?php echo $i + 1; ?></td>
                                    <td><input type="text" name="tp_cf_def[label][<?php echo $i; ?>]" value="<?php echo esc_attr( $field['label'] ); ?>" class="tp-cf-label" placeholder="Ej: Duración" style="width:100%;"></td>
                                    <td><input type="text" name="tp_cf_def[key][<?php echo $i; ?>]" value="<?php echo esc_attr( $field['key'] ); ?>" class="tp-cf-key" placeholder="duracion" style="width:100%;"></td>
                                    <td>
                                        <select name="tp_cf_def[type][<?php echo $i; ?>]" class="tp-cf-type" style="width:100%;">
                                            <?php foreach ( $types as $type_val => $type_label ) : ?>
                                            <option value="<?php echo esc_attr( $type_val ); ?>" <?php selected( $field['type'], $type_val ); ?>><?php echo esc_html( $type_label ); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="text-align:center;">
                                        <input type="hidden" name="tp_cf_def[required][<?php echo $i; ?>]" value="0">
                                        <input type="checkbox" name="tp_cf_def[required][<?php echo $i; ?>]" value="1" <?php checked( ! empty( $field['required'] ) ); ?>>
                                    </td>
                                    <td><input type="text" name="tp_cf_def[options][<?php echo $i; ?>]" value="<?php echo esc_attr( $field['options'] ?? '' ); ?>" class="tp-cf-options" placeholder="op1, op2, op3" style="width:100%;"></td>
                                    <td><button type="button" class="tp-cf-remove button-link-delete" title="<?php esc_attr_e( 'Eliminar campo', 'travel-packages' ); ?>">✕</button></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                    <button type="button" id="tp-cf-add-row" class="button">+ <?php _e( 'Agregar campo', 'travel-packages' ); ?></button>
                    <button type="submit" class="button button-primary"><?php _e( 'Guardar campos', 'travel-packages' ); ?></button>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=tp_load_default_fields' ), 'tp_load_default_fields' ) ); ?>" class="button"><?php _e( 'Cargar campos sugeridos', 'travel-packages' ); ?></a>
                </div>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var rowIndex = <?php echo count( $definitions ); ?>;

            $('#tp-cf-add-row').on('click', function() {
                rowIndex++;
                var types = <?php echo wp_json_encode( $types ); ?>;
                var typeOptions = '';
                $.each(types, function(val, label) {
                    typeOptions += '<option value="' + val + '">' + label + '</option>';
                });
                var row = '<tr class="tp-cf-row">' +
                    '<td class="tp-cf-order">' + rowIndex + '</td>' +
                    '<td><input type="text" name="tp_cf_def[label][' + rowIndex + ']" class="tp-cf-label" placeholder="Ej: Duración" style="width:100%;"></td>' +
                    '<td><input type="text" name="tp_cf_def[key][' + rowIndex + ']" class="tp-cf-key" placeholder="duracion" style="width:100%;"></td>' +
                    '<td><select name="tp_cf_def[type][' + rowIndex + ']" class="tp-cf-type" style="width:100%;">' + typeOptions + '</select></td>' +
                    '<td style="text-align:center;"><input type="hidden" name="tp_cf_def[required][' + rowIndex + ']" value="0"><input type="checkbox" name="tp_cf_def[required][' + rowIndex + ']" value="1"></td>' +
                    '<td><input type="text" name="tp_cf_def[options][' + rowIndex + ']" class="tp-cf-options" placeholder="op1, op2, op3" style="width:100%;"></td>' +
                    '<td><button type="button" class="tp-cf-remove button-link-delete" title="Eliminar campo">✕</button></td>' +
                    '</tr>';
                $('#tp-cf-rows .tp-cf-empty').remove();
                $('#tp-cf-rows').append(row);
            });

            $(document).on('click', '.tp-cf-remove', function() {
                $(this).closest('tr').remove();
            });

            $(document).on('input', '.tp-cf-label', function() {
                var $row = $(this).closest('tr');
                var $key = $row.find('.tp-cf-key');
                if ( ! $key.data('manual') ) {
                    var label = $(this).val().toLowerCase()
                        .replace(/[^a-z0-9]+/g, '_')
                        .replace(/^_|_$/g, '');
                    $key.val(label);
                }
            });

            $(document).on('input', '.tp-cf-key', function() {
                $(this).data('manual', true);
            });
        });
        </script>
        <?php
    }

    public static function save_definitions() {
        if ( ! isset( $_POST['tp_cf_nonce'] ) || ! wp_verify_nonce( $_POST['tp_cf_nonce'], 'tp_save_custom_fields' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        $labels   = isset( $_POST['tp_cf_def']['label'] )   ? (array) $_POST['tp_cf_def']['label']   : [];
        $keys     = isset( $_POST['tp_cf_def']['key'] )     ? (array) $_POST['tp_cf_def']['key']     : [];
        $types    = isset( $_POST['tp_cf_def']['type'] )    ? (array) $_POST['tp_cf_def']['type']    : [];
        $required = isset( $_POST['tp_cf_def']['required'] ) ? (array) $_POST['tp_cf_def']['required'] : [];
        $options  = isset( $_POST['tp_cf_def']['options'] ) ? (array) $_POST['tp_cf_def']['options']  : [];

        $definitions = [];
        foreach ( $labels as $i => $label ) {
            $label = sanitize_text_field( wp_unslash( $label ) );
            $key   = isset( $keys[$i] ) ? sanitize_key( $keys[$i] ) : '';
            if ( empty( $label ) || empty( $key ) ) continue;

            $type = isset( $types[$i] ) ? sanitize_key( $types[$i] ) : 'text';
            $valid_types = array_keys( apply_filters( 'tp_custom_field_types', [] ) );
            if ( ! in_array( $type, $valid_types, true ) ) $type = 'text';

            $definitions[] = [
                'label'    => $label,
                'key'      => $key,
                'type'     => $type,
                'required' => ! empty( $required[ $i ] ),
                'options'  => isset( $options[$i] ) ? sanitize_text_field( wp_unslash( $options[$i] ) ) : '',
            ];
        }

        update_option( self::OPTION_KEY, $definitions );

        wp_redirect( admin_url( 'admin.php?page=tp-custom-fields&status=saved' ) );
        exit;
    }

    public static function load_default_fields() {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'tp_load_default_fields' ) ) {
            wp_die( __( 'Nonce inválido.', 'travel-packages' ) );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos.', 'travel-packages' ) );
        }

        update_option( self::OPTION_KEY, self::default_definitions() );

        wp_redirect( admin_url( 'admin.php?page=tp-custom-fields&status=saved' ) );
        exit;
    }

    public static function render_field_input( $field, $value = '', $name_prefix = 'tp_cf' ) {
        $key   = esc_attr( $field['key'] );
        $label = esc_html( $field['label'] );
        $type  = $field['type'];
        $name  = $name_prefix . '_' . $key;
        $id    = 'tp-cf-' . $key;
        $required_attr = ! empty( $field['required'] ) ? ' required' : '';
        $output = '';

        $output .= '<div class="tp-field-group tp-cf-field" data-key="' . $key . '">';
        $output .= '<label class="tp-label" for="' . $id . '">' . $label . ( ! empty( $field['required'] ) ? ' <span class="tp-required">*</span>' : '' ) . '</label>';

        switch ( $type ) {
            case 'textarea':
                $output .= '<textarea name="' . $name . '" id="' . $id . '" rows="4" placeholder="' . $label . '"' . $required_attr . '>' . esc_textarea( $value ) . '</textarea>';
                break;

            case 'number':
                $output .= '<input type="number" name="' . $name . '" id="' . $id . '" value="' . esc_attr( $value ) . '" step="any"' . $required_attr . '>';
                break;

            case 'date':
                $output .= '<input type="text" name="' . $name . '" id="' . $id . '" value="' . esc_attr( $value ) . '" class="tp-datepicker" placeholder="dd/mm/aaaa" readonly' . $required_attr . '>';
                break;

            case 'select':
                $options_list = array_map( 'trim', explode( ',', $field['options'] ?? '' ) );
                $output .= '<select name="' . $name . '" id="' . $id . '"' . $required_attr . '>';
                $output .= '<option value="">' . __( '-- Seleccionar --', 'travel-packages' ) . '</option>';
                foreach ( $options_list as $opt ) {
                    if ( empty( $opt ) ) continue;
                    $selected = selected( $value, $opt, false );
                    $output .= '<option value="' . esc_attr( $opt ) . '"' . $selected . '>' . esc_html( $opt ) . '</option>';
                }
                $output .= '</select>';
                break;

            case 'checkbox':
                $checked = ! empty( $value ) ? ' checked' : '';
                $output .= '<label class="tp-cf-checkbox-label">';
                $output .= '<input type="hidden" name="' . $name . '" value="0">';
                $output .= '<input type="checkbox" name="' . $name . '" id="' . $id . '" value="1"' . $checked . $required_attr . '> ';
                $output .= '<span>' . $label . '</span>';
                $output .= '</label>';
                break;

            default: // text
                $output .= '<input type="text" name="' . $name . '" id="' . $id . '" value="' . esc_attr( $value ) . '" placeholder="' . $label . '"' . $required_attr . '>';
                break;
        }

        $output .= '</div>';
        return $output;
    }

    public static function save_post_fields( $post_id ) {
        if ( ! $post_id || $post_id < 1 ) return;

        $defs = self::get_definitions();
        if ( empty( $defs ) ) return;

        foreach ( $defs as $field ) {
            $key      = $field['key'];
            $meta_key = '_tp_cf_' . $key;
            $post_key = 'tp_cf_' . $key;

            $value = isset( $_POST[ $post_key ] ) ? $_POST[ $post_key ] : null;

            if ( $value !== null ) {
                if ( $field['type'] === 'textarea' ) {
                    $value = sanitize_textarea_field( wp_unslash( $value ) );
                } elseif ( $field['type'] === 'checkbox' ) {
                    $value = $value === '1' ? '1' : '0';
                } else {
                    $value = sanitize_text_field( wp_unslash( $value ) );
                }
                update_post_meta( $post_id, $meta_key, $value );
            } elseif ( $field['type'] === 'checkbox' ) {
                update_post_meta( $post_id, $meta_key, '0' );
            }
        }
    }

    public static function get_package_data( $post_id ) {
        $defs = self::get_definitions();
        $data = [];
        foreach ( $defs as $field ) {
            $data[ $field['key'] ] = self::get_field_value( $post_id, $field['key'] );
        }
        return $data;
    }
}
