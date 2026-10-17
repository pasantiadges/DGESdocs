<?php
/**
 * Clase principal del plugin que registra assets, shortcodes, 
 * endpoints REST y el panel de configuración en el admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Plugin {

 public static function init() {
        // Este módulo conecta la interfaz del shortcode con el crawler y la API REST.
        add_shortcode('dges_documentos', array(__CLASS__, 'shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function activate() {
        if (!get_option(DGES_Docs_API::OPTION_URL)) {
            add_option(DGES_Docs_API::OPTION_URL, DGES_Docs_API::default_url());
        }
    }

    public static function register_assets() {
        wp_register_style('dges-documentos', DGES_DOCS_URL . 'assets/css/docs.css', array(), DGES_DOCS_VERSION);
        wp_register_script('dges-documentos', DGES_DOCS_URL . 'assets/js/docs.js', array(), DGES_DOCS_VERSION, true);
    }

    public static function shortcode($atts) {
        // El frontend consulta los documentos por REST para poder filtrar sin recargar la página.
        wp_enqueue_style('dges-documentos');
        wp_enqueue_script('dges-documentos');
        wp_localize_script('dges-documentos', 'DGESDocumentos', array(
            'endpoint' => esc_url_raw(rest_url('dges-documentos/v1/documents')),
            'nonce' => wp_create_nonce('wp_rest'),
        ));

        $atts = shortcode_atts(array('title' => 'Documentos DGES'), $atts, 'dges_documentos');
        return '<section class="dges-documentos" data-dges-documentos><h2>' .
            esc_html($atts['title']) .
            '</h2><div class="dges-documentos-filters" data-filters></div>' .
            '<p class="dges-documentos-status" data-status aria-live="polite">Cargando documentos…</p>' .
            '<div class="dges-documentos-results" data-results></div></section>';
    }

    public static function register_routes() {
        register_rest_route('dges-documentos/v1', '/documents', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'rest_documents'),
            'permission_callback' => '__return_true',
            'args' => array(
                'search' => array('sanitize_callback' => 'sanitize_text_field'),
                'type' => array('sanitize_callback' => 'sanitize_key'),
                'category' => array('sanitize_callback' => 'sanitize_text_field'),
                'year' => array('sanitize_callback' => 'absint'),
                'document_type' => array('sanitize_callback' => 'sanitize_key'),
                'number' => array('sanitize_callback' => 'sanitize_text_field'),
                'refresh' => array('sanitize_callback' => 'rest_sanitize_boolean'),
            ),
        ));
    }

    public static function rest_documents(WP_REST_Request $request) {
        $items = DGES_Docs_API::get_documents((bool) $request->get_param('refresh'));
        if (is_wp_error($items)) {
            return $items;
        }
        $filtered = DGES_Docs_API::filter_documents($items, $request->get_params());
        return rest_ensure_response(array(
            'items' => $filtered,
            'total' => count($filtered),
            'facets' => self::facets($items),
        ));
    }

    private static function facets($items) {
        $types = array();
        $categories = array();
        $years = array();
        $document_types = array();
        $numbers = array();
        foreach ($items as $item) {
            if ($item['type']) {
                $types[$item['type']] = true;
            }
            if ($item['category']) {
                $categories[$item['category']] = true;
            }
            if ($item['year']) {
                $years[$item['year']] = true;
            }
            if (!empty($item['document_type'])) {
                $document_types[$item['document_type']] = true;
            }
            if (!empty($item['number'])) {
                $numbers[$item['number']] = true;
            }
        }
        ksort($types);
        ksort($categories);
        krsort($years);
        ksort($document_types);
        sort($numbers, SORT_NATURAL);
        return array(
            'types' => array_keys($types),
            'categories' => array_keys($categories),
            'years' => array_map('intval', array_keys($years)),
            'document_types' => array_keys($document_types),
            'numbers' => array_values($numbers),
        );
    }

    public static function admin_menu() {
        add_options_page('Documentos DGES', 'Documentos DGES', 'manage_options', 'dges-documentos', array(__CLASS__, 'settings_page'));
    }

    public static function register_settings() {
        register_setting('dges_documentos', DGES_Docs_API::OPTION_URL, array(
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => DGES_Docs_API::default_url(),
        ));
        add_action('update_option_' . DGES_Docs_API::OPTION_URL, array('DGES_Docs_API', 'clear_cache'));
    }

    public static function settings_page() {
        if (isset($_POST['dges_documentos_refresh']) && check_admin_referer('dges_documentos_refresh')) {
            DGES_Docs_API::clear_cache();
            echo '<div class="notice notice-success"><p>Caché actualizada.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Documentos DGES</h1>
            <form method="post" action="options.php">
                <?php settings_fields('dges_documentos'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>">URL de origen</label></th>
                        <td><input class="regular-text code" type="url" id="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>" name="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>" value="<?php echo esc_attr(DGES_Docs_API::get_source_url()); ?>" required></td>
                    </tr>
                </table>
                <?php submit_button('Guardar URL'); ?>
            </form>
            <form method="post">
                <?php wp_nonce_field('dges_documentos_refresh'); ?>
                <input type="hidden" name="dges_documentos_refresh" value="1">
                <?php submit_button('Vaciar caché y volver a importar', 'secondary'); ?>
            </form>
            <p>Usá <code>[dges_documentos]</code> para insertar el filtro en una página.</p>
        </div>
        <?php
    }
}
