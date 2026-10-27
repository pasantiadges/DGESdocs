<?php
/**
 * Fachada de compatibilidad. La lógica vive en Source/Repository/Query.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_API {

    const OPTION_URL = 'dges_documentos_source_url';
    const CACHE_KEY  = 'dges_docs_index_v3';

    public static function default_url() {
        return DGES_Docs_Source::default_url();
    }

    public static function get_source_url() {
        return DGES_Docs_Source::get_source_url();
    }

    public static function clear_cache() {
        DGES_Docs_Repository::clear_all();
    }

    /**
     * @param bool $force_refresh
     * @return array|WP_Error
     */
    public static function get_documents( $force_refresh = false ) {
        $repo = new DGES_Docs_Index_Repository();

        return $repo->all( (bool) $force_refresh );
    }

    /**
     * Compatibilidad con el orden por fecha que agregó dev.
     */
    public static function sort_documents( $items ) {
        return DGES_Docs_Query::sort( $items );
    }

    public static function filter_documents( $items, $args ) {
        return DGES_Docs_Query::sort( DGES_Docs_Query::filter( $items, $args ) );
    }
}
