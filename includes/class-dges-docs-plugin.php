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
    // Registramos la opción para la cantidad de destacados
        register_setting('dges_documentos', 'dges_documentos_limit_destacados', array(
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 4,
        ));
        add_shortcode('dges_documentos', array(__CLASS__, 'shortcode'));
        add_shortcode('dges_documentos_destacados', array(__CLASS__, 'shortcode_destacados')); // <--- Nuevo shortcode
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

public static function shortcode_destacados($atts) {
        wp_enqueue_style('dges-documentos');
        
        // Obtenemos los documentos cacheados/indexados
        $items = DGES_Docs_API::get_documents();
        if (is_wp_error($items)) {
            return '<p>No se pudieron cargar los documentos destacados.</p>';
        }

        // Filtramos por ejemplo los primeros 4 documentos o aquellos que tengan relevancia
        // También podés buscar palabras clave específicas en el título
        $destacados = array_slice($items, 0, 4); 

        $atts = shortcode_atts(array('title' => 'Documentos Destacados'), $atts, 'dges_documentos_destacados');
        
        $output = '<section class="dges-documentos dges-destacados-section"><h2>' . esc_html($atts['title']) . '</h2><div class="dges-documentos-results">';
        
        if (empty($destacados)) {
            $output .= '<p>No hay documentos destacados disponibles.</p>';
        } else {
            foreach ($destacados as $item) {
                $output .= '<article class="dges-document-card">';
                $output .= '<span class="dges-document-type">' . esc_html(ucfirst($item['type'])) . '</span>';
                $output .= '<h3>' . esc_html($item['title']) . '</h3>';
                if (!empty($item['description'])) {
                    $output .= '<p>' . esc_html($item['description']) . '</p>';
                }
                if (!empty($item['category'])) {
                    $output .= '<p class="dges-cat">' . esc_html($item['category']) . '</p>';
                }
                $output .= '<a href="' . esc_url($item['url']) . '" target="' . esc_attr($item['target']) . '" rel="noopener">Abrir documento</a>';
                $output .= '</article>';
            }
        }
        
        $output .= '</div></section>';
        return $output;
    }

public static function register_routes() {
        register_rest_route('dges-documentos/v1', '/documents', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array(__CLASS__, 'rest_documents'),
            'permission_callback' => '__return_true',
            'args' => array(
                'txtbusqueda' => array('sanitize_callback' => 'sanitize_text_field'),
                'categoria' => array('sanitize_callback' => 'sanitize_text_field'),
                'anio' => array('sanitize_callback' => 'absint'),
                'type' => array('sanitize_callback' => 'sanitize_key'),
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
       // Crea una sección principal independiente en el menú de WordPress
        add_menu_page(
            'Documentos DGES',                      // Título de la página en la pestaña del navegador
            'Documentos DGES',                      // Texto que se ve en el menú lateral
            'manage_options',                       // Permisos necesarios
            'dges-documentos',                      // Slug único de la página
            array(__CLASS__, 'settings_page'),      // Función que dibuja la interfaz
            'dashicons-media-document',             // Ícono de WordPress (puedes cambiarlo)
            26                                      // Posición en el menú lateral
        );
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
            echo '<div class="notice notice-success is-dismissible"><p><strong>¡Caché actualizada correctamente!</strong></p></div>';
        }
        ?>
        <div class="wrap" style="max-width: 1000px;">
            <!-- Cabecera con estilo institucional similar a DGES -->
            <div style="background: #6c5ce7; color: #fff; padding: 20px 25px; border-radius: 4px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h1 style="color: #fff; margin: 0 0 5px 0; font-size: 24px;">Configuración - Documentos DGES</h1>
                <p style="margin: 0; opacity: 0.9;">Configure la conexión con la API oficial de la base de datos de documentos y gestión de caché.</p>
            </div>

            <!-- Formulario de Configuración de API -->
            <div class="card" style="padding: 20px; margin-top: 0; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Configuración de la API</h2>
                <form method="post" action="options.php">
                    <?php 
                    settings_fields('dges_documentos'); 
                    do_settings_sections('dges_documentos'); 
                    ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>">URL del JSON de Documentos</label></th>
                            <td>
                                <input class="regular-text code" type="url" id="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>" name="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>" value="<?php echo esc_attr(DGES_Docs_API::get_source_url()); ?>" style="width: 100%; max-width: 600px;" required>
                                <p class="description">Ingrese la URL completa de la API oficial que devuelve el JSON con los documentos, categorías y archivos.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Guardar Configuración'); ?>
                </form>
            </div>

            <!-- Sección de Gestión de Caché -->
            <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Gestión de Caché y Rendimiento</h2>
                <p>El sistema almacena temporalmente los registros para agilizar las consultas. Si se actualizaron datos en la base oficial y necesita forzar la recarga inmediata, utilice el siguiente botón:</p>
                <form method="post">
                    <?php wp_nonce_field('dges_documentos_refresh'); ?>
                    <input type="hidden" name="dges_documentos_refresh" value="1">
                    <?php submit_button('Vaciar Caché y Actualizar Datos', 'secondary'); ?>
                </form>
            </div>

            <!-- Guía rápida de Shortcodes -->
            <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;">Shortcodes Disponibles</h2>
                <p>Utilice las siguientes etiquetas para insertar los componentes en cualquier página o entrada:</p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><code>[dges_documentos]</code> — Muestra el buscador avanzado con filtros en tiempo real y resultados interactivos.</li>
                    <li><code>[dges_documentos_destacados]</code> — Muestra una sección resumida con los documentos clave.</li>
                </ul>
            </div>
        </div>
        <?php
    }
}
