<?php
/**
 * Plugin Name: DGES - Documentos y Filtro Avanzado
 * Description: Importa los enlaces de la página de documentos de la DGES y los muestra con búsqueda y filtros avanzados.
 * Version: 1.2.0
 * Author: DGES
 * Text Domain: dges-documento
 */

// Seguridad de WordPress: Evita el acceso directo al archivo vía URL.
if (!defined('ABSPATH')) {
    exit;
}

// Definición de constantes globales del plugin para uso de rutas y versiones.
define('DGES_DOCS_VERSION', '1.2.0');
define('DGES_DOCS_FILE', __FILE__);
define('DGES_DOCS_DIR', plugin_dir_path(__FILE__));
define('DGES_DOCS_URL', plugin_dir_url(__FILE__));

// Importación de las clases principales ubicadas en la carpeta includes.
// El orden importa: API y Render primero, luego Settings, luego Plugin.
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-api.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-render.php';    // 👈 NUEVO
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-settings.php';  // 👈 NUEVO
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-plugin.php';

// Hooks de ciclo de vida de WordPress: activación y carga del plugin.
register_activation_hook(__FILE__, array('DGES_Docs_Plugin', 'activate'));
add_action('plugins_loaded', array('DGES_Docs_Plugin', 'init'));