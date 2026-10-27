<?php
/**
 * Orquestador: shortcodes, assets, REST, cron y ciclo de vida.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Plugin {

    public static function init() {
        add_shortcode( 'dges_documentos', array( __CLASS__, 'shortcode' ) );
        add_shortcode( 'dges_documentos_destacados', array( __CLASS__, 'shortcode_destacados' ) );
        add_shortcode( 'dges_documento', array( __CLASS__, 'shortcode_documento' ) );
        add_shortcode( 'dges_documento_detalle', array( __CLASS__, 'shortcode_detalle' ) );

        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
        add_filter( 'script_loader_tag', array( __CLASS__, 'module_script_tag' ), 10, 2 );
        add_action( 'init', array( __CLASS__, 'maybe_schedule_cron' ) );
        add_action( DGES_Docs_Repository::CRON_HOOK, array( 'DGES_Docs_Repository', 'cron_refresh' ) );
        add_action( DGES_Docs_Repository::CRON_NOW_HOOK, array( 'DGES_Docs_Repository', 'cron_refresh' ) );

        DGES_Docs_Router::init();
        DGES_Docs_Rest::init();
        DGES_Docs_Settings::init();
    }

    public static function activate() {
        if ( false === get_option( DGES_Docs_Source::OPTION_URL, false ) ) {
            add_option( DGES_Docs_Source::OPTION_URL, DGES_Docs_Source::default_url() );
        }
        if ( false === get_option( 'dges_documentos_limit_destacados', false ) ) {
            add_option( 'dges_documentos_limit_destacados', 4 );
        }
        if ( false === get_option( 'dges_documentos_per_page', false ) ) {
            add_option( 'dges_documentos_per_page', 12 );
        }
        if ( false === get_option( DGES_Docs_Repository::MODE_OPTION, false ) ) {
            add_option( DGES_Docs_Repository::MODE_OPTION, 'index' );
        }
        if ( false === get_option( DGES_Docs_Source::OPTION_FILES_BASE, false ) ) {
            add_option( DGES_Docs_Source::OPTION_FILES_BASE, '' );
        }

        DGES_Docs_Router::ensure_pages();
        DGES_Docs_Repository::maybe_schedule();

        if ( ! wp_next_scheduled( DGES_Docs_Repository::CRON_NOW_HOOK ) ) {
            wp_schedule_single_event( time() + 5, DGES_Docs_Repository::CRON_NOW_HOOK );
        }
    }

    public static function deactivate() {
        DGES_Docs_Repository::unschedule();
        wp_clear_scheduled_hook( DGES_Docs_Repository::CRON_NOW_HOOK );
        delete_option( DGES_Docs_Repository::LOCK_OPTION );
        flush_rewrite_rules();
    }

    public static function maybe_schedule_cron() {
        DGES_Docs_Repository::maybe_schedule();
    }

    public static function register_assets() {
        wp_register_style(
            'dges-documentos',
            DGES_DOCS_URL . 'assets/css/docs.css',
            array(),
            DGES_DOCS_VERSION
        );

        wp_register_script(
            'dges-documentos',
            DGES_DOCS_URL . 'assets/js/docs.js',
            array(),
            DGES_DOCS_VERSION,
            true
        );

        wp_localize_script( 'dges-documentos', 'DGESDocumentos', self::script_config() );
    }

    public static function module_script_tag( $tag, $handle ) {
        if ( 'dges-documentos' !== $handle ) {
            return $tag;
        }

        if ( false !== strpos( $tag, 'type="module"' ) ) {
            return $tag;
        }

        return str_replace( '<script ', '<script type="module" ', $tag );
    }

    private static function script_config() {
        return array(
            'endpoint' => esc_url_raw( rest_url( DGES_Docs_Rest::REST_NAMESPACE . '/documents' ) ),
            'perPage'  => max( 1, min( DGES_Docs_Query::MAX_PER_PAGE, (int) get_option( 'dges_documentos_per_page', 12 ) ) ),
            'mode'     => get_option( DGES_Docs_Repository::MODE_OPTION, 'index' ),
            'i18n'     => array(
                'loading'       => __( 'Cargando documentos…', 'dges-documentos' ),
                'loadingMore'   => __( 'Cargando más…', 'dges-documentos' ),
                'loadMore'      => __( 'Ver más', 'dges-documentos' ),
                'error'         => __( 'No pudimos cargar los documentos.', 'dges-documentos' ),
                'retry'         => __( 'Reintentar', 'dges-documentos' ),
                'clear'         => __( 'Limpiar', 'dges-documentos' ),
                'clearFilters'  => __( 'Limpiar filtros', 'dges-documentos' ),
                'emptySearch'   => __( 'No encontramos documentos para “%s”.', 'dges-documentos' ),
                'emptyBoth'     => __( 'No encontramos documentos para “%1$s” en “%2$s”.', 'dges-documentos' ),
                'emptyCategory' => __( 'No hay documentos en esta categoría.', 'dges-documentos' ),
                'emptyAll'      => __( 'Todavía no hay documentos disponibles.', 'dges-documentos' ),
                'documentsOne'  => __( '%s documento', 'dges-documentos' ),
                'documentsMany' => __( '%s documentos', 'dges-documentos' ),
                'showingOne'    => __( 'Mostrando %1$s de %2$s documento', 'dges-documentos' ),
                'showingMany'   => __( 'Mostrando %1$s de %2$s documentos', 'dges-documentos' ),
            ),
        );
    }

    public static function shortcode( $atts ) {
        wp_enqueue_style( 'dges-documentos' );
        wp_enqueue_script( 'dges-documentos' );

        $atts = shortcode_atts(
            array( 'title' => __( 'Documentos DGES', 'dges-documentos' ) ),
            $atts,
            'dges_documentos'
        );

        $search   = self::request_param( 'buscar' );
        $category = self::request_param( 'categoria' );
        $page     = self::request_page();
        $per_page = max( 1, min( DGES_Docs_Query::MAX_PER_PAGE, (int) get_option( 'dges_documentos_per_page', 12 ) ) );

        $repo   = DGES_Docs_Repository::make();
        $result = $repo->query(
            array(
                'search'   => $search,
                'category' => $category,
                'page'     => $page,
                'per_page' => $per_page,
            )
        );

        $categories = is_wp_error( $result ) ? array() : $repo->categories();

        if ( is_wp_error( $categories ) ) {
            $categories = array();
        }

        $state = array(
            'search'   => $search,
            'category' => $category,
            'page'     => $page,
        );

        return DGES_Docs_Render::shortcode_buscador(
            $atts['title'],
            $state,
            $result,
            $categories,
            is_wp_error( $result ) ? $result : null
        );
    }

    public static function shortcode_destacados( $atts ) {
        wp_enqueue_style( 'dges-documentos' );

        $atts = shortcode_atts(
            array( 'title' => __( 'Documentos Destacados', 'dges-documentos' ) ),
            $atts,
            'dges_documentos_destacados'
        );

        $limit = absint( get_option( 'dges_documentos_limit_destacados', 4 ) );
        if ( $limit < 1 ) {
            $limit = 4;
        }

        $repo  = DGES_Docs_Repository::make();
        $items = $repo->featured( $limit );

        if ( is_wp_error( $items ) ) {
            return '<p class="dges-error-inline">' . esc_html__( 'No se pudieron cargar los documentos destacados.', 'dges-documentos' ) . '</p>';
        }

        return DGES_Docs_Render::shortcode_destacados( $items, $atts['title'] );
    }

    public static function shortcode_documento( $atts ) {
        wp_enqueue_style( 'dges-documentos' );

        $atts = shortcode_atts(
            array(
                'id'               => '',
                'variant'          => 'default',
                'show_description' => '1',
                'show_meta'        => '1',
                'link'             => 'detail',
                'file'             => '',
                'class'            => '',
            ),
            $atts,
            'dges_documento'
        );

        $id = sanitize_text_field( (string) $atts['id'] );

        if ( '' === $id ) {
            if ( current_user_can( 'edit_posts' ) ) {
                return '<p class="dges-error-inline">' . esc_html__( 'El shortcode [dges_documento] necesita el atributo id.', 'dges-documentos' ) . '</p>';
            }

            return '';
        }

        $repo = DGES_Docs_Repository::make();
        $item = $repo->find( $id );

        if ( is_wp_error( $item ) ) {
            if ( current_user_can( 'edit_posts' ) ) {
                return '<p class="dges-error-inline">' . sprintf(
                    /* translators: %s: document ID */
                    esc_html__( 'No se encontró el documento con ID %s.', 'dges-documentos' ),
                    esc_html( $id )
                ) . '</p>';
            }

            return '';
        }

        return DGES_Docs_Render::single_document( $item, $atts );
    }

    public static function shortcode_detalle() {
        wp_enqueue_style( 'dges-documentos' );

        $item  = DGES_Docs_Router::get_current_item();
        $error = DGES_Docs_Router::get_current_error();
        $id    = DGES_Docs_Router::get_current_id();

        if ( ! $item && ! $error ) {
            $id = get_query_var( DGES_Docs_Router::QUERY_VAR );

            if ( '' === (string) $id && isset( $_GET['doc'] ) && is_scalar( $_GET['doc'] ) ) {
                $id = absint( wp_unslash( $_GET['doc'] ) );
            }

            if ( '' !== (string) $id ) {
                $item  = DGES_Docs_Router::resolve( $id );
                $error = DGES_Docs_Router::get_current_error();
            }
        }

        if ( is_wp_error( $error ) ) {
            return DGES_Docs_Render::detail_not_found( $id );
        }

        if ( ! is_array( $item ) ) {
            return DGES_Docs_Render::detail_not_found( '' );
        }

        return DGES_Docs_Render::detail( $item );
    }

    private static function request_param( $key ) {
        if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
    }

    private static function request_page() {
        if ( isset( $_GET['pagina'] ) && is_scalar( $_GET['pagina'] ) ) {
            return max( 1, absint( wp_unslash( $_GET['pagina'] ) ) );
        }

        return 1;
    }
}
