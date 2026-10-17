<?php
/**
 * Plugin Name: DGES Docs Filtro
 * Plugin URI:  https://github.com/tu-usuario/DGESdocs
 * Description: Plugin para rastrear, buscar y filtrar documentos de DGES.
 * Version:     1.0.0
 * Author:      DGES Team
 * License:     GPL v2 or later
 */

// Si se intenta acceder directamente a este archivo, salir de seguridad.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Definir constantes del plugin
define( 'DGES_DOCS_URL', plugin_dir_url( __FILE__ ) );
define( 'DGES_DOCS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Incluir los archivos de clases necesarios
 */
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-api.php';
require_once DGES_DOCS_DIR . 'includes/class-dges-docs-plugin.php';

/**
 * Inicializar el plugin
 */
function dges_docs_init() {
    $plugin = new DGES_Docs_Plugin();
    $plugin->init();
}
add_action( 'plugins_loaded', 'dges_docs_init' );