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
        add_shortcode('dges_documentos', array(__CLASS__, 'shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    // Acción al activar el plugin: configura la URL por defecto si no existe.
    public static function activate() {
        if (!get_option(DGES_Documentos_API::OPTION_URL)) {
            add_option(DGES_Documentos_API::OPTION_URL, DGES_Documentos_API::default_url());
        }
    }

    // Registro de hojas de estilo y scripts de frontend.
    public static function register_assets() {
        wp_register_style('dges-documentos', DGES_DOCS_URL . 'assets/css/documentos.css', array(), DGES_DOCS_VERSION);
        wp_register_script('dges-documentos', DGES_DOCS_URL . 'assets/js/docs.js', array(), DGES_DOCS_VERSION, true);
    }

    // Shortcode [dges_documentos] que imprime el contenedor HTML en la web.
    public static function shortcode($atts) {
        wp_enqueue_style('dges-documentos');
        wp_enqueue_script('dges-documentos');
        wp_localize_script('dges-documentos', 'DGESDocumentos', array(
            'endpoint' => esc_url_raw(rest_url('dges-documentos/v1/documents')),
            'nonce' => wp_create_nonce('wp_rest'),
        ));

        return '<section class="dges-documentos" data-dges-documentos>' .
               '<h2>Documentos DGES</h2>' .
               '<div class="dges-documentos-filters" data-filters></div>' .
               '<p class="dges-documentos-status" data-status>Cargando documentos...</p>' .
               '<div class="dges-documentos-results" data-results></div>' .
               '</section>';
    }

    // Registro de la ruta REST API para que JavaScript consuma los datos de forma asíncrona.
    public static function register_routes() {
        register_rest_route('dges-documentos/v1', '/documents', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'rest_documents'),
            'permission_callback' => '__return_true',
        ));
    }

    // Callback de la API REST que devuelve los documentos filtrados.
    public static function rest_documents(WP_REST_Request $request) {
        $items = DGES_Documentos_API::get_documents(false);
        if (is_wp_error($items)) return $items;
        
        $filtered = DGES_Documentos_API::filter_documents($items, $request->get_params());
        return rest_ensure_response(array('items' => $filtered, 'total' => count($filtered)));
    }

    // Creación de la página de ajustes en el panel de administración de WordPress.
    public static function admin_menu() {
        add_options_page('Documentos DGES', 'Documentos DGES', 'manage_options', 'dges-documentos', array(__CLASS__, 'settings_page'));
    }

    public static function settings_page() {
        echo '<div class="wrap"><h1>Configuración de Documentos DGES</h1><p>Panel de control del plugin.</p></div>';
    }
}