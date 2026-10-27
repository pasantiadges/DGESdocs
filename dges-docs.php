<?php
/**
 * Plugin Name: DGES - Documentos y Filtro Avanzado
 * Description: Importa los documentos de la DGES y los muestra con búsqueda por texto, filtro por categoría y detalle por documento.
 * Version: 1.5.1
 * Author: DGES
 * Text Domain: dges-documentos
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

// Seguridad de WordPress: Evita el acceso directo al archivo vía URL.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DGES_DOCS_VERSION', '1.5.1' );
define( 'DGES_DOCS_FILE', __FILE__ );
define( 'DGES_DOCS_DIR', plugin_dir_path( __FILE__ ) );
define( 'DGES_DOCS_URL', plugin_dir_url( __FILE__ ) );

require_once DGES_DOCS_DIR . 'includes/class-dges-docs-query.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-source.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-repository.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-render.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-rest.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-router.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-settings.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-api.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-plugin.php';

register_activation_hook( __FILE__, array( 'DGES_Docs_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DGES_Docs_Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'DGES_Docs_Plugin', 'init' ) );
