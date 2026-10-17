<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Plugin {

    public function init() {
        // Registrar hooks, scripts y estilos
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        // Aquí cargarás tus CSS y JS de la carpeta assets en el futuro
    }
}