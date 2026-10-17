<?php
**
 * Clase encargada de la lógica de negocio, rastreo de enlaces (crawling) 
 * y filtrado de los documentos de la DGES.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_API {
// Configuración de constantes para almacenamiento y límites del crawler.
    const OPTION_URL = 'dges_documentos_source_url';
    const CACHE_KEY = 'dges_documentos_items_v6';
    const CACHE_TTL = HOUR_IN_SECONDS; // Duración de la caché (1 hora)
    const MAX_CRAWL_PAGES = 80;
    const MAX_CRAWL_DEPTH = 3;

    // URL por defecto de donde se extraen los documentos.
    public static function default_url() {
        return 'https://dges-cba.infd.edu.ar/sitio/documentos/';
    }

    // Obtiene la URL configurada actualmente en la base de datos de WordPress.
    public static function get_source_url() {
        return esc_url_raw(get_option(self::OPTION_URL, self::default_url()));
    }

    // Limpia la caché temporal almacenada.
    public static function clear_cache() {
        delete_transient(self::CACHE_KEY);
    }

    // Método principal para obtener los documentos (usando caché si está disponible).
    public static function get_documents($force_refresh = false) {
        if (!$force_refresh) {
            $cached = get_transient(self::CACHE_KEY);
            if (is_array($cached)) {
                return $cached; // Retorna datos cacheados para optimizar velocidad
            }
        }

        $url = self::get_source_url();
        $fetched = self::fetch_url($url);
        if (is_wp_error($fetched)) {
            return $fetched;
        }

        // Ejecuta el crawler para extraer los elementos de la web.
        $items = self::crawl($fetched['url'], $fetched['body'], $fetched['content_type']);

        if (!is_array($items)) {
            return new WP_Error('dges_source_parse', 'No se pudo interpretar la respuesta de la fuente.', array('status' => 502));
        }

        // Guarda en caché los resultados obtenidos.
        set_transient(self::CACHE_KEY, $items, self::CACHE_TTL);
        return $items;
    }

    // Filtra la lista de documentos según los parámetros de búsqueda del usuario.
    public static function filter_documents($items, $args) {
        $search = self::normalize_text(isset($args['search']) ? $args['search'] : '');
        $search_tokens = $search ? preg_split('/\s+/', $search) : array();
        $type = sanitize_key(isset($args['type']) ? $args['type'] : '');
        $category = self::normalize_text(isset($args['category']) ? $args['category'] : '');
        $year = absint(isset($args['year']) ? $args['year'] : 0);
        $document_type = sanitize_key(isset($args['document_type']) ? $args['document_type'] : '');
        $number = self::normalize_text(isset($args['number']) ? $args['number'] : '');

        return array_values(array_filter($items, function ($item) use ($search, $search_tokens, $type, $category, $year, $document_type, $number) {
            if ($type && $item['type'] !== $type) return false;
            if ($category && self::normalize_text($item['category']) !== $category) return false;
            if ($year && (int) $item['year'] !== $year) return false;
            if ($document_type && $item['document_type'] !== $document_type) return false;
            if ($number && self::normalize_text($item['number']) !== $number) return false;
            
            if ($search) {
                $haystack = self::normalize_text(isset($item['search_text']) ? $item['search_text'] : implode(' ', array(
                    $item['title'], $item['category'], $item['type'], $item['url'],
                    isset($item['description']) ? $item['description'] : '',
                )));
                foreach ($search_tokens as $token) {
                    if ($token !== '' && strpos($haystack, $token) === false) {
                        return false;
                    }
                }
            }
            return true;
        }));
    }

    // Motor de rastreo web (Crawler recursivo de enlaces).
    private static function crawl($source_url, $source_body, $source_content_type) {
        $root_host = strtolower((string) wp_parse_url($source_url, PHP_URL_HOST));
        $queue = array(array($source_url, $source_body, $source_content_type, 0, ''));
        $visited = array();
        $items = array();
        $item_indexes = array();

        while ($queue && count($visited) < self::MAX_CRAWL_PAGES) {
            $entry = array_shift($queue);
            $page_url = $entry[0];
            $body = $entry[1];
            $content_type = $entry[2];
            $depth = $entry[3];
            $parent_category = $entry[4];
            $canonical = self::canonical_url($page_url);

            if (!$canonical || isset($visited[$canonical])) continue;
            $visited[$canonical] = true;

            $parsed = self::parse_html($body, $page_url, $parent_category);
            if ($parsed === false) continue;

            foreach ($parsed['items'] as $item) {
                $item['category'] = $item['category'] ?: $parent_category;
                $item = self::normalize_metadata($item);
                $item['search_text'] = self::search_text($item);
                $items[] = $item;
            }

            if ($depth >= self::MAX_CRAWL_DEPTH) continue;

            foreach ($parsed['child_pages'] as $child) {
                $child_url = self::canonical_url($child['url']);
                if (!$child_url || isset($visited[$child_url])) continue;
                $child_fetched = self::fetch_url($child_url);
                if (!is_wp_error($child_fetched)) {
                    $queue[] = array($child_fetched['url'], $child_fetched['body'], $child_fetched['content_type'], $depth + 1, $child['title']);
                }
            }
        }
        return $items;
    }

    // Funciones de utilidad auxiliar para parseo de texto, metadatos y normalización...
    private static function parse_html($html, $source_url, $parent_category = '') {
        if (!class_exists('DOMDocument')) return false;
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//main//a[@href] | //article//a[@href] | //table//a[@href]');
        
        $items = array();
        $child_pages = array();
        foreach ($links as $link) {
            $href = trim($link->getAttribute('href'));
            $title = trim($link->textContent);
            if (!$href || !$title) continue;
            $absolute_url = self::absolute_url($href, $source_url);
            if (!$absolute_url) continue;
            
            $extension = strtolower(pathinfo(parse_url($absolute_url, PHP_URL_PATH), PATHINFO_EXTENSION));
            if ($extension === 'pdf' || strpos($absolute_url, 'document') !== false) {
                $items[] = array(
                    'title' => wp_strip_all_tags($title),
                    'url' => esc_url_raw($absolute_url),
                    'type' => $extension ?: 'enlace',
                    'category' => $parent_category,
                    'year' => self::infer_year($title)
                );
            }
        }
        return array('items' => $items, 'child_pages' => $child_pages);
    }

    private static function fetch_url($url) {
        $response = wp_safe_remote_get($url, array('timeout' => 15));
        if (is_wp_error($response)) return $response;
        return array(
            'body' => wp_remote_retrieve_body($response),
            'content_type' => wp_remote_retrieve_header($response, 'content-type'),
            'url' => $url
        );
    }

    private static function canonical_url($url) { return strtok($url, '#'); }
    private static function absolute_url($href, $base) { return (strpos($href, 'http') === 0) ? $href : rtrim($base, '/') . '/' . ltrim($href, '/'); }
    private static function infer_year($text) { preg_match('/\b(19|20)\d{2}\b/', $text, $m); return isset($m[0]) ? (int)$m[0] : 0; }
    private static function normalize_text($text) { return strtolower(trim(preg_replace('/\s+/', ' ', $text))); }
    private static function normalize_metadata($item) { return $item; }
    private static function search_text($item) { return $item['title'] . ' ' . $item['category']; }
}