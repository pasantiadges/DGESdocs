<?php
/**
 * Capa REST del plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Rest {

    const REST_NAMESPACE = 'dges-documentos/v1';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/documents',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'documents' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'search'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
                    'txtbusqueda' => array( 'sanitize_callback' => 'sanitize_text_field' ),
                    'category'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
                    'categoria'   => array( 'sanitize_callback' => 'sanitize_text_field' ),
                    'page'        => array( 'sanitize_callback' => 'absint', 'default' => 1 ),
                    'per_page'    => array( 'sanitize_callback' => 'absint' ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/documents/(?P<id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'document' ),
                'permission_callback' => '__return_true',
                'args'                => array(
                    'id' => array( 'sanitize_callback' => 'absint' ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/categories',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'categories' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    public static function documents( WP_REST_Request $request ) {
        $args   = self::query_args( $request );
        $repo   = DGES_Docs_Repository::make();
        $result = $repo->query( $args );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $items = array();

        foreach ( $result['items'] as $item ) {
            $items[] = self::prepare_item( $item );
        }

        $response = rest_ensure_response(
            array(
                'items'      => $items,
                'pagination' => array(
                    'page'        => (int) $result['page'],
                    'per_page'    => (int) $result['per_page'],
                    'total'       => (int) $result['total'],
                    'total_pages' => (int) $result['total_pages'],
                    'has_more'    => (bool) $result['has_more'],
                ),
                'meta'       => array(
                    'mode' => get_option( DGES_Docs_Repository::MODE_OPTION, 'index' ),
                ),
            )
        );

        $response->header( 'Cache-Control', 'public, max-age=60' );
        $response->header( 'X-DGES-Mode', get_option( DGES_Docs_Repository::MODE_OPTION, 'index' ) );

        return $response;
    }

    public static function document( WP_REST_Request $request ) {
        $id   = (string) $request->get_param( 'id' );
        $repo = DGES_Docs_Repository::make();
        $item = $repo->find( $id );

        if ( is_wp_error( $item ) ) {
            return $item;
        }

        $related = array();

        if ( ! empty( $item['related'] ) && is_array( $item['related'] ) ) {
            foreach ( $item['related'] as $rel ) {
                $rel['detail_url'] = DGES_Docs_Render::detail_url( isset( $rel['id'] ) ? $rel['id'] : '' );
                $rel['html']       = DGES_Docs_Render::card( $rel, array( 'context' => 'related', 'link' => 'detail' ) );
                $related[]         = $rel;
            }
        }

        $response = rest_ensure_response(
            array(
                'item'    => self::prepare_item( $item ),
                'related' => $related,
                'meta'    => array(
                    'mode' => get_option( DGES_Docs_Repository::MODE_OPTION, 'index' ),
                ),
            )
        );

        $response->header( 'Cache-Control', 'public, max-age=120' );

        return $response;
    }

    public static function categories( WP_REST_Request $request ) {
        $repo = DGES_Docs_Repository::make();
        $cats = $repo->categories();

        if ( is_wp_error( $cats ) ) {
            return $cats;
        }

        $response = rest_ensure_response( array( 'categories' => $cats ) );
        $response->header( 'Cache-Control', 'public, max-age=300' );

        return $response;
    }

    private static function query_args( WP_REST_Request $request ) {
        $search = $request->get_param( 'search' );
        if ( null === $search || '' === $search ) {
            $search = $request->get_param( 'txtbusqueda' );
        }

        $category = $request->get_param( 'category' );
        if ( null === $category || '' === $category ) {
            $category = $request->get_param( 'categoria' );
        }

        $search   = is_scalar( $search ) ? trim( (string) $search ) : '';
        $category = is_scalar( $category ) ? trim( (string) $category ) : '';

        if ( strlen( $search ) > 100 ) {
            $search = substr( $search, 0, 100 );
        }

        $per_page = (int) $request->get_param( 'per_page' );
        if ( $per_page < 1 ) {
            $per_page = (int) get_option( 'dges_documentos_per_page', 12 );
        }
        if ( $per_page < 1 ) {
            $per_page = 12;
        }
        $per_page = min( DGES_Docs_Query::MAX_PER_PAGE, $per_page );

        return array(
            'search'   => $search,
            'category' => $category,
            'page'     => max( 1, (int) $request->get_param( 'page' ) ),
            'per_page' => $per_page,
        );
    }

    private static function prepare_item( $item ) {
        unset( $item['search_text'] );
        $item['detail_url'] = DGES_Docs_Render::detail_url( isset( $item['id'] ) ? $item['id'] : '' );
        $item['html']       = DGES_Docs_Render::card( $item, array( 'context' => 'list' ) );

        return $item;
    }
}
