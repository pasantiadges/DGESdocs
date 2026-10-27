<?php
/**
 * Smoke test de Render y wiring, con stubs de WordPress.
 * Ejecutar: php tests/smoke-render.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-dges-docs-query.php';
require_once __DIR__ . '/../includes/class-dges-docs-source.php';
require_once __DIR__ . '/../includes/class-dges-docs-render.php';
require_once __DIR__ . '/../includes/class-dges-docs-router.php';

function esc_html( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $text ) {
    return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
    return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function esc_attr__( $text, $domain = null ) {
    return esc_attr( __( $text, $domain ) );
}

function selected( $selected, $current = true, $echo = true ) {
    $result = (string) $selected === (string) $current ? ' selected="selected"' : '';

    if ( $echo ) {
        echo $result;
    }

    return $result;
}

function number_format_i18n( $number ) {
    return number_format( (float) $number );
}

function wp_trim_words( $text, $num_words = 55, $more = '…' ) {
    $words = preg_split( '/\s+/', trim( (string) $text ) );
    if ( count( $words ) <= $num_words ) {
        return implode( ' ', $words );
    }

    return implode( ' ', array_slice( $words, 0, $num_words ) ) . $more;
}

function size_format( $bytes, $decimals = 0 ) {
    return round( (int) $bytes / 1024, (int) $decimals ) . ' KB';
}

function sanitize_html_class( $class ) {
    return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
}

function get_permalink() {
    return isset( $GLOBALS['smoke_permalink'] ) ? $GLOBALS['smoke_permalink'] : 'https://example.test/documentos/';
}

function home_url( $path = '' ) {
    return 'https://example.test' . $path;
}

function add_query_arg( $args, $url ) {
    if ( empty( $args ) ) {
        return $url;
    }

    $separator = false === strpos( $url, '?' ) ? '?' : '&';

    return $url . $separator . http_build_query( $args );
}

function get_post_type( $post_id ) {
    return 999 === (int) $post_id ? 'page' : false;
}

function get_post_status( $post_id ) {
    return 999 === (int) $post_id ? 'publish' : false;
}

function wp_get_referer() {
    return '';
}

function remove_query_arg( $keys, $url ) {
    $parts = parse_url( $url );
    $query = array();

    if ( isset( $parts['query'] ) ) {
        parse_str( $parts['query'], $query );
    }

    foreach ( (array) $keys as $key ) {
        unset( $query[ $key ] );
    }

    $base = ( isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '' )
        . ( isset( $parts['host'] ) ? $parts['host'] : '' )
        . ( isset( $parts['path'] ) ? $parts['path'] : '' );

    return empty( $query ) ? $base : $base . '?' . http_build_query( $query );
}

$tests  = 0;
$failed = 0;

function smoke_check( $label, $condition, $actual = null ) {
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

$GLOBALS['dges_test_options'][ DGES_Docs_Router::OPTION_DETAIL_PAGE ] = 999;
$GLOBALS['dges_test_options']['dges_documentos_list_page'] = 0;
$GLOBALS['dges_test_options']['permalink_structure'] = '/%postname%/';

$raw = array(
    'id'          => '116',
    'nombre'      => 'Resolución Ministerial 116-2026',
    'observacion' => 'Referido a la aprobación de propuestas de postítulos.',
    'numero'      => '116',
    'anio'        => '2026',
    'vigente'     => 'No',
    'destacado'   => 'Si',
    'categorias'  => array(
        array( 'idcategoria' => '1', 'nombre' => 'Normativas DGES' ),
    ),
);

$item     = DGES_Docs_Source::normalize_document( $raw, false );
$card     = DGES_Docs_Render::card( $item, array( 'context' => 'list' ) );
$category = array( array( 'id' => '1', 'name' => 'Normativas DGES', 'count' => 1 ) );

smoke_check( 'card contiene clases base', false !== strpos( $card, 'dges-document-card' ), $card );
smoke_check( 'card contiene título escapado', false !== strpos( $card, 'Resolución Ministerial 116-2026' ), $card );
smoke_check( 'card muestra No vigente', false !== strpos( $card, 'No vigente' ), $card );
smoke_check( 'card muestra Destacado', false !== strpos( $card, 'Destacado' ), $card );
smoke_check( 'card muestra número', false !== strpos( $card, 'N° 116' ), $card );
smoke_check( 'card linkea al detalle', false !== strpos( $card, 'href="https://example.test/documentos/documento/116/"' ), $card );
smoke_check( 'card muestra Ver documento', false !== strpos( $card, 'Ver documento' ), $card );

$detail_raw = array(
    'id'          => '116',
    'nombre'      => 'Resolución Ministerial 116-2026',
    'observacion' => 'Propuestas de postítulos.',
    'numero'      => '116',
    'anio'        => '2026',
    'vigente'     => 'Si',
    'destacado'   => 'No',
    'categorias'  => array( array( 'idcategoria' => '1', 'nombre' => 'Normativas DGES' ) ),
    'archivos'    => array(
        array(
            'idarchivo'      => '45',
            'nombre'         => 'RM 116-2026.pdf',
            'tipo'           => 'PDF',
            'tamanio'        => '1234',
            'url'            => 'https://example.com/rm-116.pdf',
        ),
        array(
            'idarchivo'      => '46',
            'nombre'         => 'Anexo I',
            'tipo'           => 'DOCX',
            'tamanio'        => '5678',
        ),
    ),
    'documentos_relacionados' => array(
        array(
            'iddocumento' => '117',
            'tipo'        => 'Complementa',
            'nombre'      => 'Memorándum 17-2026',
            'numero'      => '17',
            'anio'        => '2026',
            'vigente'     => 'Si',
            'destacada'   => 'No',
        ),
    ),
);

$detail_item = DGES_Docs_Source::normalize_document( $detail_raw, true );
$single      = DGES_Docs_Render::single_document( $detail_item, array( 'variant' => 'compact', 'link' => 'file' ) );
$single_detail = DGES_Docs_Render::single_document( $detail_item, array( 'link' => 'detail' ) );
$single_no_desc = DGES_Docs_Render::single_document( $detail_item, array( 'show_description' => '0' ) );

$no_link_raw = $detail_raw;
$no_link_raw['archivos'] = array();
$no_link_raw['documentos_relacionados'] = array();
$no_link_item = DGES_Docs_Source::normalize_document( $no_link_raw, true );

$GLOBALS['dges_test_options'][ DGES_Docs_Router::OPTION_DETAIL_PAGE ] = 0;
$single_no_link = DGES_Docs_Render::single_document( $no_link_item, array() );
$GLOBALS['dges_test_options'][ DGES_Docs_Router::OPTION_DETAIL_PAGE ] = 999;

smoke_check( 'single card contiene wrapper', false !== strpos( $single, 'dges-documento-single--compact' ), $single );
smoke_check( 'single card contiene card', false !== strpos( $single, 'dges-single-card' ), $single );
smoke_check( 'single card no muestra bloque de archivos', false === strpos( $single, 'dges-single-files' ), $single );
smoke_check( 'single card no muestra título Archivos', false === strpos( $single, '>Archivos<' ), $single );
smoke_check( 'single card linkea archivo con url', false !== strpos( $single, 'href="https://example.com/rm-116.pdf"' ), $single );
smoke_check( 'single card etiqueta descarga', false !== strpos( $single, 'Descargar PDF' ), $single );
smoke_check( 'single card usa botón primario', false !== strpos( $single, 'dges-btn-primary' ), $single );
smoke_check( 'single card detalle linkea al detalle', false !== strpos( $single_detail, 'Ver documento' ) && false !== strpos( $single_detail, 'https://example.test/documentos/documento/116/' ), $single_detail );
smoke_check( 'single card sin enlace queda deshabilitada', false !== strpos( $single_no_link, 'Sin enlace disponible' ) && false !== strpos( $single_no_link, 'is-disabled' ), $single_no_link );
smoke_check( 'single card puede ocultar descripción', false === strpos( $single_no_desc, 'dges-single-description' ), $single_no_desc );

$detail_html = DGES_Docs_Render::detail( $detail_item );
smoke_check( 'detail contiene wrapper', false !== strpos( $detail_html, 'dges-documento-detalle' ), $detail_html );
smoke_check( 'detail contiene título', false !== strpos( $detail_html, 'Resolución Ministerial 116-2026' ), $detail_html );
smoke_check( 'detail contiene archivos', false !== strpos( $detail_html, 'dges-detail-files' ), $detail_html );
smoke_check( 'detail linkea archivo', false !== strpos( $detail_html, 'href="https://example.com/rm-116.pdf"' ), $detail_html );
smoke_check( 'detail contiene relacionados', false !== strpos( $detail_html, 'Documentos relacionados' ), $detail_html );
smoke_check( 'detail linkea relacionado', false !== strpos( $detail_html, 'https://example.test/documentos/documento/117/' ), $detail_html );
smoke_check( 'detail muestra relación', false !== strpos( $detail_html, 'dges-badge-relation' ) && false !== strpos( $detail_html, 'Complementa' ), $detail_html );
smoke_check( 'detail contiene volver', false !== strpos( $detail_html, 'Volver a documentos' ), $detail_html );

$not_found = DGES_Docs_Render::detail_not_found( '999' );
smoke_check( 'detail 404 contiene mensaje', false !== strpos( $not_found, 'Documento no encontrado' ), $not_found );

$result = array(
    'items'       => array( $item ),
    'total'       => 1,
    'page'        => 1,
    'per_page'    => 12,
    'total_pages' => 1,
    'has_more'    => false,
);

$html = DGES_Docs_Render::shortcode_buscador(
    'Documentos DGES',
    array( 'search' => '116', 'category' => '1', 'page' => 1 ),
    $result,
    $category,
    null
);

smoke_check( 'shortcode tiene data-dges-documentos', false !== strpos( $html, 'data-dges-documentos' ), $html );
smoke_check( 'shortcode conserva búsqueda', false !== strpos( $html, 'value="116"' ), $html );
smoke_check( 'shortcode selecciona categoría', false !== strpos( $html, '<option value="1" selected="selected">' ), $html );
smoke_check( 'shortcode muestra estado', false !== strpos( $html, '1 documento' ), $html );
smoke_check( 'shortcode muestra card', false !== strpos( $html, 'dges-document-card' ), $html );
smoke_check( 'shortcode tiene limpiar', false !== strpos( $html, 'data-clear-filters' ), $html );

$empty = DGES_Docs_Render::shortcode_buscador(
    'Documentos DGES',
    array( 'search' => 'zzz', 'category' => '1', 'page' => 1 ),
    array(
        'items'       => array(),
        'total'       => 0,
        'page'        => 1,
        'per_page'    => 12,
        'total_pages' => 0,
        'has_more'    => false,
    ),
    $category,
    null
);

smoke_check( 'empty state con búsqueda y categoría', false !== strpos( $empty, 'No encontramos documentos para' ), $empty );
smoke_check( 'empty state incluye limpiar', false !== strpos( $empty, 'Limpiar filtros' ), $empty );

$error = DGES_Docs_Render::shortcode_buscador(
    'Documentos DGES',
    array( 'search' => '', 'category' => '', 'page' => 1 ),
    new WP_Error( 'dges_test', 'Error de prueba' ),
    array(),
    new WP_Error( 'dges_test', 'Error de prueba' )
);

smoke_check( 'error state muestra mensaje', false !== strpos( $error, 'Error de prueba' ), $error );
smoke_check( 'error state muestra reintentar', false !== strpos( $error, 'Reintentar' ), $error );

$GLOBALS['smoke_permalink'] = 'https://example.test/?page_id=123';
$plain = DGES_Docs_Render::shortcode_buscador(
    'Documentos DGES',
    array( 'search' => '', 'category' => '', 'page' => 1 ),
    array(
        'items'       => array(),
        'total'       => 0,
        'page'        => 1,
        'per_page'    => 12,
        'total_pages' => 0,
        'has_more'    => false,
    ),
    array(),
    null
);
smoke_check( 'permalink simple conserva page_id', false !== strpos( $plain, 'name="page_id"' ), $plain );
smoke_check( 'permalink simple conserva valor de page_id', false !== strpos( $plain, 'value="123"' ), $plain );
unset( $GLOBALS['smoke_permalink'] );

echo "\n{$tests} smoke tests, {$failed} failures\n";
exit( $failed > 0 ? 1 : 0 );
