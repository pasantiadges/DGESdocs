<?php
/**
 * Panel de administración.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Settings {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register'));
    }

    public static function menu() {
        add_menu_page(
            'Documentos DGES',
            'Documentos DGES',
            'manage_options',
            'dges-documentos',
            array(__CLASS__, 'render'),
            'dashicons-media-document',
            26
        );
    }

    public static function register() {
        register_setting('dges_documentos', DGES_Docs_API::OPTION_URL);
        register_setting('dges_documentos', 'dges_documentos_limit_destacados');
        register_setting('dges_documentos', 'dges_documentos_per_page');
        add_action('update_option_' . DGES_Docs_API::OPTION_URL, array('DGES_Docs_API', 'clear_cache'));
    }

    public static function render() {
        if (isset($_POST['dges_documentos_refresh']) && check_admin_referer('dges_documentos_refresh')) {
            DGES_Docs_API::clear_cache();
            echo '<div class="notice notice-success is-dismissible"><p><strong>¡Caché actualizada correctamente!</strong></p></div>';
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        ?>
        <div class="wrap" style="max-width: 1005px;">
            <div style="background: #6c5ce7; color: #fff; padding: 20px 25px; border-radius: 4px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h1 style="color: #fff; margin: 0 0 5px 0; font-size: 24px;">Documentos DGES - Panel de Control</h1>
            </div>

            <nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
                <a href="?page=dges-documentos&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">Configuración General</a>
                <a href="?page=dges-documentos&tab=destacados" class="nav-tab <?php echo $active_tab === 'destacados' ? 'nav-tab-active' : ''; ?>">Cantidades</a>
                <a href="?page=dges-documentos&tab=json_info" class="nav-tab <?php echo $active_tab === 'json_info' ? 'nav-tab-active' : ''; ?>">Ayuda</a>
            </nav>

            <!-- UN SOLO FORMULARIO QUE ENVUELVE TODAS LAS PESTAÑAS -->
            <form method="post" action="options.php">
                <?php settings_fields('dges_documentos'); ?>

                <?php if ($active_tab === 'general'): ?>
                    <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                        <h2>Conexión con la API Oficial</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>">URL del JSON de Documentos</label></th>
                                <td>
                                    <input class="regular-text code" type="url" id="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>" name="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>" value="<?php echo esc_attr(DGES_Docs_API::get_source_url()); ?>" style="width: 100%; max-width: 600px;" required>
                                </td>
                            </tr>
                        </table>
                    </div>

                <?php elseif ($active_tab === 'destacados'): ?>
                    <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                        <h2>Cantidades</h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="dges_documentos_limit_destacados">Cantidad de Destacados</label></th>
                                <td>
                                    <input type="number" id="dges_documentos_limit_destacados" name="dges_documentos_limit_destacados" value="<?php echo esc_attr(get_option('dges_documentos_limit_destacados', 4)); ?>" min="1" max="20" class="small-text" required>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="dges_documentos_per_page">Documentos por página</label></th>
                                <td>
                                    <input type="number" id="dges_documentos_per_page" name="dges_documentos_per_page" value="<?php echo esc_attr(get_option('dges_documentos_per_page', 12)); ?>" min="1" max="100" class="small-text" required>
                                </td>
                            </tr>
                        </table>
                    </div>

                               <?php else: ?>
                    <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; margin-bottom: 20px;">
                        <h2>Shortcodes Disponibles</h2>
                        <ul style="list-style:disc; margin-left:20px;">
                            <li><code>[dges_documentos]</code> — Buscador con filtros y paginación.</li>
                            <li><code>[dges_documentos_destacados]</code> — Tarjetas de los documentos más importantes.</li>
                        </ul>
                    </div>

                    <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4;">
                        <h2>Formato JSON Esperado</h2>
                        <p>Si necesitás cambiar la fuente de datos, el JSON que consuma el plugin debe respetar esta estructura para que los filtros y las tarjetas funcionen correctamente:</p>
                        <pre style="background: #1e1e1e; color: #dcdcdc; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>{
  "documentos": [
    {
      "id": "1",
      "nombre": "Resolución N° 123/2025 - Marco Regulatorio",
      "observacion": "Establece las pautas generales para el funcionamiento...",
      "anio": "2025",
      "destacado": "Si",
      "categorias": [
        { "idcategoria": "1", "nombre": "Normativas DGES" }
      ],
      "archivos": [
        {
          "idarchivo": "101",
          "tipo": "PDF",
          "carpeta": "archivos/2025/11",
          "nombre_archivo": "2025_11_res_123.pdf"
        }
      ],
      "documentos_relacionados": [
        {
          "id": "2",
          "nombre": "Anexo I - Pautas de aplicación",
          "url": "https://bd.dges-cba.edu.ar/bd_dges/archivos/2025/11/anexo_123.pdf"
        }
      ]
    }
  ]
}</code></pre>
                    </div>
                <?php endif; ?>

                <!-- Campos ocultos para que WordPress sepa que existen y no los borre -->
                <?php if ($active_tab !== 'general'): ?>
                    <input type="hidden" name="<?php echo esc_attr(DGES_Docs_API::OPTION_URL); ?>" value="<?php echo esc_attr(DGES_Docs_API::get_source_url()); ?>">
                <?php endif; ?>
                <?php if ($active_tab !== 'destacados'): ?>
                    <input type="hidden" name="dges_documentos_limit_destacados" value="<?php echo esc_attr(get_option('dges_documentos_limit_destacados', 4)); ?>">
                    <input type="hidden" name="dges_documentos_per_page" value="<?php echo esc_attr(get_option('dges_documentos_per_page', 12)); ?>">
                <?php endif; ?>

                <?php if ($active_tab !== 'json_info'): ?>
                    <div style="margin-top: 20px;">
                        <?php submit_button('Guardar Cambios'); ?>
                    </div>
                <?php endif; ?>
            </form>
            <!-- FIN DEL FORMULARIO ÚNICO -->

            <?php if ($active_tab === 'general'): ?>
                <div class="card" style="padding: 20px; background: #fff; border: 1px solid #ccd0d4; margin-top: 20px;">
                    <h2>Gestión de Caché</h2>
                    <form method="post">
                        <?php wp_nonce_field('dges_documentos_refresh'); ?>
                        <input type="hidden" name="dges_documentos_refresh" value="1">
                        <?php submit_button('Vaciar Caché y Actualizar', 'secondary'); ?>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}