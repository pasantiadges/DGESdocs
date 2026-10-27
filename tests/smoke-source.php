<?php
/**
 * Smoke test de Source (HTTP simulado, mapeo y errores).
 * Ejecutar: php tests/smoke-source.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-dges-docs-query.php';
require_once __DIR__ . '/../includes/class-dges-docs-source.php';

$GLOBALS['fake_http'] = array(
    'list'       => array(
        'documentos' => array(
            array(
                'id'          => '116',
                'nombre'      => 'Resolución Ministerial 116-2026',
                'observacion' => 'Propuestas de postítulos.',
                'numero'      => '116',
                'anio'        => '2026',
                'vigente'     => 'Si',
                'destacado'   => 'Si',
                'categorias'  => array( array( 'idcategoria' => '1', 'nombre' => 'Normativas DGES' ) ),
            ),
            array(
                'id'          => '117',
                'nombre'      => 'Memorándum 17-2026',
                'observacion' => 'Otra cosa.',
                'numero'      => '17',
                'anio'        => '2026',
                'vigente'     => 'No',
                'destacado'   => 'No',
                'categorias'  => array(),
            ),
        ),
        'total' => 2,
    ),
    'detail'     => array(
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
                'nombre'         => 'RM 116',
                'tipo'           => 'PDF',
                'carpeta'        => 'archivos/2026/10/23',
                'nombre_archivo' => 'a.pdf',
                'tamanio'        => '123',
                'url'            => 'https://example.test/a.pdf',
            ),
        ),
        'documentos_relacionados' => array(
            array(
                'iddocumento' => '117',
                'tipo'        => 'Complementa',
                'nombre'      => 'Memorándum 17-2026',
                'numero'      => '17',
                'anio'        => '2026',
                'vigente'     => 'No',
                'destacada'   => 'No',
            ),
        ),
    ),
    'categories' => array(
        'categorias' => array(
            array( 'idcategoria' => '1', 'nombre' => 'Normativas DGES', 'cantidad' => 2 ),
            array( 'idcategoria' => '2', 'nombre' => 'Curriculares', 'cantidad' => 1 ),
        ),
    ),
);

$GLOBALS['fake_http_status'] = 200;
$GLOBALS['fake_last_url']    = '';

function home_url( $path = '' ) {
    return 'https://example.test' . $path;
}

function add_query_arg( ...$args ) {
    if ( is_array( $args[0] ) ) {
        $params = $args[0];
        $url    = isset( $args[1] ) ? $args[1] : '';
    } else {
        $params = array( $args[0] => isset( $args[1] ) ? $args[1] : '' );
        $url    = isset( $args[2] ) ? $args[2] : '';
    }

    if ( empty( $params ) ) {
        return $url;
    }

    $separator = false === strpos( $url, '?' ) ? '?' : '&';

    return $url . $separator . http_build_query( $params );
}

function wp_safe_remote_get( $url, $args = array() ) {
    $GLOBALS['fake_last_url'] = $url;

    if ( 500 === $GLOBALS['fake_http_status'] ) {
        return array(
            'response' => array( 'code' => 500 ),
            'body'     => 'error',
        );
    }

    if ( false !== strpos( $url, 'iddocumento=999' ) ) {
        return array(
            'response' => array( 'code' => 200 ),
            'body'     => 'Exterior-CarrInst-List_Carreras: Codigo5: No se encontro el ID',
        );
    }

    if ( false !== strpos( $url, 'iddocumento=116' ) ) {
        return array(
            'response' => array( 'code' => 200 ),
            'body'     => json_encode( $GLOBALS['fake_http']['detail'] ),
        );
    }

    if ( false !== strpos( $url, 'list_categorias.php' ) ) {
        return array(
            'response' => array( 'code' => 200 ),
            'body'     => json_encode( $GLOBALS['fake_http']['categories'] ),
        );
    }

    return array(
        'response' => array( 'code' => 200 ),
        'body'     => json_encode( $GLOBALS['fake_http']['list'] ),
    );
}

function wp_remote_retrieve_response_code( $response ) {
    return $response['response']['code'];
}

function wp_remote_retrieve_body( $response ) {
    return $response['body'];
}

$tests  = 0;
$failed = 0;

function source_check( $label, $condition, $actual = null ) {
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

$GLOBALS['dges_test_options'][ DGES_Docs_Source::OPTION_URL ] = 'https://example.test/list_documentos.php';

$source = new DGES_Docs_Source();
$items  = $source->get_list();

source_check( 'get_list devuelve items', is_array( $items ) && 2 === count( $items ), $items );
source_check( 'get_list mapea número', '116' === $items[0]['number'], $items[0] );
source_check( 'get_list mapea destacado', true === $items[0]['featured'], $items[0] );
source_check( 'get_list respeta total de la API', 2 === $source->last_total, $source->last_total );

$source->get_list(
    array(
        'search'   => 'resolucion',
        'category' => '1',
        'limit'    => 10,
        'offset'   => 20,
    )
);
source_check( 'get_list envía filtros', false !== strpos( $GLOBALS['fake_last_url'], 'txtbusqueda=resolucion' ), $GLOBALS['fake_last_url'] );
source_check( 'get_list envía categoría', false !== strpos( $GLOBALS['fake_last_url'], 'categoria=1' ), $GLOBALS['fake_last_url'] );
source_check( 'get_list envía limit', false !== strpos( $GLOBALS['fake_last_url'], 'limit=10' ), $GLOBALS['fake_last_url'] );
source_check( 'get_list envía offset', false !== strpos( $GLOBALS['fake_last_url'], 'offset=20' ), $GLOBALS['fake_last_url'] );

$detail = $source->get_detail( '116' );
source_check( 'get_detail mapea archivos', is_array( $detail ) && 1 === count( $detail['files'] ), $detail );
source_check( 'get_detail mapea relacionados', is_array( $detail ) && 1 === count( $detail['related'] ), $detail );
source_check( 'get_detail usa url del archivo', 'https://example.test/a.pdf' === $detail['url'], $detail );
source_check( 'relacionado no vigente', false === $detail['related'][0]['current'], $detail['related'][0] );

$not_found = $source->get_detail( '999' );
source_check( 'detalle inexistente devuelve 404', is_wp_error( $not_found ) && 404 === $not_found->get_error_data()['status'], $not_found );

$invalid = $source->get_detail( 'abc' );
source_check( 'id inválido devuelve 404', is_wp_error( $invalid ) && 404 === $invalid->get_error_data()['status'], $invalid );

$categories = $source->get_categories();
source_check( 'get_categories devuelve categorías', is_array( $categories ) && 2 === count( $categories ), $categories );
source_check( 'get_categories mapea cantidad', 2 === $categories[0]['count'], $categories[0] );

$GLOBALS['fake_http_status'] = 500;
$error = $source->get_list();
source_check( 'error HTTP devuelve WP_Error', is_wp_error( $error ), $error );
source_check( 'error HTTP tiene status 503', 503 === $error->get_error_data()['status'], $error );

echo "\n{$tests} source tests, {$failed} failures\n";
exit( $failed > 0 ? 1 : 0 );
