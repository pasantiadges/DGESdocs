<?php
/**
 * Solo presentación HTML de los shortcodes del frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DGES_Docs_Render {

        public static function shortcode_buscador($title) {
        return '<section class="dges-documentos" data-dges-documentos>
            <h2>' . esc_html($title) . '</h2>
            
            <div class="dges-search-bar">
                <input type="search" data-search placeholder="Buscar por nombre, número o resolución..." class="dges-search-input">
                <button type="button" class="dges-toggle-filters" data-toggle-filters>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    Filtros
                </button>
            </div>

            <div class="dges-documentos-filters" data-filters style="display:none;"></div>
            
            <p class="dges-documentos-status" data-status aria-live="polite">Cargando documentos…</p>
            <div class="dges-documentos-results" data-results></div>
            <div class="dges-documentos-pagination" data-pagination>
                <button type="button" class="dges-load-more" data-load-more hidden>Ver más</button>
            </div>
            </section>';
    }

    public static function shortcode_destacados($items, $title) {
        $output = '<section class="dges-documentos dges-destacados-section">' .
            '<h2>' . esc_html($title) . '</h2>' .
            '<div class="dges-documentos-results">';

        if (empty($items)) {
            $output .= '<p>No hay documentos destacados disponibles.</p>';
        } else {
            foreach ($items as $item) {
                $output .= self::card($item);
            }
        }

        $output .= '</div></section>';
        return $output;
    }

    private static function card($item) {
        $type        = isset($item['type']) ? $item['type'] : '';
        $title       = isset($item['title']) ? $item['title'] : '';
        $description = isset($item['description']) ? $item['description'] : '';
        $category    = isset($item['category']) ? $item['category'] : '';
        $url         = isset($item['url']) ? $item['url'] : '';
        $target      = !empty($item['target']) ? $item['target'] : '_self';

        $html = '<article class="dges-document-card">';
        if ($type !== '') {
            $html .= '<span class="dges-document-type">' . esc_html(ucfirst($type)) . '</span>';
        }
        $html .= '<h3>' . esc_html($title) . '</h3>';
        if ($description) $html .= '<p>' . esc_html($description) . '</p>';
        if ($category)    $html .= '<p class="dges-cat">' . esc_html($category) . '</p>';
        if ($url)         $html .= '<a href="' . esc_url($url) . '" target="' . esc_attr($target) . '" rel="noopener">Abrir documento</a>';
        $html .= '</article>';

        return $html;
    }
}