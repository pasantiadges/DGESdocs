<?php
/**
 * Repositorios de documentos.
 *
 * - Index: baja el listado, lo cachea y filtra en WordPress (modo transición).
 * - Remote: delega búsqueda y paginación a la API (cuando el back lo confirme).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Repository {

    const MODE_OPTION     = 'dges_documentos_search_mode';
    const INDEX_KEY       = 'dges_docs_index_v3';
    const CATEGORIES_KEY  = 'dges_docs_categories_v3';
    const INDEX_FRESH     = HOUR_IN_SECONDS;
    const INDEX_TTL       = DAY_IN_SECONDS;
    const LOCK_OPTION     = 'dges_docs_index_lock';
    const LAST_SYNC       = 'dges_docs_last_sync';
    const LAST_ERROR      = 'dges_docs_last_error';
    const LAST_COUNT      = 'dges_docs_last_count';
    const REMOTE_PREFIX   = 'dges_docs_remote_v3_';
    const CRON_HOOK       = 'dges_docs_refresh_index';
    const CRON_NOW_HOOK   = 'dges_docs_refresh_index_now';

    public static function make() {
        $mode = get_option( self::MODE_OPTION, 'index' );

        if ( 'api' === $mode ) {
            return new DGES_Docs_Remote_Repository();
        }

        return new DGES_Docs_Index_Repository();
    }

    public static function clear_all() {
        delete_transient( self::INDEX_KEY );
        delete_transient( self::CATEGORIES_KEY );
        delete_transient( 'dges_documentos_api_json_v2' );
        delete_option( self::LOCK_OPTION );
        delete_option( self::LAST_SYNC );
        delete_option( self::LAST_COUNT );
        delete_option( self::LAST_ERROR );
    }

    public static function status() {
        $mode   = get_option( self::MODE_OPTION, 'index' );
        $cached = get_transient( self::INDEX_KEY );
        $items  = ( is_array( $cached ) && isset( $cached['items'] ) && is_array( $cached['items'] ) ) ? $cached['items'] : array();
        $fetched_at = ( is_array( $cached ) && isset( $cached['fetched_at'] ) ) ? (int) $cached['fetched_at'] : 0;

        return array(
            'mode'       => $mode,
            'count'      => count( $items ),
            'last_sync'  => (int) get_option( self::LAST_SYNC, 0 ),
            'last_error' => (string) get_option( self::LAST_ERROR, '' ),
            'fetched_at' => $fetched_at,
            'is_stale'   => $fetched_at > 0 && $fetched_at < ( time() - self::INDEX_FRESH ),
        );
    }

    public static function refresh_now() {
        if ( 'api' === get_option( self::MODE_OPTION, 'index' ) ) {
            self::clear_all();
            return true;
        }

        $repo = new DGES_Docs_Index_Repository();
        return $repo->refresh();
    }

    public static function maybe_schedule() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
        }
    }

    public static function unschedule() {
        wp_clear_scheduled_hook( self::CRON_HOOK );
    }

    public static function cron_refresh() {
        if ( 'api' === get_option( self::MODE_OPTION, 'index' ) ) {
            return;
        }

        $repo = new DGES_Docs_Index_Repository();
        $repo->refresh();
    }
}

class DGES_Docs_Index_Repository {

    /** @var DGES_Docs_Source */
    private $source;

    public function __construct() {
        $this->source = new DGES_Docs_Source();
    }

    /**
     * Devuelve todos los items del índice, refrescando si hace falta.
     *
     * @param bool $force
     * @return array|WP_Error
     */
    public function get_items( $force = false ) {
        $cached     = get_transient( DGES_Docs_Repository::INDEX_KEY );
        $has        = is_array( $cached ) && isset( $cached['items'] ) && is_array( $cached['items'] );
        $fetched_at = ( $has && isset( $cached['fetched_at'] ) ) ? (int) $cached['fetched_at'] : 0;

        if ( $has && ! $force && $fetched_at >= ( time() - DGES_Docs_Repository::INDEX_FRESH ) ) {
            return $cached['items'];
        }

        if ( $has && ! $force ) {
            self::schedule_refresh();
            return $cached['items'];
        }

        $result = $this->refresh();

        if ( is_wp_error( $result ) && $has ) {
            return $cached['items'];
        }

        return $result;
    }

    /**
     * Fuerza la descarga del listado y actualiza el índice.
     *
     * @return array|WP_Error
     */
    public function refresh() {
        if ( ! $this->acquire_lock() ) {
            return new WP_Error(
                'dges_docs_index_locked',
                __( 'El índice se está actualizando. Probá de nuevo en unos segundos.', 'dges-documentos' ),
                array( 'status' => 503 )
            );
        }

        $items = $this->source->get_list();

        if ( is_wp_error( $items ) ) {
            update_option( DGES_Docs_Repository::LAST_ERROR, $items->get_error_message(), false );
            $this->release_lock();
            return $items;
        }

        $items = DGES_Docs_Query::sort( $items );

        set_transient(
            DGES_Docs_Repository::INDEX_KEY,
            array(
                'fetched_at' => time(),
                'items'      => $items,
            ),
            DGES_Docs_Repository::INDEX_TTL
        );

        update_option( DGES_Docs_Repository::LAST_SYNC, time(), false );
        update_option( DGES_Docs_Repository::LAST_COUNT, count( $items ), false );
        update_option( DGES_Docs_Repository::LAST_ERROR, '', false );

        $this->release_lock();

        return $items;
    }

    /**
     * Consulta paginada del índice.
     */
    public function query( $args ) {
        $items = $this->get_items( ! empty( $args['force'] ) );

        if ( is_wp_error( $items ) ) {
            return $items;
        }

        $filtered = DGES_Docs_Query::filter( $items, $args );

        return DGES_Docs_Query::paginate(
            $filtered,
            isset( $args['page'] ) ? $args['page'] : 1,
            isset( $args['per_page'] ) ? $args['per_page'] : 12
        );
    }

    public function categories() {
        $items = $this->get_items();

        if ( is_wp_error( $items ) ) {
            return $items;
        }

        return DGES_Docs_Query::categories( $items );
    }

    public function featured( $limit = 4 ) {
        $items = $this->get_items();

        if ( is_wp_error( $items ) ) {
            return $items;
        }

        $featured = array_values(
            array_filter(
                $items,
                function ( $item ) {
                    return ! empty( $item['featured'] );
                }
            )
        );

        $featured = DGES_Docs_Query::sort( $featured );

        return array_slice( $featured, 0, max( 1, (int) $limit ) );
    }

    public function find( $id ) {
        $detail = $this->source->get_detail( $id );

        if ( ! is_wp_error( $detail ) ) {
            return $detail;
        }

        $items = $this->get_items();

        if ( is_wp_error( $items ) ) {
            return $detail;
        }

        foreach ( $items as $item ) {
            if ( (string) $item['id'] === (string) $id ) {
                return $item;
            }
        }

        return $detail;
    }

    public function all( $force = false ) {
        return $this->get_items( $force );
    }

    private function acquire_lock() {
        $lock = (int) get_option( DGES_Docs_Repository::LOCK_OPTION, 0 );

        if ( $lock > 0 && ( time() - $lock ) < 300 ) {
            return false;
        }

        if ( $lock > 0 ) {
            delete_option( DGES_Docs_Repository::LOCK_OPTION );
        }

        return add_option( DGES_Docs_Repository::LOCK_OPTION, time(), '', 'no' );
    }

    private function release_lock() {
        delete_option( DGES_Docs_Repository::LOCK_OPTION );
    }

    public static function schedule_refresh() {
        $next = wp_next_scheduled( DGES_Docs_Repository::CRON_HOOK );

        if ( ! $next || $next > ( time() + 60 ) ) {
            wp_schedule_single_event( time() + 5, DGES_Docs_Repository::CRON_HOOK );
        }
    }
}

