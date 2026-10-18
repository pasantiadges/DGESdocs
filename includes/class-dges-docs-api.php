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

        // Validamos la estructura del JSON devuelto por la API oficial {"documentos": [...]}
        if (!is_array($data) || !isset($data['documentos'])) {
            return new WP_Error('dges_api_error', 'No se pudo obtener el listado de documentos de la API oficial.');
        }

        $items = $data['documentos'];

        // Guardamos en caché por 1 hora
        set_transient(self::CACHE_KEY, $items, self::CACHE_TTL);
        return $items;
    }

    public static function filter_documents($items, $args) {
        $txtbusqueda = self::normalize_text(isset($args['txtbusqueda']) ? $args['txtbusqueda'] : '');
        $categoria_filtro = self::normalize_text(isset($args['categoria']) ? $args['categoria'] : '');
        $anio_filtro = absint(isset($args['anio']) ? $args['anio'] : 0);
        $type_filtro = self::normalize_text(isset($args['type']) ? $args['type'] : '');
        return array_values(array_filter($items, function ($item) use ($txtbusqueda, $categoria_filtro, $anio_filtro) {
            
            // 1. Filtrar por Año
            if ($anio_filtro && (int) (isset($item['anio']) ? $item['anio'] : 0) !== $anio_filtro) {
                return false;
            }

            // 2. Filtrar por Categoría (recorriendo el array de categorías del JSON)
            if ($categoria_filtro) {
                $match_cat = false;
                if (!empty($item['categorias']) && is_array($item['categorias'])) {
                    foreach ($item['categorias'] as $cat) {
                        if (strpos(self::normalize_text(isset($cat['nombre']) ? $cat['nombre'] : ''), $categoria_filtro) !== false) {
                            $match_cat = true;
                            break;
                        }
                    }
                }
                if (!$match_cat) {
                    return false;
                }
            }

            // 3. Filtrar por Texto (en nombre y observación)
            if ($txtbusqueda) {
                $nombre = isset($item['nombre']) ? $item['nombre'] : '';
                $observacion = isset($item['observacion']) ? $item['observacion'] : '';
                $haystack = self::normalize_text($nombre . ' ' . $observacion);
                
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
