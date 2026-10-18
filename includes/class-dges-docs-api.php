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
    const CACHE_KEY = 'dges_documentos_items_v6';
    const CACHE_TTL = HOUR_IN_SECONDS;
    const MAX_CRAWL_PAGES = 80;
    const MAX_CRAWL_DEPTH = 3;

    public static function default_url() {
        return 'https://dges-cba.infd.edu.ar/sitio/documentos/';
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
        $fetched = self::fetch_url($url);
        if (is_wp_error($fetched)) {
            return $fetched;
        }

        $items = self::crawl($fetched['url'], $fetched['body'], $fetched['content_type']);

        if (!is_array($items)) {
            return new WP_Error('dges_source_parse', 'No se pudo interpretar la respuesta de la fuente.', array('status' => 502));
        }

        set_transient(self::CACHE_KEY, $items, self::CACHE_TTL);
        return $items;
    }

public static function filter_documents($items, $args) {
        $txtbusqueda = self::normalize_text(isset($args['txtbusqueda']) ? $args['txtbusqueda'] : '');
        $search_tokens = $txtbusqueda ? preg_split('/\s+/', $txtbusqueda) : array();
        $category = self::normalize_text(isset($args['categoria']) ? $args['categoria'] : '');
        $year = absint(isset($args['anio']) ? $args['anio'] : 0);
        
        $type = sanitize_key(isset($args['type']) ? $args['type'] : '');
        $document_type = sanitize_key(isset($args['document_type']) ? $args['document_type'] : '');
        $number = self::normalize_text(isset($args['number']) ? $args['number'] : '');

        return array_values(array_filter($items, function ($item) use ($txtbusqueda, $search_tokens, $category, $year, $type, $document_type, $number) {
            if ($type && $item['type'] !== $type) {
                return false;
            }
            if ($category) {
                // Comprobamos si coincide la categoría del ítem
                $item_category = self::normalize_text(isset($item['category']) ? $item['category'] : '');
                if (strpos($item_category, $category) === false) {
                    return false;
                }
            }
            if ($year && (int) $item['year'] !== $year) {
                return false;
            }
            if ($document_type && $item['document_type'] !== $document_type) {
                return false;
            }
            if ($number && self::normalize_text($item['number']) !== $number) {
                return false;
            }
            if ($txtbusqueda) {
                $haystack = self::normalize_text(implode(' ', array(
                    isset($item['title']) ? $item['title'] : '',
                    isset($item['description']) ? $item['description'] : '',
                    isset($item['category']) ? $item['category'] : '',
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

            if (!$canonical || isset($visited[$canonical])) {
                continue;
            }
            $visited[$canonical] = true;

            if (strpos($content_type, 'json') !== false || self::looks_like_json($body)) {
                $json = json_decode($body, true);
                $body = is_array($json) && isset($json['content']['rendered']) ? $json['content']['rendered'] : '';
            }

            $parsed = self::parse_html($body, $page_url, $parent_category);
            if ($parsed === false) {
                continue;
            }

            foreach ($parsed['items'] as $item) {
                $item['category'] = $item['category'] ?: $parent_category;
                $item['source_page'] = $page_url;
                $item['source_category'] = $parent_category;
                $item = self::normalize_metadata($item);
                $item['search_text'] = self::search_text($item);
                if (isset($item_indexes[$item['url']])) {
                    $existing_index = $item_indexes[$item['url']];
                    $items[$existing_index]['related'] = self::merge_related(
                        $items[$existing_index]['related'],
                        $item['related']
                    );
                    $items[$existing_index]['search_text'] = self::search_text($items[$existing_index]);
                } else {
                    $item_indexes[$item['url']] = count($items);
                    $items[] = $item;
                }
            }

            if ($depth >= self::MAX_CRAWL_DEPTH) {
                continue;
            }

            foreach ($parsed['child_pages'] as $child) {
                $child_url = self::canonical_url($child['url']);
                if (!$child_url || strtolower((string) wp_parse_url($child_url, PHP_URL_HOST)) !== $root_host || isset($visited[$child_url])) {
                    continue;
                }
                $child_category = $child['title'] ?: $parent_category;
                $child_fetched = self::fetch_url($child_url);
                if (!is_wp_error($child_fetched)) {
                    $queue[] = array($child_fetched['url'], $child_fetched['body'], $child_fetched['content_type'], $depth + 1, $child_category);
                }
            }
        }

        return $items;
    }

    private static function parse_html($html, $source_url, $parent_category = '') {
        if (!class_exists('DOMDocument')) {
            return false;
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        if (!$loaded) {
            return false;
        }

        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//main//a[@href] | //article//a[@href] | //div[contains(@class,"entry-content")]//a[@href] | //table//a[@href]');
        $items = array();
        $child_pages = array();
        $seen = array();

        foreach ($links as $link) {
            $list_item = self::related_container($link);
            if ($list_item && self::has_list_item_ancestor($list_item)) {
                continue;
            }

            $href = trim($link->getAttribute('href'));
            $title = trim(preg_replace('/\s+/', ' ', $link->textContent));
            if (!$href || !$title || stripos($href, '#') === 0) {
                continue;
            }

            $absolute_url = self::absolute_url($href, $source_url);
            if (!$absolute_url || isset($seen[$absolute_url])) {
                continue;
            }

            $seen[$absolute_url] = true;
            $extension = strtolower(pathinfo((string) wp_parse_url($absolute_url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $title_text = wp_strip_all_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8'));
            $root_host = strtolower((string) wp_parse_url($source_url, PHP_URL_HOST));
            if (self::is_crawlable_page($absolute_url, $extension, $root_host)) {
                $child_pages[] = array('url' => $absolute_url, 'title' => $title_text);
                continue;
            }
            if (!self::is_document_link($absolute_url, $extension)) {
                continue;
            }
            $type = self::type_from_extension($extension);
            $item = array(
                'id' => md5($absolute_url),
                'title' => $title_text,
                'url' => esc_url_raw($absolute_url),
                'type' => $type,
                'extension' => $extension,
                'category' => self::infer_category($title, $absolute_url) ?: $parent_category,
                'year' => self::infer_year($title . ' ' . $absolute_url),
                'target' => $link->getAttribute('target') ?: '_self',
                'source_page' => $source_url,
            );
            $item = self::normalize_metadata($item);
            $item['description'] = self::description_for_item($list_item, $title);
            $item['related'] = self::related_documents($list_item, $source_url, $seen);
            $item['search_text'] = self::search_text($item);
            $items[] = $item;
        }

        return array('items' => $items, 'child_pages' => $child_pages);
    }

    private static function fetch_url($url) {
        $response = wp_safe_remote_get($url, array(
            'timeout' => 20,
            'redirection' => 3,
            'headers' => array('Accept' => 'text/html, application/json'),
            'user-agent' => 'WordPress; DGES Documentos/' . DGES_DOCS_VERSION,
        ));
        if (is_wp_error($response)) {
            return new WP_Error('dges_source_request', $response->get_error_message(), array('status' => 502));
        }
        $status = wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            return new WP_Error('dges_source_status', sprintf('La fuente respondió con HTTP %d.', $status), array('status' => 502));
        }
        $effective_url = $url;
        if (isset($response['http_response']) && is_object($response['http_response'])) {
            $response_object = $response['http_response']->get_response_object();
            if (is_object($response_object) && !empty($response_object->url)) {
                $effective_url = $response_object->url;
            }
        }

        return array(
            'body' => wp_remote_retrieve_body($response),
            'content_type' => strtolower((string) wp_remote_retrieve_header($response, 'content-type')),
            'url' => $effective_url,
        );
    }

    private static function is_crawlable_page($url, $extension, $root_host) {
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        if ($host !== $root_host || self::is_file_extension($extension)) {
            return false;
        }
        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        return strpos($path, '/wp-admin') === false && strpos($path, '/wp-login') === false;
    }

    private static function is_document_link($url, $extension) {
        if (self::is_file_extension($extension)) {
            return true;
        }

        $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
        return (bool) preg_match('/(document|resol|memor|anexo|instructivo|planilla|normativ|ley|disposicion|decreto)/i', $path);
    }

    private static function is_file_extension($extension) {
        return in_array(strtolower($extension), array(
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'zip', 'rar', 'odt', 'ods', 'odp', 'txt', 'csv',
        ), true);
    }

    private static function canonical_url($url) {
        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $path = isset($parts['path']) ? untrailingslashit($parts['path']) : '';
        return strtolower($parts['scheme']) . '://' . strtolower($parts['host']) . $path .
            (isset($parts['query']) ? '?' . $parts['query'] : '');
    }

    private static function merge_related($existing, $incoming) {
        $merged = array();
        foreach (array_merge((array) $existing, (array) $incoming) as $item) {
            if (!isset($merged[$item['url']])) {
                $merged[$item['url']] = $item;
            }
        }
        return array_values($merged);
    }

    private static function closest_list_item($node) {
        while ($node && $node->parentNode) {
            if (strtolower($node->nodeName) === 'li') {
                return $node;
            }
            $node = $node->parentNode;
        }
        return null;
    }

    private static function related_container($node) {
        $list_item = self::closest_list_item($node);
        if ($list_item) {
            return $list_item;
        }

        while ($node && $node->parentNode) {
            if (strtolower($node->nodeName) === 'td') {
                return $node;
            }
            $node = $node->parentNode;
        }

        return null;
    }

    private static function has_list_item_ancestor($list_item) {
        $parent = $list_item->parentNode;
        while ($parent) {
            if (strtolower($parent->nodeName) === 'li') {
                return true;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    private static function description_for_item($list_item, $title) {
        if (!$list_item) {
            return '';
        }

        $clone = $list_item->cloneNode(true);
        $remove = array();
        $xpath = new DOMXPath($clone->ownerDocument);
        foreach ($xpath->query('.//a | .//ul | .//ol', $clone) as $node) {
            $remove[] = $node;
        }
        foreach ($remove as $node) {
            if ($node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }

        $description = trim(preg_replace('/\s+/', ' ', $clone->textContent));
        $description = preg_replace('/^' . preg_quote($title, '/') . '\s*/iu', '', $description);
        return trim(wp_strip_all_tags(html_entity_decode($description, ENT_QUOTES, 'UTF-8')));
    }

    private static function related_documents($list_item, $source_url, &$seen) {
        if (!$list_item) {
            return array();
        }

        $xpath = new DOMXPath($list_item->ownerDocument);
        $related = array();
        foreach ($xpath->query('./ul//a[@href] | ./ol//a[@href]', $list_item) as $link) {
            $href = trim($link->getAttribute('href'));
            $title = trim(preg_replace('/\s+/', ' ', $link->textContent));
            $url = self::absolute_url($href, $source_url);
            if (!$href || !$title || !$url) {
                continue;
            }

            $extension = strtolower(pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $related_item = array(
                'id' => md5($url),
                'title' => wp_strip_all_tags(html_entity_decode($title, ENT_QUOTES, 'UTF-8')),
                'url' => esc_url_raw($url),
                'type' => self::type_from_extension($extension),
                'extension' => $extension,
                'year' => self::infer_year($title . ' ' . $url),
                'target' => $link->getAttribute('target') ?: '_self',
            );
            $related_item['description'] = '';
            $related[] = $related_item;
            $seen[$url] = true;
        }
        return $related;
    }

    private static function search_text($item) {
        $parts = array(
            $item['title'],
            $item['description'],
            $item['category'],
            $item['type'],
            $item['document_type'],
            $item['number'],
            $item['year'],
            $item['url'],
        );
        foreach ($item['related'] as $related) {
            $parts[] = $related['title'];
            $parts[] = $related['description'];
            $parts[] = $related['url'];
        }
        return implode(' ', $parts);
    }

    private static function normalize_metadata($item) {
        $metadata = self::extract_document_metadata($item['title']);
        $item['document_type'] = $metadata['document_type'];
        $item['number'] = $metadata['number'];
        if (!$item['year'] && $metadata['year']) {
            $item['year'] = $metadata['year'];
        }
        return $item;
    }

    private static function extract_document_metadata($title) {
        $normalized = self::normalize_text($title);
        $types = array(
            'resolucion' => 'resolucion',
            'memorandum' => 'memorandum',
            'memorando' => 'memorandum',
            'disposicion' => 'disposicion',
            'decreto' => 'decreto',
            'ley' => 'ley',
            'circular' => 'circular',
            'instructivo' => 'instructivo',
            'anexo' => 'anexo',
            'planilla' => 'planilla',
        );
        $document_type = 'documento';
        foreach ($types as $label => $value) {
            if (strpos($normalized, $label) !== false) {
                $document_type = $value;
                break;
            }
        }

        $number = '';
        if (preg_match('/\b(?:conjunta\s*)?(\d{1,5})(?:\s*[-\/]\s*|\s+)(\d{2,4})\b/u', $normalized, $match)) {
            $number = $match[1];
            $year = (int) $match[2];
            $year = $year < 100 ? 2000 + $year : $year;
        } else {
            $year = self::infer_year($title);
        }

        return array(
            'document_type' => $document_type,
            'number' => $number,
            'year' => $year,
        );
    }

    private static function parse_json($body) {
        $data = json_decode($body, true);
        if (!is_array($data)) {
            return false;
        }
        if (isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }
        $html = isset($data['content']['rendered']) ? $data['content']['rendered'] : '';
        return $html ? self::parse_html($html, self::get_source_url()) : false;
    }

    private static function looks_like_json($body) {
        $trimmed = ltrim($body);
        return $trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[');
    }

    private static function absolute_url($href, $source_url) {
        if (wp_http_validate_url($href)) {
            return $href;
        }
        $base = wp_parse_url($source_url);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return false;
        }
        if (strpos($href, '//') === 0) {
            return $base['scheme'] . ':' . $href;
        }
        if ($href[0] === '/') {
            return $base['scheme'] . '://' . $base['host'] . $href;
        }
        $directory = trailingslashit(dirname(isset($base['path']) ? $base['path'] : '/'));
        return $base['scheme'] . '://' . $base['host'] . $directory . ltrim($href, '/');
    }

    private static function type_from_extension($extension) {
        $types = array(
            'pdf' => 'pdf',
            'doc' => 'documento',
            'docx' => 'documento',
            'xls' => 'planilla',
            'xlsx' => 'planilla',
            'ppt' => 'presentacion',
            'pptx' => 'presentacion',
            'zip' => 'archivo',
            'rar' => 'archivo',
        );
        return isset($types[$extension]) ? $types[$extension] : 'enlace';
    }

    private static function infer_category($title, $url) {
        $path = (string) wp_parse_url($url, PHP_URL_PATH);
        if (preg_match('#/(evaluacion-desarrollo-curricular|desarrollo-profesional|normativas|resoluciones|curriculares|leyes)[^/]*#i', $path, $match)) {
            return ucwords(str_replace('-', ' ', $match[1]));
        }
        return '';
    }

    private static function infer_year($value) {
        return preg_match('/\b(19|20)\d{2}\b/', $value, $match) ? (int) $match[0] : 0;
    }

    private static function normalize_text($value) {
        $value = remove_accents(wp_strip_all_tags((string) $value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value);
        return strtolower(trim(preg_replace('/\s+/', ' ', $value)));
    }
}
