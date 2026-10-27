<?php
/**
 * Cliente de la API de documentos de la DGES: HTTP, mapeo y normalización.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Source {

    const OPTION_URL        = 'dges_documentos_source_url';
    const OPTION_FILES_BASE = 'dges_documentos_files_base';
    const TIMEOUT           = 15;

    /** @var int|null Total informado por la API, si viene. */
    public $last_total = null;

    public static function default_url() {
        return 'https://bd.dges-cba.edu.ar/bd_dges/modulos/documentos/operaciones/list_documentos.php';
    }

    public static function get_source_url() {
        return esc_url_raw( get_option( self::OPTION_URL, self::default_url() ) );
    }

    public static function get_files_base() {
        return esc_url_raw( get_option( self::OPTION_FILES_BASE, '' ) );
    }

    /**
     * Listado. En modo índice se pide todo; en modo API se pasan los filtros.
     *
     * @param array $args search, category, limit, offset.
     * @return array|WP_Error
     */
    public function get_list( $args = array() ) {
        $url  = $this->build_list_url( $args );
        $data = $this->request_json( $url, 'list' );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $raw_items = null;

        if ( isset( $data['documentos'] ) && is_array( $data['documentos'] ) ) {
            $raw_items = $data['documentos'];
        } elseif ( isset( $data[0] ) && is_array( $data[0] ) ) {
            $raw_items = $data;
        } elseif ( isset( $data['id'] ) ) {
            $raw_items = array( $data );
        }

        if ( null === $raw_items ) {
            return new WP_Error(
                'dges_docs_api_invalid_response',
                __( 'La API devolvió un listado con un formato inesperado.', 'dges-documentos' ),
                array( 'status' => 502 )
            );
        }

        $this->last_total = isset( $data['total'] ) ? (int) $data['total'] : null;

        $items = array();

        foreach ( $raw_items as $raw ) {
            $item = self::normalize_document( $raw, false );
            if ( '' === $item['id'] && '' === $item['title'] ) {
                continue;
            }
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Detalle por id. Devuelve archivos y relacionados.
     *
     * @param string $id
     * @return array|WP_Error
     */
    public function get_detail( $id ) {
        $id = (string) $id;

        if ( ! preg_match( '/^\d+$/', $id ) ) {
            return new WP_Error(
                'dges_docs_document_not_found',
                __( 'Documento no encontrado.', 'dges-documentos' ),
                array( 'status' => 404 )
            );
        }

        $url  = add_query_arg( 'iddocumento', $id, self::get_source_url() );
        $data = $this->request_json( $url, 'detail' );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        if ( isset( $data['documentos'] ) && is_array( $data['documentos'] ) ) {
            foreach ( $data['documentos'] as $raw ) {
                if ( isset( $raw['id'] ) && (string) $raw['id'] === $id ) {
                    return self::normalize_document( $raw, true );
                }
            }

            return new WP_Error(
                'dges_docs_document_not_found',
                __( 'Documento no encontrado.', 'dges-documentos' ),
                array( 'status' => 404 )
            );
        }

        if ( isset( $data['documento'] ) && is_array( $data['documento'] ) ) {
            $data = $data['documento'];
        }

        if ( ! isset( $data['id'] ) ) {
            return new WP_Error(
                'dges_docs_document_not_found',
                __( 'Documento no encontrado.', 'dges-documentos' ),
                array( 'status' => 404 )
            );
        }

        return self::normalize_document( $data, true );
    }

    /**
     * Categorías. El endpoint puede no existir todavía; el repository hace fallback.
     *
     * @return array|WP_Error
     */
    public function get_categories() {
        $base = self::get_source_url();
        $url  = str_replace( 'list_documentos.php', 'list_categorias.php', $base );
        $url  = apply_filters( 'dges_docs_categories_url', $url, $base );

        $data = $this->request_json( $url, 'categories' );

        if ( is_wp_error( $data ) ) {
            return $data;
        }

        $raw = array();

        if ( isset( $data['categorias'] ) && is_array( $data['categorias'] ) ) {
            $raw = $data['categorias'];
        } elseif ( isset( $data[0] ) && is_array( $data[0] ) ) {
            $raw = $data;
        }

        $categories = array();

        foreach ( $raw as $cat ) {
            if ( is_string( $cat ) ) {
                $cat = array( 'nombre' => $cat );
            }
            if ( ! is_array( $cat ) ) {
                continue;
            }

            $name = isset( $cat['nombre'] ) ? $cat['nombre'] : ( isset( $cat['name'] ) ? $cat['name'] : '' );
            $name = DGES_Docs_Query::clean_display( $name );

            if ( '' === $name ) {
                continue;
            }

            $id = isset( $cat['idcategoria'] ) ? $cat['idcategoria'] : ( isset( $cat['id'] ) ? $cat['id'] : '' );

            $categories[] = array(
                'id'    => (string) $id,
                'name'  => $name,
                'count' => isset( $cat['cantidad'] ) ? (int) $cat['cantidad'] : 0,
            );
        }

        return $categories;
    }

    private function build_list_url( $args ) {
        $params = array();

        if ( ! empty( $args['limit'] ) ) {
            $params['limit'] = (int) $args['limit'];
        }

        if ( isset( $args['offset'] ) && (int) $args['offset'] > 0 ) {
            $params['offset'] = (int) $args['offset'];
        }

        $search = isset( $args['search'] ) ? $args['search'] : ( isset( $args['txtbusqueda'] ) ? $args['txtbusqueda'] : '' );
        if ( '' !== trim( (string) $search ) ) {
            $params['txtbusqueda'] = trim( (string) $search );
        }

        $category = isset( $args['category'] ) ? $args['category'] : ( isset( $args['categoria'] ) ? $args['categoria'] : '' );
        if ( '' !== trim( (string) $category ) ) {
            $params['categoria'] = trim( (string) $category );
        }

        if ( ! empty( $args['featured'] ) ) {
            $params['destacado'] = 'Si';
        }

        $base = self::get_source_url();

        return empty( $params ) ? $base : add_query_arg( $params, $base );
    }

    /**
     * Pide una URL y devuelve JSON decodificado.
     *
     * @param string $url
     * @param string $context list|detail|categories
     * @return array|WP_Error
     */
    private function request_json( $url, $context ) {
        $response = wp_safe_remote_get(
            $url,
            array(
                'timeout'     => self::TIMEOUT,
                'redirection' => 3,
                'headers'     => array(
                    'Accept'     => 'application/json',
                    'User-Agent' => 'DGESdocs/' . DGES_DOCS_VERSION . '; ' . home_url( '/' ),
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'dges_docs_api_unavailable',
                __( 'No se pudo conectar con la API de documentos.', 'dges-documentos' ),
                array( 'status' => 503, 'detail' => $response->get_error_message() )
            );
        }

        $code = (int) wp_remote_retrieve_response_code( $response );

        if ( $code < 200 || $code >= 300 ) {
            if ( 404 === $code && 'detail' === $context ) {
                return new WP_Error(
                    'dges_docs_document_not_found',
                    __( 'Documento no encontrado.', 'dges-documentos' ),
                    array( 'status' => 404 )
                );
            }

            return new WP_Error(
                'dges_docs_api_unavailable',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __( 'La API respondió con el código HTTP %d.', 'dges-documentos' ),
                    $code
                ),
                array( 'status' => 503 )
            );
        }

        $body = trim( (string) wp_remote_retrieve_body( $response ) );
        $data = json_decode( $body, true );

        if ( null === $data && JSON_ERROR_NONE !== json_last_error() ) {
            if ( 'detail' === $context ) {
                // La API devuelve texto plano cuando el id no existe.
                return new WP_Error(
                    'dges_docs_document_not_found',
                    __( 'Documento no encontrado.', 'dges-documentos' ),
                    array( 'status' => 404 )
                );
            }

            return new WP_Error(
                'dges_docs_api_invalid_response',
                __( 'La API no devolvió un JSON válido.', 'dges-documentos' ),
                array( 'status' => 502, 'detail' => substr( $body, 0, 200 ) )
            );
        }

        if ( ! is_array( $data ) ) {
            return new WP_Error(
                'dges_docs_api_invalid_response',
                __( 'La API devolvió una respuesta inesperada.', 'dges-documentos' ),
                array( 'status' => 502 )
            );
        }

        return $data;
    }

    /**
     * Mapea un documento crudo al modelo del plugin.
     */
    public static function normalize_document( $raw, $detail = false ) {
        if ( ! is_array( $raw ) ) {
            $raw = array();
        }

        $title       = DGES_Docs_Query::clean_display( isset( $raw['nombre'] ) ? $raw['nombre'] : ( isset( $raw['title'] ) ? $raw['title'] : '' ) );
        $description = DGES_Docs_Query::clean_display( isset( $raw['observacion'] ) ? $raw['observacion'] : ( isset( $raw['description'] ) ? $raw['description'] : '' ) );
        $number      = DGES_Docs_Query::clean_display( isset( $raw['numero'] ) ? $raw['numero'] : ( isset( $raw['number'] ) ? $raw['number'] : '' ) );
        $year        = 0;

        if ( isset( $raw['anio'] ) && is_scalar( $raw['anio'] ) ) {
            $year = (int) $raw['anio'];
        } elseif ( isset( $raw['year'] ) && is_scalar( $raw['year'] ) ) {
            $year = (int) $raw['year'];
        }

        if ( '' === $number || $year <= 0 ) {
            $inferred = self::infer_number_year( $title . ' ' . $number );
            if ( '' === $number && '' !== $inferred['number'] ) {
                $number = $inferred['number'];
            }
            if ( $year <= 0 && $inferred['year'] > 0 ) {
                $year = $inferred['year'];
            }
        }

        $categories = self::normalize_categories( isset( $raw['categorias'] ) ? $raw['categorias'] : ( isset( $raw['categories'] ) ? $raw['categories'] : array() ) );

        $document_type = '';
        if ( ! empty( $raw['tipo_documento'] ) ) {
            $document_type = DGES_Docs_Query::clean_display( $raw['tipo_documento'] );
        }
        if ( '' === $document_type ) {
            $document_type = self::infer_document_type( $title );
        }

        $featured = self::is_yes( isset( $raw['destacado'] ) ? $raw['destacado'] : ( isset( $raw['destacada'] ) ? $raw['destacada'] : false ) );

        $current = true;
        if ( array_key_exists( 'vigente', $raw ) && '' !== trim( (string) $raw['vigente'] ) ) {
            $current = self::is_yes( $raw['vigente'] );
        }

        $files   = $detail ? self::normalize_files( isset( $raw['archivos'] ) ? $raw['archivos'] : array() ) : array();
        $related = $detail ? self::normalize_related( isset( $raw['documentos_relacionados'] ) ? $raw['documentos_relacionados'] : array(), isset( $raw['id'] ) ? (string) $raw['id'] : '' ) : array();

        $primary_file = ! empty( $files[0] ) ? $files[0] : array();

        $item = array(
            'id'            => isset( $raw['id'] ) ? (string) $raw['id'] : '',
            'title'         => $title,
            'description'   => $description,
            'number'        => $number,
            'year'          => $year,
            'month'         => isset( $raw['mes'] ) ? absint( $raw['mes'] ) : 0,
            'categories'    => $categories,
            'category'      => ! empty( $categories[0]['name'] ) ? $categories[0]['name'] : '',
            'document_type' => $document_type,
            'featured'      => $featured,
            'current'       => $current,
            'files'         => $files,
            'related'       => $related,
            'file_type'     => ! empty( $primary_file['type'] ) ? $primary_file['type'] : '',
            'file_size'     => ! empty( $primary_file['size'] ) ? (int) $primary_file['size'] : 0,
            'url'           => ! empty( $primary_file['url'] ) ? $primary_file['url'] : '',
        );

        $item['search_text'] = DGES_Docs_Query::build_search_text( $item );

        return $item;
    }

    public static function normalize_categories( $raw ) {
        if ( ! is_array( $raw ) ) {
            return array();
        }

        $categories = array();
        $seen       = array();

        foreach ( $raw as $cat ) {
            if ( is_string( $cat ) ) {
                $cat = array( 'nombre' => $cat );
            }
            if ( ! is_array( $cat ) ) {
                continue;
            }

            $name = isset( $cat['nombre'] ) ? $cat['nombre'] : ( isset( $cat['name'] ) ? $cat['name'] : '' );
            $name = DGES_Docs_Query::clean_display( $name );

            if ( '' === $name ) {
                continue;
            }

            $id  = isset( $cat['idcategoria'] ) ? (string) $cat['idcategoria'] : ( isset( $cat['id'] ) ? (string) $cat['id'] : '' );
            $key = '' !== $id ? 'id:' . $id : 'name:' . strtolower( $name );

            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;

            $categories[] = array(
                'id'   => $id,
                'name' => $name,
            );
        }

        return $categories;
    }

    public static function normalize_files( $raw ) {
        if ( ! is_array( $raw ) ) {
            return array();
        }

        $files = array();
        $seen  = array();

        foreach ( $raw as $file ) {
            if ( ! is_array( $file ) ) {
                continue;
            }

            $id       = isset( $file['idarchivo'] ) ? (string) $file['idarchivo'] : ( isset( $file['id'] ) ? (string) $file['id'] : '' );
            $filename = isset( $file['nombre_archivo'] ) ? (string) $file['nombre_archivo'] : ( isset( $file['filename'] ) ? (string) $file['filename'] : '' );
            $name     = isset( $file['nombre'] ) ? DGES_Docs_Query::clean_display( $file['nombre'] ) : ( isset( $file['name'] ) ? DGES_Docs_Query::clean_display( $file['name'] ) : '' );

            if ( '' === $name ) {
                $name = $filename;
            }
            if ( '' === $name && '' === $id ) {
                continue;
            }

            $key = '' !== $id ? 'id:' . $id : 'name:' . strtolower( $name . '|' . $filename );
            if ( isset( $seen[ $key ] ) ) {
                continue;
            }
            $seen[ $key ] = true;

            $type = isset( $file['tipo'] ) ? strtoupper( DGES_Docs_Query::clean_display( $file['tipo'] ) ) : '';
            if ( '' === $type && '' !== $filename ) {
                $type = strtoupper( pathinfo( $filename, PATHINFO_EXTENSION ) );
            }

            $files[] = array(
                'id'           => $id,
                'name'         => $name,
                'description'  => isset( $file['descripcion'] ) ? DGES_Docs_Query::clean_display( $file['descripcion'] ) : ( isset( $file['description'] ) ? DGES_Docs_Query::clean_display( $file['description'] ) : '' ),
                'type'         => $type,
                'folder'       => isset( $file['carpeta'] ) ? (string) $file['carpeta'] : '',
                'filename'     => $filename,
                'size'         => isset( $file['tamanio'] ) ? (int) $file['tamanio'] : ( isset( $file['size'] ) ? (int) $file['size'] : 0 ),
                'url'          => self::resolve_file_url( $file ),
            );
        }

        return $files;
    }

    public static function normalize_related( $raw, $exclude_id = '' ) {
        if ( ! is_array( $raw ) ) {
            return array();
        }

        $related = array();
        $seen    = array();

        foreach ( $raw as $rel ) {
            if ( ! is_array( $rel ) ) {
                continue;
            }

            $id = isset( $rel['id'] ) ? (string) $rel['id'] : ( isset( $rel['iddocumento'] ) ? (string) $rel['iddocumento'] : '' );

            if ( '' === $id || $id === (string) $exclude_id || isset( $seen[ $id ] ) ) {
                continue;
            }
            $seen[ $id ] = true;

            $title      = isset( $rel['nombre'] ) ? DGES_Docs_Query::clean_display( $rel['nombre'] ) : ( isset( $rel['title'] ) ? DGES_Docs_Query::clean_display( $rel['title'] ) : '' );
            $categories = self::normalize_categories( isset( $rel['categorias'] ) ? $rel['categorias'] : array() );
            $number     = isset( $rel['numero'] ) ? DGES_Docs_Query::clean_display( $rel['numero'] ) : ( isset( $rel['number'] ) ? DGES_Docs_Query::clean_display( $rel['number'] ) : '' );
            $year       = isset( $rel['anio'] ) ? (int) $rel['anio'] : ( isset( $rel['year'] ) ? (int) $rel['year'] : 0 );

            if ( '' === $number || $year <= 0 ) {
                $inferred = self::infer_number_year( $title . ' ' . $number );

                if ( '' === $number && '' !== $inferred['number'] ) {
                    $number = $inferred['number'];
                }
                if ( $year <= 0 && $inferred['year'] > 0 ) {
                    $year = $inferred['year'];
                }
            }

            $related[] = array(
                'id'            => $id,
                'title'         => $title,
                'description'   => isset( $rel['observacion'] ) ? DGES_Docs_Query::clean_display( $rel['observacion'] ) : ( isset( $rel['description'] ) ? DGES_Docs_Query::clean_display( $rel['description'] ) : '' ),
                'number'        => $number,
                'year'          => $year,
                'relation'      => isset( $rel['tipo'] ) ? DGES_Docs_Query::clean_display( $rel['tipo'] ) : '',
                'featured'      => self::is_yes( isset( $rel['destacado'] ) ? $rel['destacado'] : ( isset( $rel['destacada'] ) ? $rel['destacada'] : false ) ),
                'current'       => array_key_exists( 'vigente', $rel ) && is_scalar( $rel['vigente'] ) && '' !== trim( (string) $rel['vigente'] ) ? self::is_yes( $rel['vigente'] ) : true,
                'categories'    => $categories,
                'category'      => ! empty( $categories[0]['name'] ) ? $categories[0]['name'] : '',
                'document_type' => self::infer_document_type( $title ),
                'file_type'     => '',
                'file_size'     => 0,
                'files'         => array(),
                'url'           => '',
            );
        }

        return $related;
    }

    public static function resolve_file_url( $file ) {
        $url = '';

        if ( ! empty( $file['url'] ) ) {
            $url = (string) $file['url'];
        } elseif ( ! empty( $file['archivo_url'] ) ) {
            $url = (string) $file['archivo_url'];
        } else {
            $base = self::get_files_base();

            if ( '' !== $base && ! empty( $file['carpeta'] ) && ! empty( $file['nombre_archivo'] ) ) {
                $base = trailingslashit( $base );
                $path = ltrim( (string) $file['carpeta'], '/' ) . '/' . ltrim( (string) $file['nombre_archivo'], '/' );

                if ( 0 === strpos( $path, 'archivos/' ) && '/archivos/' === substr( $base, -10 ) ) {
                    $path = substr( $path, 9 );
                }

                $url = $base . $path;
            }
        }

        $url = apply_filters( 'dges_docs_file_url', $url, $file );

        if ( '' === $url ) {
            return '';
        }

        $url = esc_url_raw( $url );
        $scheme = wp_parse_url( $url, PHP_URL_SCHEME );

        if ( ! in_array( strtolower( (string) $scheme ), array( 'http', 'https' ), true ) ) {
            return '';
        }

        return $url;
    }

    /**
     * Tipo de documento a partir del título.
     */
    public static function infer_document_type( $title ) {
        $title = DGES_Docs_Query::normalize( $title );

        if ( '' === $title ) {
            return 'Documento';
        }

        $rules = array(
            'Resolución Conjunta'   => array( 'resolucion conjunta' ),
            'Resolución Ministerial' => array( 'resolucion ministerial' ),
            'Resolución'            => array( 'resolucion' ),
            'Memorándum Conjunto'   => array( 'memorandum conjunto', 'memorando conjunto' ),
            'Memorándum'            => array( 'memorandum', 'memorando' ),
            'Disposición'           => array( 'disposicion' ),
            'Decreto'               => array( 'decreto' ),
            'Plan de Acción'        => array( 'plan de accion' ),
            'Instructivo'           => array( 'instructivo' ),
            'Estadística'           => array( 'estadistica' ),
            'Memoria Anual'         => array( 'memoria anual' ),
            'Cuaderno'              => array( 'cuaderno' ),
        );

        foreach ( $rules as $label => $needles ) {
            foreach ( $needles as $needle ) {
                if ( false !== strpos( $title, $needle ) ) {
                    return $label;
                }
            }
        }

        if ( preg_match( '/\bley\b/', $title ) ) {
            return 'Ley';
        }

        return 'Documento';
    }

    /**
     * Fallback de número y año cuando la API no los manda.
     *
     * @return array number, year
     */
    public static function infer_number_year( $title ) {
        $normalized = strtolower( remove_accents( DGES_Docs_Query::clean_display( $title ) ) );
        $normalized = preg_replace( '/[-\/\.]+/', ' ', $normalized );
        $normalized = preg_replace( '/\s+/', ' ', $normalized );
        $normalized = trim( $normalized );
        $number     = '';
        $year       = 0;

        if ( preg_match( '/\b(\d{1,5})\s+(\d{2,4})\b/', $normalized, $match ) ) {
            $number = $match[1];
            $year   = (int) $match[2];

            if ( $year < 100 ) {
                $current = (int) gmdate( 'y' ) + 1;
                $year   += ( $year <= $current ) ? 2000 : 1900;
            }

            if ( $year < 1900 || $year > (int) gmdate( 'Y' ) + 1 ) {
                $year = 0;
            }
        }

        return array(
            'number' => $number,
            'year'   => $year,
        );
    }

    public static function is_yes( $value ) {
        if ( true === $value || 1 === $value ) {
            return true;
        }

        $value = DGES_Docs_Query::normalize( $value );

        return in_array( $value, array( 'si', '1', 'true', 'yes', 'y' ), true );
    }
}
