<?php
/**
 * Consultas sobre el listado normalizado: búsqueda, categoría, orden y paginación.
 * Funciones puras para poder testearlas sin WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Query {

    const MAX_PER_PAGE = 50;

    /**
     * Normaliza texto para buscar: sin acentos, minúsculas, sin puntuación.
     */
    public static function normalize( $value ) {
        $value = self::clean_display( $value );
        $value = remove_accents( $value );
        $value = strtolower( $value );
        $value = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $value );

        if ( null === $value ) {
            return '';
        }

        $value = preg_replace( '/\s+/', ' ', $value );

        return null === $value ? '' : trim( $value );
    }

    /**
     * Limpia texto para mostrar: entidades, etiquetas, espacios y puntuación inicial.
     */
    public static function clean_display( $value ) {
        if ( ! is_scalar( $value ) ) {
            return '';
        }

        $value = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
        $value = wp_strip_all_tags( $value );
        $value = preg_replace( '/[\s\x{00A0}]+/u', ' ', $value );

        if ( null === $value ) {
            return '';
        }

        $value = trim( $value );
        $value = preg_replace( '/^[\s:;,.\-–—]+/u', '', $value );

        return null === $value ? '' : trim( $value );
    }

    /**
     * Divide una búsqueda en tokens (AND).
     */
    public static function tokens( $search ) {
        $search = self::normalize( $search );
        if ( '' === $search ) {
            return array();
        }
        $tokens = preg_split( '/\s+/', $search );
        $tokens = array_filter( array_unique( $tokens ), 'strlen' );
        return array_values( $tokens );
    }

    /**
     * Filtra por búsqueda de texto y categoría.
     *
     * @param array $items Items normalizados.
     * @param array $args  search|txtbusqueda, category|categoria.
     * @return array
     */
    public static function filter( $items, $args ) {
        $search   = isset( $args['search'] ) ? $args['search'] : ( isset( $args['txtbusqueda'] ) ? $args['txtbusqueda'] : '' );
        $category = isset( $args['category'] ) ? $args['category'] : ( isset( $args['categoria'] ) ? $args['categoria'] : '' );

        $tokens          = self::tokens( $search );
        $category        = trim( (string) $category );
        $category_norm   = self::normalize( $category );
        $category_is_id  = (bool) preg_match( '/^\d+$/', $category );

        if ( empty( $tokens ) && '' === $category ) {
            return array_values( $items );
        }

        $filtered = array();

        foreach ( $items as $item ) {
            if ( ! empty( $tokens ) ) {
                $haystack = isset( $item['search_text'] ) && '' !== $item['search_text']
                    ? $item['search_text']
                    : self::build_search_text( $item );

                foreach ( $tokens as $token ) {
                    if ( false === strpos( $haystack, $token ) ) {
                        continue 2;
                    }
                }
            }

            if ( '' !== $category && ! self::matches_category( $item, $category, $category_norm, $category_is_id ) ) {
                continue;
            }

            $filtered[] = $item;
        }

        return array_values( $filtered );
    }

    /**
     * ¿El item pertenece a la categoría pedida? Acepta id o nombre.
     */
    private static function matches_category( $item, $category, $category_norm, $category_is_id ) {
        $categories = isset( $item['categories'] ) && is_array( $item['categories'] ) ? $item['categories'] : array();

        foreach ( $categories as $cat ) {
            $id   = isset( $cat['id'] ) ? (string) $cat['id'] : '';
            $name = isset( $cat['name'] ) ? (string) $cat['name'] : '';

            if ( $category_is_id && '' !== $id && $id === $category ) {
                return true;
            }

            if ( ! $category_is_id && '' !== $name && false !== strpos( self::normalize( $name ), $category_norm ) ) {
                return true;
            }
        }

        if ( ! $category_is_id && ! empty( $item['category'] ) && false !== strpos( self::normalize( $item['category'] ), $category_norm ) ) {
            return true;
        }

        return false;
    }

    /**
     * Orden estable: año desc, mes desc, número desc, título asc.
     */
    public static function sort( $items ) {
        usort( $items, array( __CLASS__, 'compare' ) );
        return $items;
    }

    private static function compare( $a, $b ) {
        $year_a = isset( $a['year'] ) ? (int) $a['year'] : 0;
        $year_b = isset( $b['year'] ) ? (int) $b['year'] : 0;

        if ( $year_a !== $year_b ) {
            return $year_b <=> $year_a;
        }

        $month_a = isset( $a['month'] ) ? (int) $a['month'] : 0;
        $month_b = isset( $b['month'] ) ? (int) $b['month'] : 0;

        if ( $month_a !== $month_b ) {
            return $month_b <=> $month_a;
        }

        $num_a = isset( $a['number'] ) ? (string) $a['number'] : '';
        $num_b = isset( $b['number'] ) ? (string) $b['number'] : '';

        if ( '' !== $num_a && '' === $num_b ) {
            return -1;
        }
        if ( '' === $num_a && '' !== $num_b ) {
            return 1;
        }
        if ( '' !== $num_a && '' !== $num_b ) {
            $cmp = strnatcasecmp( $num_b, $num_a );
            if ( 0 !== $cmp ) {
                return $cmp;
            }
        }

        $title_a = isset( $a['title'] ) ? (string) $a['title'] : '';
        $title_b = isset( $b['title'] ) ? (string) $b['title'] : '';

        return strcasecmp( $title_a, $title_b );
    }

    /**
     * Pagina y devuelve el contrato que usa el REST.
     */
    public static function paginate( $items, $page, $per_page ) {
        $per_page = (int) $per_page;
        if ( $per_page < 1 ) {
            $per_page = 12;
        }
        $per_page = min( self::MAX_PER_PAGE, $per_page );

        $total       = count( $items );
        $total_pages = $total > 0 ? (int) ceil( $total / $per_page ) : 0;
        $page        = max( 1, (int) $page );

        if ( $total_pages > 0 && $page > $total_pages ) {
            $page = $total_pages;
        }

        $offset    = ( $page - 1 ) * $per_page;
        $items_page = array_slice( $items, $offset, $per_page );

        return array(
            'items'       => array_values( $items_page ),
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
            'has_more'    => $page < $total_pages,
        );
    }

    /**
     * Categorías únicas con conteo, ordenadas por nombre.
     */
    public static function categories( $items ) {
        $map = array();

        foreach ( $items as $item ) {
            $categories = isset( $item['categories'] ) && is_array( $item['categories'] ) ? $item['categories'] : array();

            foreach ( $categories as $cat ) {
                $id   = isset( $cat['id'] ) ? (string) $cat['id'] : '';
                $name = isset( $cat['name'] ) ? self::clean_display( $cat['name'] ) : '';

                if ( '' === $name ) {
                    continue;
                }

                $key = '' !== $id ? 'id:' . $id : 'name:' . strtolower( $name );

                if ( ! isset( $map[ $key ] ) ) {
                    $map[ $key ] = array(
                        'id'    => $id,
                        'name'  => $name,
                        'count' => 0,
                    );
                }

                $map[ $key ]['count']++;
            }
        }

        $categories = array_values( $map );

        usort( $categories, function ( $a, $b ) {
            return strcasecmp( $a['name'], $b['name'] );
        } );

        return $categories;
    }

    /**
     * Texto de búsqueda de un item (fallback si no viene precalculado).
     */
    public static function build_search_text( $item ) {
        $parts = array(
            isset( $item['title'] ) ? $item['title'] : '',
            isset( $item['description'] ) ? $item['description'] : '',
            isset( $item['number'] ) ? $item['number'] : '',
            isset( $item['year'] ) ? $item['year'] : '',
            isset( $item['document_type'] ) ? $item['document_type'] : '',
        );

        if ( ! empty( $item['categories'] ) && is_array( $item['categories'] ) ) {
            foreach ( $item['categories'] as $cat ) {
                if ( ! empty( $cat['name'] ) ) {
                    $parts[] = $cat['name'];
                }
            }
        }

        return self::normalize( implode( ' ', $parts ) );
    }
}
