<?php
/**
 * Tests de la lógica pura de la Fase 1.
 * Ejecutar: php tests/test-phase1.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-dges-docs-query.php';
require_once __DIR__ . '/../includes/class-dges-docs-source.php';

$GLOBALS['dges_tests']  = 0;
$GLOBALS['dges_failed'] = 0;

function check( $label, $condition, $actual = null ) {
    $GLOBALS['dges_tests']++;

    if ( $condition ) {
        echo "ok - {$label}\n";
        return;
    }

    $GLOBALS['dges_failed']++;
    echo "FAIL - {$label}\n";

    if ( null !== $actual ) {
        echo '       actual: ' . var_export( $actual, true ) . "\n";
    }
}

function check_same( $expected, $actual, $label ) {
    check( $label . ' (' . var_export( $expected, true ) . ')', $expected === $actual, $actual );
}

// Normalización.
check_same( 'resolucion n 116 2026', DGES_Docs_Query::normalize( 'Resolución N° 116/2026' ), 'normalize quita acentos y puntuación' );
check_same( 'Hola & mundo', DGES_Docs_Query::clean_display( ' :; <b>Hola</b> &amp;  mundo  ' ), 'clean_display limpia entidades, tags y prefijos' );
check_same( array( 'resolucion', '116' ), DGES_Docs_Query::tokens( 'Resolución 116' ), 'tokens divide en AND' );

// Tipos y metadatos.
check_same( 'Resolución Ministerial', DGES_Docs_Source::infer_document_type( 'Resolución Ministerial 116-2026' ), 'tipo Resolución Ministerial' );
check_same( 'Memorándum Conjunto', DGES_Docs_Source::infer_document_type( 'Memorándum Conjunto 01/2026' ), 'tipo Memorándum Conjunto' );
check_same( 'Ley', DGES_Docs_Source::infer_document_type( 'Ley 123' ), 'tipo Ley' );
check_same( 'Documento', DGES_Docs_Source::infer_document_type( 'prueba12' ), 'tipo Documento fallback' );

$inferred = DGES_Docs_Source::infer_number_year( 'Resolución 1377-13' );
check_same( '1377', $inferred['number'], 'número inferido' );
check_same( 2013, $inferred['year'], 'año de dos dígitos inferido' );

check( 'is_yes Sí', true === DGES_Docs_Source::is_yes( 'Sí' ) );
check( 'is_yes No', false === DGES_Docs_Source::is_yes( 'No' ) );
check( 'is_yes 1', true === DGES_Docs_Source::is_yes( '1' ) );

// Mapeo de listado.
$list_raw = array(
    'id'          => '116',
    'nombre'      => 'Resolución Ministerial 116-2026',
    'observacion' => 'Referido a la aprobación de propuestas de postítulos.',
    'numero'      => '116',
    'anio'        => '2026',
    'vigente'     => 'Si',
    'destacado'   => 'Si',
    'categorias'  => array(
        array( 'idcategoria' => '1', 'nombre' => 'Normativas DGES' ),
    ),
);

$list_item = DGES_Docs_Source::normalize_document( $list_raw, false );

check_same( '116', $list_item['id'], 'id mapeado' );
check_same( 'Resolución Ministerial 116-2026', $list_item['title'], 'título mapeado' );
check_same( 2026, $list_item['year'], 'año mapeado' );
check_same( '116', $list_item['number'], 'número mapeado' );
check_same( 'Normativas DGES', $list_item['category'], 'categoría principal' );
check_same( 1, count( $list_item['categories'] ), 'categorías mapeadas' );
check_same( true, $list_item['featured'], 'destacado booleano' );
check_same( true, $list_item['current'], 'vigente booleano' );
check( 'search_text incluye número', false !== strpos( $list_item['search_text'], '116' ), $list_item['search_text'] );
check( 'search_text incluye acento normalizado', false !== strpos( $list_item['search_text'], 'resolucion' ), $list_item['search_text'] );
check_same( array(), $list_item['files'], 'listado sin archivos' );

$no_vigente = DGES_Docs_Source::normalize_document( array( 'id' => '1', 'nombre' => 'Prueba', 'vigente' => 'No' ), false );
check_same( false, $no_vigente['current'], 'no vigente booleano' );

// Detalle con archivos y relacionados.
$detail_raw = array(
    'id'          => '116',
    'nombre'      => 'Resolución Ministerial 116-2026',
    'observacion' => 'Referido a la aprobación de propuestas de postítulos.',
    'numero'      => '116',
    'anio'        => '2026',
    'vigente'     => 'Si',
    'destacado'   => 'No',
    'categorias'  => array(
        array( 'idcategoria' => '1', 'nombre' => 'Normativas DGES' ),
    ),
    'archivos'    => array(
        array(
            'idarchivo'      => '45',
            'nombre'         => 'RM 116-2026',
            'descripcion'    => 'Propuestas de postítulos',
            'tipo'           => 'PDF',
            'carpeta'        => 'archivos/2026/10/23',
            'nombre_archivo' => '2026_10_23.pdf',
            'tamanio'        => '12345',
            'url'            => 'https://example.com/2026_10_23.pdf',
        ),
        array(
            'idarchivo'      => '46',
            'nombre'         => 'Anexo I',
            'tipo'           => 'DOCX',
            'nombre_archivo' => 'anexo.docx',
            'tamanio'        => '456',
        ),
    ),
    'documentos_relacionados' => array(
        array(
            'iddocumento' => '4',
            'tipo'        => 'Complementa',
            'nombre'      => 'Resolución Ministerial 337-2025',
            'numero'      => '337',
            'anio'        => '2025',
            'vigente'     => 'Si',
            'destacada'   => 'No',
        ),
        array(
            'iddocumento' => '116',
            'tipo'        => 'Complementa',
            'nombre'      => 'Auto-referencia',
        ),
    ),
);

$detail_item = DGES_Docs_Source::normalize_document( $detail_raw, true );

check_same( 2, count( $detail_item['files'] ), 'archivos mapeados' );
check_same( 'PDF', $detail_item['file_type'], 'tipo de archivo principal' );
check_same( 12345, $detail_item['file_size'], 'tamaño del archivo principal' );
check_same( 'https://example.com/2026_10_23.pdf', $detail_item['url'], 'url del archivo principal' );
check_same( 'DOCX', $detail_item['files'][1]['type'], 'tipo del anexo' );
check_same( 1, count( $detail_item['related'] ), 'relacionados sin auto-referencia' );
check_same( '4', $detail_item['related'][0]['id'], 'id de relacionado normalizado' );
check_same( 'Complementa', $detail_item['related'][0]['relation'], 'tipo de relación' );
check_same( false, $detail_item['related'][0]['featured'], 'destacada normalizada a featured' );

// Búsqueda y categoría.
$items = array(
    array(
        'id'          => '1',
        'title'       => 'Resolución 116/2026',
        'search_text' => 'resolucion 116 2026 postitulos normativas dges',
        'categories'  => array( array( 'id' => '1', 'name' => 'Normativas DGES' ) ),
        'year'        => 2026,
        'number'      => '116',
    ),
    array(
        'id'          => '2',
        'title'       => 'Resolución 337/2025',
        'search_text' => 'resolucion 337 2025 postitulos normativas dges',
        'categories'  => array( array( 'id' => '1', 'name' => 'Normativas DGES' ) ),
        'year'        => 2025,
        'number'      => '337',
    ),
    array(
        'id'          => '3',
        'title'       => 'Memorándum 16/2026',
        'search_text' => 'memorandum 16 2026 curriculares',
        'categories'  => array( array( 'id' => '2', 'name' => 'Curriculares' ) ),
        'year'        => 2026,
        'number'      => '16',
    ),
);

check_same( 1, count( DGES_Docs_Query::filter( $items, array( 'search' => 'resolucion 116' ) ) ), 'búsqueda AND multi-palabra' );
check_same( 2, count( DGES_Docs_Query::filter( $items, array( 'search' => 'postitulos' ) ) ), 'búsqueda por descripción' );
check_same( 2, count( DGES_Docs_Query::filter( $items, array( 'search' => 'resolucion' ) ) ), 'búsqueda por título' );
check_same( 2, count( DGES_Docs_Query::filter( $items, array( 'category' => '1' ) ) ), 'filtro por id de categoría' );
check_same( 2, count( DGES_Docs_Query::filter( $items, array( 'category' => 'normativas' ) ) ), 'filtro por nombre de categoría' );
check_same( 0, count( DGES_Docs_Query::filter( $items, array( 'category' => '3' ) ) ), 'categoría sin resultados' );
check_same( 3, count( DGES_Docs_Query::filter( $items, array() ) ), 'sin filtros devuelve todo' );

// Paginación.
$page2 = DGES_Docs_Query::paginate( $items, 2, 2 );
check_same( 1, count( $page2['items'] ), 'página 2 con 3 items y per_page 2' );
check_same( 3, $page2['total'], 'total de paginación' );
check_same( 2, $page2['total_pages'], 'total de páginas' );
check_same( false, $page2['has_more'], 'última página sin has_more' );

$page99 = DGES_Docs_Query::paginate( $items, 99, 2 );
check_same( 2, $page99['page'], 'página fuera de rango se clampa' );
check_same( 1, count( $page99['items'] ), 'página clampada devuelve la última' );

// Orden.
$sorted = DGES_Docs_Query::sort( $items );
check_same( '1', $sorted[0]['id'], 'orden por año desc y número desc' );
check_same( '3', $sorted[1]['id'], 'segundo por año 2026' );
check_same( '2', $sorted[2]['id'], 'último por año 2025' );

$monthly = array(
    array( 'id' => 'a', 'year' => 2026, 'month' => 10, 'number' => '1', 'title' => 'A' ),
    array( 'id' => 'b', 'year' => 2026, 'month' => 3, 'number' => '99', 'title' => 'B' ),
    array( 'id' => 'c', 'year' => 2025, 'month' => 12, 'number' => '5', 'title' => 'C' ),
);
check_same( array( 'a', 'b', 'c' ), array_column( DGES_Docs_Query::sort( $monthly ), 'id' ), 'orden por año y mes desc antes que número' );

// Categorías.
$cats = DGES_Docs_Query::categories( $items );
check_same( 2, count( $cats ), 'categorías únicas' );
check_same( 'Curriculares', $cats[0]['name'], 'categorías ordenadas por nombre' );
check_same( 2, $cats[1]['count'], 'conteo de categoría' );

// URLs de archivos.
check_same(
    'https://example.com/a.pdf',
    DGES_Docs_Source::resolve_file_url( array( 'url' => 'https://example.com/a.pdf' ) ),
    'url absoluta respetada'
);
check_same(
    '',
    DGES_Docs_Source::resolve_file_url( array( 'url' => 'javascript:alert(1)' ) ),
    'url javascript rechazada'
);

$GLOBALS['dges_test_options']['dges_documentos_files_base'] = 'https://example.com/bd_dges/';
check_same(
    'https://example.com/bd_dges/archivos/2026/10/23/a.pdf',
    DGES_Docs_Source::resolve_file_url(
        array(
            'carpeta'        => 'archivos/2026/10/23',
            'nombre_archivo' => 'a.pdf',
        )
    ),
    'url construida desde la base'
);

$GLOBALS['dges_test_options']['dges_documentos_files_base'] = 'https://example.com/bd_dges/archivos/';
check_same(
    'https://example.com/bd_dges/archivos/2026/10/23/a.pdf',
    DGES_Docs_Source::resolve_file_url(
        array(
            'carpeta'        => 'archivos/2026/10/23',
            'nombre_archivo' => 'a.pdf',
        )
    ),
    'url sin duplicar archivos/'
);

$GLOBALS['dges_test_options']['dges_documentos_files_base'] = 'https://example.com/bd_dges/archivos';
check_same(
    'https://example.com/bd_dges/archivos/2026/10/23/a.pdf',
    DGES_Docs_Source::resolve_file_url(
        array(
            'carpeta'        => 'archivos/2026/10/23',
            'nombre_archivo' => 'a.pdf',
        )
    ),
    'url sin duplicar archivos/ sin slash final'
);

echo "\n";
echo sprintf( "%d tests, %d failures\n", $GLOBALS['dges_tests'], $GLOBALS['dges_failed'] );

exit( $GLOBALS['dges_failed'] > 0 ? 1 : 0 );
