<?php
/**
 * Smoke test de carga y wiring de todas las clases, con stubs de WordPress.
 * Ejecutar: php tests/smoke-wiring.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-dges-docs-query.php';
require_once __DIR__ . '/../includes/class-dges-docs-source.php';
require_once __DIR__ . '/../includes/class-dges-docs-repository.php';
require_once __DIR__ . '/../includes/class-dges-docs-render.php';
require_once __DIR__ . '/../includes/class-dges-docs-rest.php';
require_once __DIR__ . '/../includes/class-dges-docs-router.php';
require_once __DIR__ . '/../includes/class-dges-docs-settings.php';
require_once __DIR__ . '/../includes/class-dges-docs-api.php';
require_once __DIR__ . '/../includes/class-dges-docs-plugin.php';

$GLOBALS['smoke_routes']   = array();
$GLOBALS['smoke_settings'] = array();
$GLOBALS['smoke_scheduled'] = array();

class WP_REST_Server {
    const READABLE = 'GET';
}

function add_shortcode( $tag, $callback ) {}
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function wp_register_style( $handle, $src, $deps = array(), $version = false, $media = 'all' ) {}
function wp_register_script( $handle, $src, $deps = array(), $version = false, $in_footer = false ) {}
function wp_localize_script( $handle, $object_name, $data ) {}
function rest_url( $path = '' ) {
    return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}
function register_rest_route( $namespace, $route, $args ) {
    $GLOBALS['smoke_routes'][] = $namespace . $route;
}
function register_setting( $group, $name, $args = array() ) {
    $GLOBALS['smoke_settings'][] = $name;
}
function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback, $icon = '', $position = null ) {}
function wp_next_scheduled( $hook ) {
    return isset( $GLOBALS['smoke_scheduled'][ $hook ] ) ? $GLOBALS['smoke_scheduled'][ $hook ] : false;
}
function wp_schedule_event( $timestamp, $recurrence, $hook ) {
    $GLOBALS['smoke_scheduled'][ $hook ] = $timestamp;
    return true;
}
function wp_schedule_single_event( $timestamp, $hook ) {
    $GLOBALS['smoke_scheduled'][ $hook ] = $timestamp;
    return true;
}
function wp_clear_scheduled_hook( $hook ) {
    unset( $GLOBALS['smoke_scheduled'][ $hook ] );
    return true;
}
function get_transient( $key ) {
    return isset( $GLOBALS['dges_test_transients'][ $key ] ) ? $GLOBALS['dges_test_transients'][ $key ] : false;
}
function set_transient( $key, $value, $ttl = 0 ) {
    $GLOBALS['dges_test_transients'][ $key ] = $value;
    return true;
}
function delete_transient( $key ) {
    unset( $GLOBALS['dges_test_transients'][ $key ] );
    return true;
}
function wp_json_encode( $value ) {
    return json_encode( $value );
}
function get_posts( $args = array() ) {
    return array();
}
function get_post_field( $field, $post_id ) {
    return '';
}
function has_shortcode( $content, $tag ) {
    return false;
}
function wp_insert_post( $args, $wp_error = false ) {
    return 999;
}
function flush_rewrite_rules( $hard = true ) {}
function get_pages( $args = array() ) {
    return array();
}
function get_permalink( $post_id ) {
    return 'https://example.test/?page_id=' . (int) $post_id;
}
function get_post_type( $post_id ) {
    return 999 === (int) $post_id ? 'page' : false;
}
function get_post_status( $post_id ) {
    return 999 === (int) $post_id ? 'publish' : false;
}
function home_url( $path = '' ) {
    return 'https://example.test' . $path;
}
function get_query_var( $var ) {
    return '';
}
function is_page( $page = '' ) {
    return false;
}
function is_admin() {
    return false;
}
function wp_doing_ajax() {
    return false;
}
function status_header( $code ) {}
function nocache_headers() {}
function remove_action( $hook, $callback, $priority = 10 ) {}
function wp_safe_redirect( $url, $status = 302 ) {
    return true;
}
function get_site_icon_url() {
    return '';
}
function get_bloginfo( $show = '' ) {
    return 'Test';
}
function wp_get_referer() {
    return '';
}

$tests  = 0;
$failed = 0;

function wiring_check( $label, $condition, $actual = null ) {
    global $tests, $failed;
    $tests++;

    if ( $condition ) {
        echo "ok - {$label}\n";
        return;
    }

    $failed++;
    echo "FAIL - {$label}\n";
    if ( null !== $actual ) {
        echo '       actual: ' . var_export( $actual, true ) . "\n";
    }
}

DGES_Docs_Plugin::init();
wiring_check( 'Plugin::init no fatal', true );

DGES_Docs_Plugin::register_assets();
wiring_check( 'register_assets no fatal', true );

DGES_Docs_Rest::register_routes();
wiring_check( 'REST registra listado, detalle y categorías', 3 === count( $GLOBALS['smoke_routes'] ), $GLOBALS['smoke_routes'] );

DGES_Docs_Settings::register();
wiring_check( 'Settings registra 7 opciones', 7 === count( $GLOBALS['smoke_settings'] ), $GLOBALS['smoke_settings'] );
wiring_check( 'sanitize_mode permite index', 'index' === DGES_Docs_Settings::sanitize_mode( 'index' ) );
wiring_check( 'sanitize_mode rechaza otro valor', 'index' === DGES_Docs_Settings::sanitize_mode( 'otro' ) );
wiring_check( 'sanitize_per_page clampa a 50', 50 === DGES_Docs_Settings::sanitize_per_page( 999 ) );
wiring_check( 'sanitize_per_page vacío vuelve a 12', 12 === DGES_Docs_Settings::sanitize_per_page( 0 ) );
wiring_check( 'sanitize_featured_limit clampa a 20', 20 === DGES_Docs_Settings::sanitize_featured_limit( 999 ) );
wiring_check( 'sanitize_featured_limit vacío vuelve a 4', 4 === DGES_Docs_Settings::sanitize_featured_limit( 0 ) );

DGES_Docs_Plugin::activate();
wiring_check( 'activate guarda URL', false !== get_option( DGES_Docs_Source::OPTION_URL, false ) );
wiring_check( 'activate guarda per_page', 12 === (int) get_option( 'dges_documentos_per_page', 0 ) );
wiring_check( 'activate guarda modo index', 'index' === get_option( DGES_Docs_Repository::MODE_OPTION, '' ) );
wiring_check( 'activate crea página de detalle', 999 === (int) get_option( DGES_Docs_Router::OPTION_DETAIL_PAGE, 0 ), get_option( DGES_Docs_Router::OPTION_DETAIL_PAGE, 0 ) );
wiring_check( 'activate programa cron', isset( $GLOBALS['smoke_scheduled'][ DGES_Docs_Repository::CRON_HOOK ] ), $GLOBALS['smoke_scheduled'] );

DGES_Docs_Plugin::deactivate();
wiring_check( 'deactivate limpia cron', ! isset( $GLOBALS['smoke_scheduled'][ DGES_Docs_Repository::CRON_HOOK ] ), $GLOBALS['smoke_scheduled'] );

$module_tag = DGES_Docs_Plugin::module_script_tag( '<script src="x.js"></script>', 'dges-documentos' );
$normal_tag = DGES_Docs_Plugin::module_script_tag( '<script src="x.js"></script>', 'otro' );
wiring_check( 'module_script_tag agrega type module', false !== strpos( $module_tag, 'type="module"' ), $module_tag );
wiring_check( 'module_script_tag no toca otros handles', false === strpos( $normal_tag, 'type="module"' ), $normal_tag );

echo "\n{$tests} wiring tests, {$failed} failures\n";
exit( $failed > 0 ? 1 : 0 );
