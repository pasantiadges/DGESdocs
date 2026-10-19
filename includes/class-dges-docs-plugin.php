<?php
/**
 * Orquestador: shortcodes, REST, assets. Sin HTML ni admin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Plugin {

    public static function init() {
        add_shortcode('dges_documentos', array(__CLASS__, 'shortcode'));
        add_shortcode('dges_documentos_destacados', array(__CLASS__, 'shortcode_destacados'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'register_assets'));
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
        DGES_Docs_Settings::init();
    }

    public static function activate() {
        if (!get_option(DGES_Docs_API::OPTION_URL)) {
            add_option(DGES_Docs_API::OPTION_URL, DGES_Docs_API::default_url());
        }
        if (!get_option('dges_documentos_limit_destacados')) {
            add_option('dges_documentos_limit_destacados', 4);
        }
        if (!get_option('dges_documentos_per_page')) {
            add_option('dges_documentos_per_page', 12);
        }
    }

    public static function register_assets() {
        wp_register_style('dges-documentos', DGES_DOCS_URL . 'assets/css/docs.css', array(), DGES_DOCS_VERSION);

        wp_register_script(
            'dges-documentos',
            DGES_DOCS_URL . 'assets/js/docs.js',
            array(),
            DGES_DOCS_VERSION,
            true
        );

        add_filter('script_loader_tag', function ($tag, $handle) {
            if ($handle !== 'dges-documentos') return $tag;
            return str_replace('<script ', '<script type="module" ', $tag);
        }, 10, 2);
    }

    public static function shortcode($atts) {
        wp_enqueue_style('dges-documentos');
        wp_enqueue_script('dges-documentos');

        $per_page = absint(get_option('dges_documentos_per_page', 12));
        if ($per_page < 1) $per_page = 12;

        wp_localize_script('dges-documentos', 'DGESDocumentos', array(
            'endpoint' => esc_url_raw(rest_url('dges-documentos/v1/documents')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'perPage'  => $per_page,
            'i18n'     => array(
                'loading'     => 'Cargando documentos…',
                'noResults'   => 'No se encontraron documentos.',
                'loadMore'    => 'Ver más',
                'loadingMore' => 'Cargando más…',
                'error'       => 'Ocurrió un error al cargar los documentos.',
            ),
        ));

        $atts = shortcode_atts(array('title' => 'Documentos DGES'), $atts, 'dges_documentos');
        return DGES_Docs_Render::shortcode_buscador($atts['title']);
    }

    public static function shortcode_destacados($atts) {
        wp_enqueue_style('dges-documentos');

        $items = DGES_Docs_API::get_documents();
        if (is_wp_error($items)) {
            return '<p>No se pudieron cargar los documentos destacados.</p>';
        }

        $limit = absint(get_option('dges_documentos_limit_destacados', 4));
        if ($limit < 1) $limit = 4;
        $destacados = array_slice($items, 0, $limit);

        $atts = shortcode_atts(array('title' => 'Documentos Destacados'), $atts, 'dges_documentos_destacados');
        return DGES_Docs_Render::shortcode_destacados($destacados, $atts['title']);
    }

    public static function register_routes() {
        register_rest_route('dges-documentos/v1', '/documents', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'rest_documents'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'txtbusqueda'   => array('sanitize_callback' => 'sanitize_text_field'),
                'categoria'     => array('sanitize_callback' => 'sanitize_text_field'),
                'anio'          => array('sanitize_callback' => 'absint'),
                'type'          => array('sanitize_callback' => 'sanitize_key'),
                'document_type' => array('sanitize_callback' => 'sanitize_key'),
                'number'        => array('sanitize_callback' => 'sanitize_text_field'),
                'refresh'       => array('sanitize_callback' => 'rest_sanitize_boolean'),
                'page'          => array('sanitize_callback' => 'absint', 'default' => 1),
                'per_page'      => array('sanitize_callback' => 'absint', 'default' => 12),
            ),
        ));
    }

    public static function rest_documents(WP_REST_Request $request) {
        $items = DGES_Docs_API::get_documents((bool) $request->get_param('refresh'));
        if (is_wp_error($items)) {
            return $items;
        }

        $filtered  = DGES_Docs_API::filter_documents($items, $request->get_params());
        $total_all = count($filtered);

        $page     = max(1, (int) $request->get_param('page'));
        $per_page = (int) $request->get_param('per_page');
        if ($per_page < 1) $per_page = 12;

        $total_pages = (int) ceil($total_all / $per_page);
        $offset      = ($page - 1) * $per_page;
        $items_page  = array_slice($filtered, $offset, $per_page);

        return rest_ensure_response(array(
            'items'       => $items_page,
            'total'       => $total_all,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
            'has_more'    => $page < $total_pages,
            'facets'      => self::facets($items),
        ));
    }

    private static function facets($items) {
        $types = array(); $categories = array(); $years = array();
        $document_types = array(); $numbers = array();
        foreach ($items as $item) {
            if (!empty($item['type']))          $types[$item['type']] = true;
            if (!empty($item['category']))      $categories[$item['category']] = true;
            if (!empty($item['year']))          $years[$item['year']] = true;
            if (!empty($item['number']))        $numbers[$item['number']] = true;
        }
        ksort($types); ksort($categories); krsort($years); ksort($document_types);
        sort($numbers, SORT_NATURAL);
        return array(
            'types'          => array_keys($types),
            'categories'     => array_keys($categories),
            'years'          => array_map('intval', array_keys($years)),
            'numbers'        => array_values($numbers),
        );
    }
}