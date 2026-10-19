<?php
/**
 * Clase encargada de la lógica de negocio, rastreo de enlaces (crawling) 
 * y filtrado de los documentos de la DGES.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_API {
    const OPTION_URL = 'dges_documentos_source_url';
    const CACHE_KEY = 'dges_documentos_api_json_v2';
    const CACHE_TTL = HOUR_IN_SECONDS;

    public static function default_url() {
        // URL por defecto apuntando a la nueva API oficial de la DGES
        return 'https://bd.dges-cba.edu.ar/bd_dges/modulos/documentos/operaciones/list_documentos.php';
    }

    public static function get_source_url() {
        return esc_url_raw(get_option(self::OPTION_URL, self::default_url()));
    }

    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
    }

      public static function get_documents($force_refresh = false) {
        if (!$force_refresh) {
            $cached = get_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $url = self::get_source_url();
        $response = wp_safe_remote_get($url, array('timeout' => 20));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['documentos'])) {
            return new WP_Error('dges_api_error', 'No se pudo obtener el listado de documentos.');
        }

        $items = $data['documentos'];
        $mapped_items = array();

        foreach ($items as $item) {
            // Manejo de la categoría
            $cat_nombre = '';
            if (!empty($item['categorias']) && is_array($item['categorias'])) {
                $cat_nombre = isset($item['categorias'][0]['nombre']) ? $item['categorias'][0]['nombre'] : '';
            }

            // Manejo del archivo principal (URL y Tipo)
            $url_archivo = '#';
            $tipo_archivo = 'PDF'; // por defecto
            if (!empty($item['archivos']) && is_array($item['archivos'])) {
                $archivo = $item['archivos'][0];
                $tipo_archivo = isset($archivo['tipo']) ? strtoupper($archivo['tipo']) : 'PDF';
                // Armamos la URL ficticia para probar (en producción usarías la carpeta real)
                if (isset($archivo['nombre_archivo'])) {
                    $url_archivo = 'https://bd.dges-cba.edu.ar/bd_dges/archivos/' . (isset($archivo['carpeta']) ? $archivo['carpeta'] : '') . '/' . $archivo['nombre_archivo'];
                }
            }

            $mapped_items[] = array(
                'id'          => isset($item['id']) ? $item['id'] : '',
                'title'       => isset($item['nombre']) ? $item['nombre'] : '',
                'description' => isset($item['observacion']) ? $item['observacion'] : '',
                'year'        => isset($item['anio']) ? $item['anio'] : '',
                'category'    => $cat_nombre,
                'type'        => $tipo_archivo, // Ahora dice "PDF" o "XLSX" real
                'url'         => $url_archivo,
                'target'      => '_blank',
                'destacado'   => (isset($item['destacado']) && $item['destacado'] === 'Si'),
                'related'     => isset($item['documentos_relacionados']) ? $item['documentos_relacionados'] : array()
            );
        }

        set_transient(self::CACHE_KEY, $mapped_items, self::CACHE_TTL);
        return $mapped_items;
    }

    public static function filter_documents($items, $args) {
        $txtbusqueda = self::normalize_text(isset($args['txtbusqueda']) ? $args['txtbusqueda'] : '');
        $categoria_filtro = self::normalize_text(isset($args['categoria']) ? $args['categoria'] : '');
        $anio_filtro = absint(isset($args['anio']) ? $args['anio'] : 0);
        
        return array_values(array_filter($items, function ($item) use ($txtbusqueda, $categoria_filtro, $anio_filtro) {
            
            // 1. Filtrar por Año (comparando ambos como string)
            if ($anio_filtro && (string) $item['year'] !== (string) $anio_filtro) {
                return false;
            }

            // 2. Filtrar por Categoría
            if ($categoria_filtro) {
                if (strpos(self::normalize_text($item['category']), $categoria_filtro) === false) {
                    return false;
                }
            }

            // 3. Filtrar por Texto
            if ($txtbusqueda) {
                $haystack = self::normalize_text($item['title'] . ' ' . $item['description']);
                if (strpos($haystack, $txtbusqueda) === false) {
                    return false;
                }
            }

            return true;
        }));
    }

    private static function normalize_text($value) {
        $value = remove_accents(wp_strip_all_tags((string) $value));
        return strtolower(trim(preg_replace('/\s+/', ' ', $value)));
    }
}
