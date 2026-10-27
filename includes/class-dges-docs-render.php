<?php
/**
 * Presentación HTML: shortcodes, cards y estados.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Render {

    /**
     * Shortcode principal. La primera página se renderiza en PHP; JS mejora la interacción.
     *
     * @param string       $title
     * @param array        $state      search, category, page.
     * @param array|WP_Error $result   Resultado paginado del repository.
     * @param array        $categories
     * @param WP_Error|null $error
     * @return string
     */
    public static function shortcode_buscador( $title, $state, $result, $categories, $error = null ) {
        if ( ! is_array( $state ) ) {
            $state = array();
        }
        if ( ! is_array( $categories ) ) {
            $categories = array();
        }

        $search   = isset( $state['search'] ) ? (string) $state['search'] : '';
        $category = isset( $state['category'] ) ? (string) $state['category'] : '';

        $items      = array();
        $pagination = array(
            'page'        => 1,
            'per_page'    => 12,
            'total'       => 0,
            'total_pages' => 0,
            'has_more'    => false,
        );

        if ( ! is_wp_error( $result ) && is_array( $result ) ) {
            $items      = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();
            $pagination = array_merge( $pagination, $result );
        }

        $has_error = is_wp_error( $error ) || is_wp_error( $result );
        $error_obj = is_wp_error( $error ) ? $error : ( is_wp_error( $result ) ? $result : null );

        $action = self::clean_action_url();
        $count  = count( $items );
        $total  = (int) $pagination['total'];

        $html  = '<section class="dges-documentos" data-dges-documentos'
            . ' data-initial-search="' . esc_attr( $search ) . '"'
            . ' data-initial-category="' . esc_attr( $category ) . '"'
            . ' data-initial-page="' . esc_attr( (int) $pagination['page'] ) . '"'
            . ' data-initial-total="' . esc_attr( $total ) . '"'
            . ' data-initial-count="' . esc_attr( $count ) . '"'
            . ' data-initial-has-more="' . ( ! empty( $pagination['has_more'] ) ? '1' : '0' ) . '"'
            . ' data-initial-error="' . ( $has_error ? '1' : '0' ) . '">';
        $html .= '<h2>' . esc_html( $title ) . '</h2>';

        $html .= '<form class="dges-search-bar" data-dges-search-form role="search" method="get" action="' . esc_url( $action ) . '">';

        // En permalinks simples, el formulario GET reemplaza la query: preservamos page_id y otros parámetros.
        $action_query  = wp_parse_url( $action, PHP_URL_QUERY );
        $action_params = array();

        if ( $action_query ) {
            parse_str( $action_query, $action_params );
        }

        foreach ( $action_params as $param_key => $param_value ) {
            if ( in_array( $param_key, array( 'buscar', 'categoria', 'pagina' ), true ) ) {
                continue;
            }

            if ( is_array( $param_value ) ) {
                foreach ( $param_value as $param_item ) {
                    if ( is_scalar( $param_item ) ) {
                        $html .= '<input type="hidden" name="' . esc_attr( $param_key ) . '[]" value="' . esc_attr( $param_item ) . '">';
                    }
                }
            } elseif ( is_scalar( $param_value ) ) {
                $html .= '<input type="hidden" name="' . esc_attr( $param_key ) . '" value="' . esc_attr( $param_value ) . '">';
            }
        }

        $html .= '<div class="dges-search-field">';
        $html .= '<input type="search" name="buscar" data-search class="dges-search-input" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Buscar por título, descripción o número…', 'dges-documentos' ) . '" autocomplete="off" aria-label="' . esc_attr__( 'Buscar documentos', 'dges-documentos' ) . '">';
        $html .= '<button type="button" class="dges-search-clear" data-clear-search aria-label="' . esc_attr__( 'Limpiar búsqueda', 'dges-documentos' ) . '"' . ( '' === $search ? ' hidden' : '' ) . '>&times;</button>';
        $html .= '</div>';

        $html .= '<select name="categoria" data-category class="dges-category-select" aria-label="' . esc_attr__( 'Filtrar por categoría', 'dges-documentos' ) . '">';
        $html .= '<option value="">' . esc_html__( 'Todas las categorías', 'dges-documentos' ) . '</option>';

        $category_found = false;

        foreach ( $categories as $cat ) {
            $id   = isset( $cat['id'] ) ? (string) $cat['id'] : '';
            $name = isset( $cat['name'] ) ? (string) $cat['name'] : '';
            if ( '' === $name ) {
                continue;
            }
            if ( $id === $category ) {
                $category_found = true;
            }
            $html .= '<option value="' . esc_attr( $id ) . '"' . selected( $category, $id, false ) . '>' . esc_html( $name ) . '</option>';
        }

        if ( '' !== $category && ! $category_found ) {
            $html .= '<option value="' . esc_attr( $category ) . '" selected="selected">' . esc_html( $category ) . '</option>';
        }

        $html .= '</select>';

        $html .= '<button type="submit" class="dges-search-submit" aria-label="' . esc_attr__( 'Buscar', 'dges-documentos' ) . '">';
        $html .= '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>';
        $html .= '<span class="dges-search-submit-label">' . esc_html__( 'Buscar', 'dges-documentos' ) . '</span>';
        $html .= '</button>';

        $html .= '<button type="button" class="dges-clear-filters" data-clear-filters' . ( ( '' === $search && '' === $category ) ? ' hidden' : '' ) . '>' . esc_html__( 'Limpiar', 'dges-documentos' ) . '</button>';
        $html .= '</form>';

        $status_text = $has_error ? '' : self::status_text( $count, $total );
        $html .= '<p class="dges-documentos-status" data-status aria-live="polite"' . ( '' === $status_text ? ' hidden' : '' ) . '>' . esc_html( $status_text ) . '</p>';

        $html .= '<div class="dges-documentos-results" data-results>';

        foreach ( $items as $item ) {
            $html .= self::card( $item, array( 'context' => 'list' ) );
        }

        $html .= '</div>';

        $html .= self::empty_state( $state, $categories, $has_error, ( 0 === $count && ! $has_error ) );
        $html .= self::error_state( $error_obj, $state );
        $html .= self::pagination( $state, $pagination );

        $html .= '</section>';

        return $html;
    }

    /**
     * Shortcode de destacados. Server-rendered, sin JS.
     *
     * @param array  $items
     * @param string $title
     * @return string
     */
    public static function shortcode_destacados( $items, $title ) {
        if ( ! is_array( $items ) ) {
            $items = array();
        }

        $output  = '<section class="dges-documentos dges-destacados-section">';
        $output .= '<h2>' . esc_html( $title ) . '</h2>';
        $output .= '<div class="dges-documentos-results">';

        if ( empty( $items ) ) {
            $output .= '<p class="dges-empty-inline">' . esc_html__( 'No hay documentos destacados disponibles.', 'dges-documentos' ) . '</p>';
        } else {
            foreach ( $items as $item ) {
                $output .= self::card( $item, array( 'context' => 'destacado' ) );
            }
        }

        $output .= '</div></section>';

        return $output;
    }

    /**
     * Card reutilizable por listado, destacados y (más adelante) detalle.
     *
     * @param array $item
     * @param array $args context, link, file_id, show_description, show_meta, class.
     * @return string
     */
    public static function card( $item, $args = array() ) {
        if ( ! is_array( $item ) ) {
            return '';
        }

        $args = wp_parse_args(
            $args,
            array(
                'context'          => 'list',
                'link'             => 'detail',
                'file_id'          => '',
                'show_description' => true,
                'show_meta'        => true,
                'class'            => '',
            )
        );

        $title       = isset( $item['title'] ) ? (string) $item['title'] : '';
        $description = isset( $item['description'] ) ? (string) $item['description'] : '';
        $type        = ! empty( $item['document_type'] ) ? (string) $item['document_type'] : ( ! empty( $item['file_type'] ) ? (string) $item['file_type'] : __( 'Documento', 'dges-documentos' ) );
        $number      = isset( $item['number'] ) ? (string) $item['number'] : '';
        $year        = isset( $item['year'] ) ? (int) $item['year'] : 0;
        $category    = ! empty( $item['category'] ) ? (string) $item['category'] : '';
        $featured    = ! empty( $item['featured'] );
        $current     = ! isset( $item['current'] ) || $item['current'];
        $relation    = isset( $item['relation'] ) ? (string) $item['relation'] : '';

        $href       = '';
        $link_label = '';
        $external   = false;

        if ( 'detail' === $args['link'] ) {
            $href = self::detail_url( isset( $item['id'] ) ? $item['id'] : '' );
        } elseif ( 'file' === $args['link'] ) {
            $href = self::file_url( $item, $args['file_id'] );
            $external = true;
        }

        if ( '' !== $href ) {
            $link_label = 'file' === $args['link']
                ? sprintf( __( 'Descargar %s', 'dges-documentos' ), $type )
                : __( 'Ver documento', 'dges-documentos' );
        }

        $classes = 'dges-document-card';
        if ( ! empty( $args['class'] ) ) {
            $extra_classes = preg_split( '/\s+/', trim( (string) $args['class'] ) );

            foreach ( $extra_classes as $extra_class ) {
                if ( '' !== $extra_class ) {
                    $classes .= ' ' . sanitize_html_class( $extra_class );
                }
            }
        }
        if ( ! $current ) {
            $classes .= ' is-not-current';
        }

        $html  = '<article class="' . esc_attr( $classes ) . '" data-document-id="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
        $html .= '<div class="dges-card-header">';
        $html .= '<span class="dges-document-type">' . esc_html( $type ) . '</span>';

        if ( $featured ) {
            $html .= '<span class="dges-badge-destacado">' . esc_html__( '★ Destacado', 'dges-documentos' ) . '</span>';
        }
        if ( '' !== $relation && 'related' === $args['context'] ) {
            $html .= '<span class="dges-badge-relation">' . esc_html( $relation ) . '</span>';
        }
        if ( ! $current ) {
            $html .= '<span class="dges-badge-no-vigente">' . esc_html__( 'No vigente', 'dges-documentos' ) . '</span>';
        }

        $html .= '</div>';

        if ( '' !== $href ) {
            $html .= '<h3><a href="' . esc_url( $href ) . '"' . ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $title ) . '</a></h3>';
        } else {
            $html .= '<h3>' . esc_html( $title ) . '</h3>';
        }

        if ( $args['show_description'] && '' !== $description ) {
            $html .= '<p>' . esc_html( wp_trim_words( $description, 28, '…' ) ) . '</p>';
        }

        if ( $args['show_meta'] ) {
            $meta = array();

            if ( '' !== $category ) {
                $meta[] = '<span>' . esc_html( $category ) . '</span>';
            }
            if ( $year > 0 ) {
                $meta[] = '<span>' . sprintf( esc_html__( 'Año %s', 'dges-documentos' ), esc_html( (string) $year ) ) . '</span>';
            }
            if ( '' !== $number ) {
                $meta[] = '<span>' . sprintf( esc_html__( 'N° %s', 'dges-documentos' ), esc_html( $number ) ) . '</span>';
            }

            if ( ! empty( $meta ) ) {
                $html .= '<div class="dges-card-meta">' . implode( '', $meta ) . '</div>';
            }
        }

        if ( '' !== $href && '' !== $link_label ) {
            $html .= '<a class="dges-btn-open" href="' . esc_url( $href ) . '"' . ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $link_label ) . '</a>';
        }

        $html .= '</article>';

        return $html;
    }

    /**
     * Card breve de un solo documento para el shortcode [dges_documento].
     * Insignia + título + una línea de descripción + acción.
     */
    public static function single_document( $item, $args = array() ) {
        if ( ! is_array( $item ) ) {
            return '';
        }

        $args = wp_parse_args(
            $args,
            array(
                'variant'          => 'default',
                'show_description' => '1',
                'show_meta'        => '1',
                'link'             => 'detail',
                'file'             => '',
                'class'            => '',
            )
        );

        $variant = in_array( $args['variant'], array( 'default', 'compact', 'horizontal' ), true ) ? $args['variant'] : 'default';
        $link    = 'file' === $args['link'] ? 'file' : 'detail';

        $title       = isset( $item['title'] ) ? (string) $item['title'] : '';
        $description = isset( $item['description'] ) ? (string) $item['description'] : '';
        $number      = isset( $item['number'] ) ? (string) $item['number'] : '';
        $year        = isset( $item['year'] ) ? (int) $item['year'] : 0;
        $category    = ! empty( $item['category'] ) ? (string) $item['category'] : '';
        $current     = ! isset( $item['current'] ) || $item['current'];
        $file_type   = ! empty( $item['file_type'] ) ? (string) $item['file_type'] : '';

        $type = ! empty( $item['document_type'] ) ? (string) $item['document_type'] : $file_type;
        if ( '' === $type ) {
            $type = __( 'Documento', 'dges-documentos' );
        }
        if ( __( 'Documento', 'dges-documentos' ) === $type && '' !== $category ) {
            $type = $category;
        }

        $detail_url = self::detail_url( isset( $item['id'] ) ? $item['id'] : '' );
        $file_url   = self::file_url( $item, $args['file'] );

        $href     = '';
        $label    = '';
        $external = false;

        if ( 'file' === $link ) {
            if ( '' !== $file_url ) {
                $href     = $file_url;
                $label    = '' !== $file_type
                    ? sprintf( __( 'Descargar %s', 'dges-documentos' ), $file_type )
                    : __( 'Descargar archivo', 'dges-documentos' );
                $external = true;
            } elseif ( '' !== $detail_url ) {
                $href  = $detail_url;
                $label = __( 'Ver documento', 'dges-documentos' );
            }
        } else {
            if ( '' !== $detail_url ) {
                $href  = $detail_url;
                $label = __( 'Ver documento', 'dges-documentos' );
            } elseif ( '' !== $file_url ) {
                $href     = $file_url;
                $label    = '' !== $file_type
                    ? sprintf( __( 'Descargar %s', 'dges-documentos' ), $file_type )
                    : __( 'Descargar archivo', 'dges-documentos' );
                $external = true;
            }
        }

        if ( '' === $href ) {
            $label = __( 'Sin enlace disponible', 'dges-documentos' );
        }

        $meta = array();

        if ( self::to_bool( $args['show_meta'] ) ) {
            if ( $year > 0 && false === strpos( $title, (string) $year ) ) {
                $meta[] = sprintf( __( 'Año %s', 'dges-documentos' ), (string) $year );
            }
            if ( '' !== $number && false === strpos( $title, $number ) ) {
                $meta[] = sprintf( __( 'N° %s', 'dges-documentos' ), $number );
            }
        }

        $classes = 'dges-document-card dges-single-card';

        if ( ! empty( $args['class'] ) ) {
            foreach ( preg_split( '/\s+/', trim( (string) $args['class'] ) ) as $extra_class ) {
                if ( '' !== $extra_class ) {
                    $classes .= ' ' . sanitize_html_class( $extra_class );
                }
            }
        }

        if ( ! $current ) {
            $classes .= ' is-not-current';
        }

        $html  = '<div class="dges-documentos dges-documento-single dges-documento-single--' . esc_attr( $variant ) . '">';
        $html .= '<article class="' . esc_attr( $classes ) . '" data-document-id="' . esc_attr( isset( $item['id'] ) ? $item['id'] : '' ) . '">';
        $html .= '<div class="dges-card-header">';
        $html .= '<span class="dges-document-type">' . esc_html( $type ) . '</span>';

        if ( ! $current ) {
            $html .= '<span class="dges-badge-no-vigente">' . esc_html__( 'No vigente', 'dges-documentos' ) . '</span>';
        }

        $html .= '</div>';
        $html .= '<h3>' . esc_html( $title ) . '</h3>';

        if ( self::to_bool( $args['show_description'] ) && '' !== $description ) {
            $html .= '<p class="dges-single-description">' . esc_html( $description ) . '</p>';
        }

        $html .= '<div class="dges-single-footer">';
        $html .= '<span class="dges-single-meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>';

        if ( '' !== $href ) {
            $html .= '<a class="dges-btn-primary" href="' . esc_url( $href ) . '"' . ( $external ? ' target="_blank" rel="noopener noreferrer"' : '' ) . '>' . esc_html( $label ) . '</a>';
        } else {
            $html .= '<span class="dges-btn-primary is-disabled" aria-disabled="true">' . esc_html( $label ) . '</span>';
        }

        $html .= '</div>';
        $html .= '</article>';
        $html .= '</div>';

        return $html;
    }

    private static function to_bool( $value ) {
        if ( is_bool( $value ) ) {
            return $value;
        }

        $value = strtolower( trim( (string) $value ) );

        return ! in_array( $value, array( '0', 'false', 'no', 'off', '' ), true );
    }

    /**
     * Detalle completo: breadcrumb, metadata, archivos y relacionados.
     */
    public static function detail( $item ) {
        if ( ! is_array( $item ) ) {
            return '';
        }

        $title       = isset( $item['title'] ) ? (string) $item['title'] : '';
        $description = isset( $item['description'] ) ? (string) $item['description'] : '';
        $number      = isset( $item['number'] ) ? (string) $item['number'] : '';
        $year        = isset( $item['year'] ) ? (int) $item['year'] : 0;
        $category    = ! empty( $item['category'] ) ? (string) $item['category'] : '';
        $type        = ! empty( $item['document_type'] ) ? (string) $item['document_type'] : ( ! empty( $item['file_type'] ) ? (string) $item['file_type'] : __( 'Documento', 'dges-documentos' ) );
        $featured    = ! empty( $item['featured'] );
        $current     = ! isset( $item['current'] ) || $item['current'];
        $list_url    = DGES_Docs_Router::list_url();
        $back_url    = DGES_Docs_Router::back_url();

        $html  = '<article class="dges-documentos dges-documento-detalle">';
        $html .= '<nav class="dges-detail-breadcrumb" aria-label="' . esc_attr__( 'Migas de pan', 'dges-documentos' ) . '">';
        $html .= '<a href="' . esc_url( $list_url ) . '">' . esc_html__( 'Documentos', 'dges-documentos' ) . '</a>';
        $html .= '<span aria-hidden="true">/</span><span>' . esc_html( $title ) . '</span>';
        $html .= '</nav>';

        $html .= '<header class="dges-detail-header">';
        $html .= '<div class="dges-detail-badges">';
        $html .= '<span class="dges-document-type">' . esc_html( $type ) . '</span>';

        if ( $featured ) {
            $html .= '<span class="dges-badge-destacado">' . esc_html__( '★ Destacado', 'dges-documentos' ) . '</span>';
        }
        if ( ! $current ) {
            $html .= '<span class="dges-badge-no-vigente">' . esc_html__( 'No vigente', 'dges-documentos' ) . '</span>';
        }

        $html .= '</div>';
        $html .= '<h1 class="dges-detail-title">' . esc_html( $title ) . '</h1>';

        $meta = array();

        if ( '' !== $number || $year > 0 ) {
            $meta[] = trim( ( '' !== $number ? 'N° ' . $number : '' ) . ( '' !== $number && $year > 0 ? ' / ' : '' ) . ( $year > 0 ? (string) $year : '' ) );
        }
        if ( '' !== $category ) {
            $meta[] = $category;
        }

        if ( ! empty( $meta ) ) {
            $html .= '<div class="dges-detail-meta">';

            foreach ( $meta as $meta_item ) {
                $html .= '<span>' . esc_html( $meta_item ) . '</span>';
            }

            $html .= '</div>';
        }

        $html .= '</header>';

        if ( '' !== $description ) {
            $html .= '<p class="dges-detail-description">' . esc_html( $description ) . '</p>';
        }

        $html .= self::detail_files( $item );
        $html .= self::detail_related( $item );

        $html .= '<p class="dges-detail-back"><a class="dges-btn-secondary" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Volver a documentos', 'dges-documentos' ) . '</a></p>';
        $html .= '</article>';

        return $html;
    }

    public static function detail_not_found( $id = '' ) {
        $back_url = DGES_Docs_Router::back_url();
        $title    = '' === (string) $id
            ? __( 'No se especificó un documento', 'dges-documentos' )
            : __( 'Documento no encontrado', 'dges-documentos' );

        $html  = '<article class="dges-documentos dges-documento-detalle">';
        $html .= '<h1 class="dges-detail-title">' . esc_html( $title ) . '</h1>';
        $html .= '<p class="dges-detail-description">' . esc_html__( 'No encontramos ese documento o ya no está disponible.', 'dges-documentos' ) . '</p>';
        $html .= '<p class="dges-detail-back"><a class="dges-btn-secondary" href="' . esc_url( $back_url ) . '">' . esc_html__( 'Volver a documentos', 'dges-documentos' ) . '</a></p>';
        $html .= '</article>';

        return $html;
    }

    private static function detail_files( $item ) {
        $files = isset( $item['files'] ) && is_array( $item['files'] ) ? $item['files'] : array();

        $html  = '<section class="dges-detail-files">';
        $html .= '<h2>' . esc_html__( 'Archivos', 'dges-documentos' ) . '</h2>';

        if ( empty( $files ) ) {
            $html .= '<p class="dges-detail-empty">' . esc_html__( 'Sin archivo disponible.', 'dges-documentos' ) . '</p>';
            $html .= '</section>';

            return $html;
        }

        $html .= '<ul>';

        foreach ( $files as $file ) {
            if ( ! is_array( $file ) ) {
                continue;
            }

            $name = ! empty( $file['name'] ) ? (string) $file['name'] : ( ! empty( $file['filename'] ) ? (string) $file['filename'] : '' );

            if ( '' === $name ) {
                continue;
            }

            $meta = array();

            if ( ! empty( $file['type'] ) ) {
                $meta[] = (string) $file['type'];
            }
            if ( ! empty( $file['size'] ) ) {
                $meta[] = size_format( (int) $file['size'], 1 );
            }
            if ( empty( $file['url'] ) ) {
                $meta[] = __( 'sin enlace', 'dges-documentos' );
            }

            $html .= '<li class="dges-detail-file">';

            if ( ! empty( $file['url'] ) ) {
                $html .= '<a href="' . esc_url( $file['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . '</a>';
            } else {
                $html .= '<span>' . esc_html( $name ) . '</span>';
            }

            if ( ! empty( $meta ) ) {
                $html .= '<span class="dges-file-meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
            }

            if ( ! empty( $file['description'] ) ) {
                $html .= '<span class="dges-file-description">' . esc_html( $file['description'] ) . '</span>';
            }

            $html .= '</li>';
        }

        $html .= '</ul></section>';

        return $html;
    }

    private static function detail_related( $item ) {
        $related = isset( $item['related'] ) && is_array( $item['related'] ) ? $item['related'] : array();

        if ( empty( $related ) ) {
            return '';
        }

        $html  = '<section class="dges-detail-related">';
        $html .= '<h2>' . esc_html__( 'Documentos relacionados', 'dges-documentos' ) . '</h2>';
        $html .= '<div class="dges-documentos-results dges-related-grid">';

        foreach ( $related as $rel ) {
            $html .= self::card( $rel, array( 'context' => 'related', 'link' => 'detail' ) );
        }

        $html .= '</div></section>';

        return $html;
    }

    /**
     * URL de detalle resuelta por el router.
     */
    public static function detail_url( $id ) {
        $id = (string) $id;

        if ( '' === $id ) {
            return '';
        }

        $url = class_exists( 'DGES_Docs_Router' ) ? DGES_Docs_Router::detail_url( $id ) : '';

        return '' === $url ? '' : esc_url_raw( $url );
    }

    private static function file_url( $item, $file_id = '' ) {
        $files = isset( $item['files'] ) && is_array( $item['files'] ) ? $item['files'] : array();

        if ( '' !== (string) $file_id ) {
            foreach ( $files as $file ) {
                if ( (string) $file['id'] === (string) $file_id && ! empty( $file['url'] ) ) {
                    return $file['url'];
                }
            }
        }

        if ( ! empty( $item['url'] ) ) {
            return $item['url'];
        }

        if ( ! empty( $files[0]['url'] ) ) {
            return $files[0]['url'];
        }

        return '';
    }

    private static function status_text( $count, $total ) {
        if ( $total < 1 ) {
            return '';
        }

        if ( $count >= $total ) {
            return sprintf(
                _n( '%s documento', '%s documentos', $total, 'dges-documentos' ),
                number_format_i18n( $total )
            );
        }

        return sprintf(
            _n( 'Mostrando %1$s de %2$s documento', 'Mostrando %1$s de %2$s documentos', $total, 'dges-documentos' ),
            number_format_i18n( $count ),
            number_format_i18n( $total )
        );
    }

    private static function empty_state( $state, $categories, $has_error, $visible ) {
        if ( $has_error ) {
            return '';
        }

        $message = self::empty_message( $state, $categories );
        $clear   = self::clean_action_url();

        $html  = '<div class="dges-empty" data-empty role="status"' . ( $visible ? '' : ' hidden' ) . '>';
        $html .= '<p>' . esc_html( $message ) . '</p>';
        $html .= '<a class="dges-btn-secondary" href="' . esc_url( $clear ) . '" data-clear-filters>' . esc_html__( 'Limpiar filtros', 'dges-documentos' ) . '</a>';
        $html .= '</div>';

        return $html;
    }

    private static function error_state( $error, $state ) {
        if ( ! is_wp_error( $error ) ) {
            return '';
        }

        $message = $error->get_error_message();
        if ( '' === $message ) {
            $message = __( 'No pudimos cargar los documentos.', 'dges-documentos' );
        }

        $retry = self::page_url( $state, 1 );

        $html  = '<div class="dges-error" data-error role="alert">';
        $html .= '<p>' . esc_html( $message ) . '</p>';
        $html .= '<a class="dges-btn-secondary" href="' . esc_url( $retry ) . '" data-retry>' . esc_html__( 'Reintentar', 'dges-documentos' ) . '</a>';
        $html .= '</div>';

        return $html;
    }

    private static function pagination( $state, $pagination ) {
        if ( empty( $pagination['has_more'] ) ) {
            return '<div class="dges-documentos-pagination" data-pagination></div>';
        }

        $next = self::page_url( $state, (int) $pagination['page'] + 1 );

        $html  = '<div class="dges-documentos-pagination" data-pagination>';
        $html .= '<a class="dges-load-more" data-load-more href="' . esc_url( $next ) . '">' . esc_html__( 'Ver más', 'dges-documentos' ) . '</a>';
        $html .= '</div>';

        return $html;
    }

    private static function empty_message( $state, $categories ) {
        $search   = isset( $state['search'] ) ? trim( (string) $state['search'] ) : '';
        $category = isset( $state['category'] ) ? trim( (string) $state['category'] ) : '';

        if ( '' !== $search && '' !== $category ) {
            return sprintf(
                __( 'No encontramos documentos para “%1$s” en “%2$s”.', 'dges-documentos' ),
                $search,
                self::category_label( $categories, $category )
            );
        }

        if ( '' !== $search ) {
            return sprintf(
                __( 'No encontramos documentos para “%s”.', 'dges-documentos' ),
                $search
            );
        }

        if ( '' !== $category ) {
            return __( 'No hay documentos en esta categoría.', 'dges-documentos' );
        }

        return __( 'Todavía no hay documentos disponibles.', 'dges-documentos' );
    }

    private static function category_label( $categories, $category ) {
        foreach ( $categories as $cat ) {
            if ( (string) $cat['id'] === (string) $category ) {
                return (string) $cat['name'];
            }
        }

        return (string) $category;
    }

    private static function clean_action_url() {
        $url = get_permalink();

        if ( ! $url ) {
            $url = home_url( '/' );
        }

        return remove_query_arg( array( 'buscar', 'categoria', 'pagina' ), $url );
    }

    private static function page_url( $state, $page ) {
        $args = array();

        if ( ! empty( $state['search'] ) ) {
            $args['buscar'] = $state['search'];
        }
        if ( ! empty( $state['category'] ) ) {
            $args['categoria'] = $state['category'];
        }
        if ( (int) $page > 1 ) {
            $args['pagina'] = (int) $page;
        }

        return empty( $args ) ? self::clean_action_url() : add_query_arg( $args, self::clean_action_url() );
    }
}
