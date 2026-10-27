<?php
/**
 * Smoke test de Index_Repository con un refresh simulado.
 * Ejecutar: php tests/smoke-repository.php
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../includes/class-dges-docs-query.php';
require_once __DIR__ . '/../includes/class-dges-docs-source.php';
require_once __DIR__ . '/../includes/class-dges-docs-repository.php';

$GLOBALS['dges_test_transients'] = array();

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

function wp_next_scheduled( $hook ) {
    return false;
}

function wp_schedule_event( $timestamp, $recurrence, $hook ) {
    return true;
}

function wp_schedule_single_event( $timestamp, $hook ) {
    return true;
}

function wp_clear_scheduled_hook( $hook ) {
    return true;
}

function wp_json_encode( $value ) {
    return json_encode( $value );
}

class Fixture_Index_Repository extends DGES_Docs_Index_Repository {
    private $fixture;

    public function __construct( $fixture ) {
        parent::__construct();
        $this->fixture = $fixture;
    }

    public function refresh() {
        return $this->fixture;
    }
}

$tests  = 0;
$failed = 0;

function repo_check( $label, $condition, $actual = null ) {
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

$items = array(
    array(
        'id'          => '1',
        'title'       => 'Resolución 116/2026',
        'search_text' => 'resolucion 116 2026 postitulos normativas dges',
        'categories'  => array( array( 'id' => '1', 'name' => 'Normativas DGES' ) ),
        'year'        => 2026,
        'number'      => '116',
        'featured'    => true,
        'current'     => true,
    ),
    array(
        'id'          => '2',
        'title'       => 'Resolución 337/2025',
        'search_text' => 'resolucion 337 2025 postitulos normativas dges',
        'categories'  => array( array( 'id' => '1', 'name' => 'Normativas DGES' ) ),
        'year'        => 2025,
        'number'      => '337',
        'featured'    => false,
        'current'     => true,
    ),
    array(
        'id'          => '3',
        'title'       => 'Memorándum 16/2026',
        'search_text' => 'memorandum 16 2026 curriculares',
        'categories'  => array( array( 'id' => '2', 'name' => 'Curriculares' ) ),
        'year'        => 2026,
        'number'      => '16',
        'featured'    => true,
        'current'     => false,
    ),
);

$repo = new Fixture_Index_Repository( $items );

$all = $repo->query( array( 'page' => 1, 'per_page' => 10 ) );
repo_check( 'query devuelve todos', 3 === $all['total'], $all );
repo_check( 'query ordena por año y número', '1' === $all['items'][0]['id'], $all['items'] );

$search = $repo->query( array( 'search' => 'resolucion 116', 'page' => 1, 'per_page' => 10 ) );
repo_check( 'query filtra búsqueda', 1 === $search['total'], $search );

$category = $repo->query( array( 'category' => '1', 'page' => 1, 'per_page' => 10 ) );
repo_check( 'query filtra categoría', 2 === $category['total'], $category );

$page1 = $repo->query( array( 'page' => 1, 'per_page' => 2 ) );
$page2 = $repo->query( array( 'page' => 2, 'per_page' => 2 ) );
repo_check( 'query página 1 tiene has_more', true === $page1['has_more'], $page1 );
repo_check( 'query pagina', 2 === $page2['page'], $page2 );
repo_check( 'query última página sin has_more', false === $page2['has_more'], $page2 );
repo_check( 'query página 2 tiene un item', 1 === count( $page2['items'] ), $page2 );

$cats = $repo->categories();
repo_check( 'categories cuenta', 2 === count( $cats ), $cats );

$featured = $repo->featured( 2 );
repo_check( 'featured filtra y limita', 2 === count( $featured ), $featured );
repo_check( 'featured primero es 2026', '1' === $featured[0]['id'], $featured );

echo "\n{$tests} repository tests, {$failed} failures\n";
exit( $failed > 0 ? 1 : 0 );
