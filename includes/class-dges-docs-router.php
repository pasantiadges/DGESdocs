<?php
/**
 * Router del detalle de documentos: rewrite, página, resolución, SEO y 404.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Router {

    const OPTION_DETAIL_PAGE = 'dges_documentos_detail_page';
    const OPTION_LIST_PAGE   = 'dges_documentos_list_page';
    const OPTION_PAGES_READY = 'dges_documentos_pages_ready';
    const OPTION_REWRITE_VERSION = 'dges_documentos_rewrite_version';
    const REWRITE_VERSION    = '2';
    const QUERY_VAR          = 'dges_doc_id';

    /** @var array|null */
    private static $current_item = null;

    /** @var WP_Error|null */
    private static $current_error = null;

    /** @var string */
    private static $current_id = '';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_rewrite' ), 5 );
        add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 6 );
        add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 8 );
        add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ), 1 );
        add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
        add_filter( 'document_title_parts', array( __CLASS__, 'document_title_parts' ) );
        add_action( 'wp_head', array( __CLASS__, 'head_meta' ), 1 );
        add_filter( 'get_canonical_url', array( __CLASS__, 'canonical_url' ), 10, 2 );
        add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
        add_filter( 'astra_the_title_enabled', array( __CLASS__, 'astra_title' ) );
    }

    public static function base_path() {
        $base = apply_filters( 'dges_docs_detail_base', 'documentos/documento' );
        $base = trim( (string) $base, '/' );

        return '' === $base ? 'documentos/documento' : $base;
    }

    public static function detail_page_id() {
        $page_id = (int) get_option( self::OPTION_DETAIL_PAGE, 0 );

        if ( $page_id > 0 && 'page' === get_post_type( $page_id ) && 'publish' === get_post_status( $page_id ) ) {
            return $page_id;
        }

        return 0;
    }

    public static function list_page_id() {
        $page_id = (int) get_option( self::OPTION_LIST_PAGE, 0 );

        return $page_id > 0 && 'page' === get_post_type( $page_id ) ? $page_id : 0;
    }

    public static function list_url() {
        $page_id = self::list_page_id();

        return $page_id > 0 ? get_permalink( $page_id ) : home_url( '/' );
    }

    public static function detail_url( $id ) {
        $id = (string) $id;

        if ( ! preg_match( '/^\d+$/', $id ) ) {
            return '';
        }

        $page_id = self::detail_page_id();

        if ( ! $page_id ) {
            return '';
        }

        if ( get_option( 'permalink_structure' ) ) {
            $url = home_url( '/' . self::base_path() . '/' . $id . '/' );
        } else {
            $url = add_query_arg(
                array(
                    'page_id' => $page_id,
                    'doc'     => $id,
                ),
                home_url( '/' )
            );
        }

        return apply_filters( 'dges_docs_detail_url', $url, $id );
    }

    public static function back_url() {
        $list    = self::list_url();
        $referer = wp_get_referer();

        if ( $referer && $list && false !== strpos( $referer, $list ) ) {
            return $referer;
        }

        return $list;
    }

    public static function register_rewrite() {
        add_rewrite_tag( '%' . self::QUERY_VAR . '%', '([0-9]+)' );

        $page_id = self::detail_page_id();

        if ( ! $page_id ) {
            return;
        }

        add_rewrite_rule(
            '^' . preg_quote( self::base_path(), '/' ) . '/([0-9]+)/?$',
            'index.php?page_id=' . $page_id . '&' . self::QUERY_VAR . '=$matches[1]',
            'top'
        );
    }

    public static function query_vars( $vars ) {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function maybe_upgrade() {
        if ( get_option( self::OPTION_PAGES_READY ) ) {
            return;
        }

        self::ensure_pages();
        update_option( self::OPTION_PAGES_READY, 1, false );
    }

    public static function maybe_flush_rewrite() {
        if ( self::REWRITE_VERSION === (string) get_option( self::OPTION_REWRITE_VERSION, '' ) ) {
            return;
        }

        flush_rewrite_rules();
        update_option( self::OPTION_REWRITE_VERSION, self::REWRITE_VERSION, false );
    }

    /**
     * Crea/recupera la página de detalle y detecta la página del listado.
     *
     * @return int
     */
    public static function ensure_pages() {
        $detail_page = self::detail_page_id();

        if ( ! $detail_page ) {
            $existing = get_posts(
                array(
                    'post_type'      => 'page',
                    'post_status'    => 'publish',
                    'posts_per_page' => 20,
                    'fields'         => 'ids',
                )
            );

            foreach ( $existing as $page_id ) {
                $content = (string) get_post_field( 'post_content', $page_id );

                if ( has_shortcode( $content, 'dges_documento_detalle' ) ) {
                    update_option( self::OPTION_DETAIL_PAGE, $page_id, false );
                    $detail_page = $page_id;
                    break;
                }
            }
        }

        if ( ! $detail_page ) {
            $created = wp_insert_post(
                array(
                    'post_title'   => __( 'Documento DGES', 'dges-documentos' ),
                    'post_name'    => 'documento-dges',
                    'post_content' => '[dges_documento_detalle]',
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ),
                true
            );

            if ( ! is_wp_error( $created ) ) {
                update_option( self::OPTION_DETAIL_PAGE, (int) $created, false );
                $detail_page = (int) $created;
            }
        }

        if ( ! self::list_page_id() ) {
            $pages = get_posts(
                array(
                    'post_type'      => 'page',
                    'post_status'    => 'publish',
                    'posts_per_page' => 100,
                    'fields'         => 'ids',
                )
            );

            foreach ( $pages as $page_id ) {
                $content = (string) get_post_field( 'post_content', $page_id );

                if ( has_shortcode( $content, 'dges_documentos' ) ) {
                    update_option( self::OPTION_LIST_PAGE, $page_id, false );
                    break;
                }
            }
        }

        flush_rewrite_rules();

        return (int) $detail_page;
    }

    public static function flush_rewrite() {
        flush_rewrite_rules();
    }

    public static function get_current_item() {
        return self::$current_item;
    }

    public static function get_current_error() {
        return self::$current_error;
    }

    public static function get_current_id() {
        return self::$current_id;
    }

    /**
     * Resuelve un documento y deja el estado para el shortcode y el SEO.
     *
     * @param string $id
     * @return array|WP_Error
     */
    public static function resolve( $id ) {
        $id = (string) $id;

        self::$current_id    = $id;
        self::$current_item  = null;
        self::$current_error = null;

        if ( ! preg_match( '/^\d+$/', $id ) ) {
            self::$current_error = new WP_Error(
                'dges_docs_document_not_found',
                __( 'Documento no encontrado.', 'dges-documentos' ),
                array( 'status' => 404 )
            );

            self::set_404();
            return self::$current_error;
        }

        $repo = DGES_Docs_Repository::make();
        $item = $repo->find( $id );

        if ( is_wp_error( $item ) ) {
            self::$current_error = $item;
            self::set_404();
            return $item;
        }

        self::$current_item = $item;

        return $item;
    }

    private static function set_404() {
        status_header( 404 );
        nocache_headers();
        remove_action( 'wp_head', 'rel_canonical' );
    }

    public static function template_redirect() {
        if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        if ( ! self::detail_page_id() ) {
            return;
        }

        if ( isset( $_GET['doc'] ) && is_scalar( $_GET['doc'] ) && get_option( 'permalink_structure' ) ) {
            $doc = absint( wp_unslash( $_GET['doc'] ) );

            if ( $doc > 0 ) {
                $url = self::detail_url( $doc );

                if ( $url ) {
                    wp_safe_redirect( $url, 301 );
                    exit;
                }
            }
        }

        $id = get_query_var( self::QUERY_VAR );

        if ( '' === $id && is_page( self::detail_page_id() ) && isset( $_GET['doc'] ) && is_scalar( $_GET['doc'] ) ) {
            $id = absint( wp_unslash( $_GET['doc'] ) );
        }

        $id = (string) $id;

        if ( '' === $id ) {
            return;
        }

        self::resolve( $id );
    }

    public static function body_class( $classes ) {
        if ( '' !== self::$current_id ) {
            $classes[] = 'dges-doc-detail';
        }

        return $classes;
    }

    public static function document_title_parts( $parts ) {
        if ( self::$current_item ) {
            $parts['title'] = self::$current_item['title'];
        } elseif ( self::$current_error ) {
            $parts['title'] = __( 'Documento no encontrado', 'dges-documentos' );
        }

        return $parts;
    }

    public static function canonical_url( $canonical, $post = null ) {
        if ( self::$current_item && '' !== self::$current_id ) {
            $url = self::detail_url( self::$current_id );

            if ( $url ) {
                return $url;
            }
        }

        return $canonical;
    }

    public static function robots( $robots ) {
        $base_page_without_id = '' === self::$current_id && is_page( self::detail_page_id() );

        if ( self::$current_error || $base_page_without_id ) {
            unset( $robots['index'] );
            $robots['noindex'] = true;
            $robots['nofollow'] = true;
        }

        return $robots;
    }

    public static function astra_title( $enabled ) {
        if ( '' !== self::$current_id ) {
            return false;
        }

        return $enabled;
    }

    public static function head_meta() {
        if ( ! self::$current_item || '' === self::$current_id ) {
            return;
        }

        $item = self::$current_item;
        $url  = self::detail_url( self::$current_id );

        if ( ! $url ) {
            return;
        }

        $title       = isset( $item['title'] ) ? (string) $item['title'] : '';
        $description = isset( $item['description'] ) ? (string) $item['description'] : '';
        $description = wp_trim_words( wp_strip_all_tags( $description ), 30, '…' );
        $site_name   = get_bloginfo( 'name' );
        $icon        = get_site_icon_url();

        if ( '' !== $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
        if ( '' !== $description ) {
            echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
        }
        echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
        if ( $icon ) {
            echo '<meta property="og:image" content="' . esc_url( $icon ) . '">' . "\n";
        }

        echo '<meta name="twitter:card" content="summary">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
        if ( '' !== $description ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        $json = self::json_ld( $item, $url );

        if ( '' !== $json ) {
            echo '<script type="application/ld+json">' . $json . '</script>' . "\n";
        }
    }

    private static function json_ld( $item, $url ) {
        $types = array();

        if ( ! empty( $item['files'] ) && is_array( $item['files'] ) ) {
            foreach ( $item['files'] as $file ) {
                if ( ! empty( $file['type'] ) ) {
                    $types[] = $file['type'];
                }
            }
        }

        $data = array(
            '@context'      => 'https://schema.org',
            '@type'         => 'DigitalDocument',
            'name'          => isset( $item['title'] ) ? $item['title'] : '',
            'description'   => isset( $item['description'] ) ? $item['description'] : '',
            'url'           => $url,
            'identifier'    => isset( $item['id'] ) ? $item['id'] : '',
            'encodingFormat' => array_values( array_unique( $types ) ),
        );

        if ( ! empty( $item['year'] ) ) {
            $data['datePublished'] = (int) $item['year'] . '-01-01';
        }

        if ( ! empty( $item['category'] ) ) {
            $data['about'] = $item['category'];
        }

        $data['isPartOf'] = array(
            '@type' => 'CollectionPage',
            'name'  => __( 'Documentos DGES', 'dges-documentos' ),
            'url'   => self::list_url(),
        );

        $json = wp_json_encode( array_filter( $data ) );

        return $json ? str_replace( '</', '<\\/', $json ) : '';
    }
}