class DGES_Docs_Remote_Repository {

    /** @var DGES_Docs_Source */
    private $source;

    public function __construct() {
        $this->source = new DGES_Docs_Source();
    }

    public function query( $args ) {
        $per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 12;
        $per_page = min( DGES_Docs_Query::MAX_PER_PAGE, max( 1, $per_page ) );
        $page     = max( 1, isset( $args['page'] ) ? (int) $args['page'] : 1 );

        $cache_key = DGES_Docs_Repository::REMOTE_PREFIX . md5(
            wp_json_encode(
                array(
                    'search'   => isset( $args['search'] ) ? $args['search'] : '',
                    'category' => isset( $args['category'] ) ? $args['category'] : '',
                    'page'     => $page,
                    'per_page' => $per_page,
                )
            )
        );

        $cached = get_transient( $cache_key );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $items = $this->source->get_list(
            array(
                'search'   => isset( $args['search'] ) ? $args['search'] : '',
                'category' => isset( $args['category'] ) ? $args['category'] : '',
                'limit'    => $per_page,
                'offset'   => ( $page - 1 ) * $per_page,
            )
        );

        if ( is_wp_error( $items ) ) {
            return $items;
        }

        $items = DGES_Docs_Query::sort( $items );
        $total = null !== $this->source->last_total ? (int) $this->source->last_total : count( $items );

        $response = array(
            'items'       => array_values( $items ),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total > 0 ? (int) ceil( $total / $per_page ) : 0,
            'has_more'    => ( $page * $per_page ) < $total,
        );

        set_transient( $cache_key, $response, 5 * MINUTE_IN_SECONDS );

        return $response;
    }

    public function categories() {
        $cached = get_transient( DGES_Docs_Repository::CATEGORIES_KEY );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $categories = $this->source->get_categories();

        if ( is_wp_error( $categories ) ) {
            $index = new DGES_Docs_Index_Repository();
            $fallback = $index->categories();

            if ( is_wp_error( $fallback ) ) {
                return $categories;
            }

            $categories = $fallback;
        }

        set_transient( DGES_Docs_Repository::CATEGORIES_KEY, $categories, HOUR_IN_SECONDS );

        return $categories;
    }

    public function featured( $limit = 4 ) {
        $items = $this->source->get_list(
            array(
                'limit'    => max( 1, (int) $limit ),
                'featured' => true,
            )
        );

        if ( is_wp_error( $items ) ) {
            $index = new DGES_Docs_Index_Repository();
            return $index->featured( $limit );
        }

        $featured = array_values(
            array_filter(
                $items,
                function ( $item ) {
                    return ! empty( $item['featured'] );
                }
            )
        );

        $featured = DGES_Docs_Query::sort( $featured );

        return array_slice( $featured, 0, max( 1, (int) $limit ) );
    }

    public function find( $id ) {
        return $this->source->get_detail( $id );
    }

    public function all( $force = false ) {
        return $this->source->get_list();
    }
}
