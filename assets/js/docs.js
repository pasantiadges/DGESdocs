/**
 * Plugin Frontend: Buscador y Filtros Avanzados para Documentos DGES
 * Maneja la interacción del usuario, la construcción de parámetros de búsqueda,
 * las peticiones asíncronas a la REST API de WordPress y el renderizado dinámico.
 */
(function () {
    'use strict';

    /**
     * Sanitiza texto plano para evitar inyecciones XSS al insertar HTML en el DOM.
     * @param {string} value - Texto original
     * @returns {string} Texto seguro escapado
     */
    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    /**
     * Capitaliza la primera letra de un texto.
     * @param {string} value - Texto original (ej: 'pdf')
     * @returns {string} Texto capitalizado (ej: 'Pdf')
     */
    function label(value) {
        return value ? value.charAt(0).toUpperCase() + value.slice(1) : '';
    }

    /**
     * Inicializa el componente de documentos dentro del contenedor del shortcode.
     * @param {HTMLElement} root - Elemento contenedor principal de la sección
     */
    function init(root) {
        // Referencias a los elementos del DOM inyectados por el shortcode
        const filters = root.querySelector('[data-filters]');
        const results = root.querySelector('[data-results]');
        const status = root.querySelector('[data-status]');

        // Estado actual de los filtros aplicados por el usuario
        let state = { txtbusqueda: '', type: '', categoria: '', anio: '', document_type: '', number: '' };

        let searchTimer; // Timer para el retraso (debounce) en la barra de búsqueda
        let allItems = []; // Almacena los resultados devuelven por el servidor

        /**
         * Realiza una petición AJAX (fetch) a la API REST de WordPress enviando
         * los parámetros de filtrado actuales.
         */
        function fetchDocuments() {
            status.textContent = 'Buscando…';

            // Construcción dinámica de los parámetros de la URL mediante URLSearchParams
            const params = new URLSearchParams();
            if (state.txtbusqueda) params.append('txtbusqueda', state.txtbusqueda);
            if (state.categoria) params.append('categoria', state.categoria);
            if (state.anio) params.append('anio', state.anio);
            if (state.type) params.append('type', state.type);
            if (state.document_type) params.append('document_type', state.document_type);
            if (state.number) params.append('number', state.number);

            let url = DGESDocumentos.endpoint;
            const queryString = params.toString();
            if (queryString) {
                url += '?' + queryString;
            }

            // Petición HTTP segura utilizando el Nonce de seguridad de WordPress
            fetch(url, { headers: { 'X-WP-Nonce': DGESDocumentos.nonce } })
                .then(function (response) {
                    if (!response.ok) throw new Error('No se pudieron cargar los documentos.');
                    return response.json();
                })
                .then(function (payload) {
                    allItems = payload.items || [];

                    // Si la respuesta incluye facetas (categorías, años disponibles, etc.), renderiza los selectores
                    if (payload.facets) {
                        renderFilters(payload.facets);
                    }

                    // Renderiza las tarjetas de documentos obtenidas del servidor
                    renderResults(allItems);
                    status.textContent = allItems.length + ' documento(s) encontrado(s).';
                })
                .catch(function (error) {
                    status.textContent = error.message;
                    results.innerHTML = '';
                });
        }

        /**
         * Genera e inyecta dinámicamente los elementos HTML del formulario de filtros y selectores.
         * @param {Object} facets - Objeto que contiene las opciones únicas para los selectores (años, tipos, categorías)
         */
        function renderFilters(facets) {
            filters.innerHTML =
                '<label>Buscar <input type="search" data-search placeholder="Ej.: Resolución 234-2023 o Memo 001/22"></label>' +
                '<label>Tipo <select data-type><option value="">Todos</option>' +
                (facets.types || []).map(function (item) { return '<option value="' + escapeHtml(item) + '">' + escapeHtml(label(item)) + '</option>'; }).join('') +
                '</select></label>' +
                '<label>Categoría <select data-category><option value="">Todas</option>' +
                (facets.categories || []).map(function (item) { return '<option value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</option>'; }).join('') +
                '</select></label>' +
                '<label>Año <select data-year><option value="">Todos</option>' +
                (facets.years || []).map(function (item) { return '<option value="' + item + '">' + item + '</option>'; }).join('') +
                '</select></label>' +
                '<label>Tipo documental <select data-document-type><option value="">Todos</option>' +
                (facets.document_types || []).map(function (item) {
                    return '<option value="' + escapeHtml(item) + '">' + escapeHtml(label(item)) + '</option>';
                }).join('') +
                '</select></label>' +
                '<label>Número <select data-number><option value="">Todos</option>' +
                (facets.numbers || []).map(function (item) {
                    return '<option value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</option>';
                }).join('') +
                '</select></label>' +
                '<button type="button" data-reset>Limpiar</button>';

            // Mantiene sincronizados los valores actuales del estado con los inputs visuales
            filters.querySelector('[data-search]').value = state.txtbusqueda;
            filters.querySelector('[data-type]').value = state.type;
            filters.querySelector('[data-category]').value = state.categoria;
            filters.querySelector('[data-year]').value = state.anio;
            filters.querySelector('[data-document-type]').value = state.document_type;
            filters.querySelector('[data-number]').value = state.number;
        }

        /**
         * Renderiza las tarjetas de documentos en pantalla, incluyendo sus metadatos
         * y los documentos anidados relacionados (anexos, memorándums) mediante etiquetas <details>.
         * @param {Array} items - Listado de documentos a mostrar
         */
        function renderResults(items) {
            results.innerHTML = items.length ? items.map(function (item) {
                return '<article class="dges-document-card"><span class="dges-document-type">' +
                    escapeHtml(label(item.type)) + '</span><h3>' + escapeHtml(item.title) +
                    '</h3>' + (item.description ? '<p>' + escapeHtml(item.description) + '</p>' : '') +
                    (item.category ? '<p>' + escapeHtml(item.category) + '</p>' : '') +
                    (item.document_type ? '<p class="dges-document-meta">' +
                        escapeHtml(label(item.document_type)) +
                        (item.number ? ' N.º ' + escapeHtml(item.number) : '') +
                        (item.year ? '/' + escapeHtml(item.year) : '') + '</p>' : '') +
                    // Renderizado de elementos anidados/relacionados (padre/hijo)
                    ((item.related || []).length ? '<details><summary>Documentos relacionados (' +
                        item.related.length + ')</summary><ul>' + item.related.map(function (related) {
                            return '<li><a href="' + escapeHtml(related.url) + '" target="' +
                                escapeHtml(related.target) + '" rel="noopener">' + escapeHtml(related.title) +
                                '</a>' + (related.description ? ' — ' + escapeHtml(related.description) : '') +
                                '</li>';
                        }).join('') + '</ul></details>' : '') +
                    '<a href="' + escapeHtml(item.url) + '" target="' + escapeHtml(item.target) +
                    '" rel="noopener">Abrir documento</a></article>';
            }).join('') : '<p>No se encontraron documentos con esos filtros.</p>';
        }

        // ==========================================
        // MANEJADORES DE EVENTOS (LISTENERS)
        // ==========================================

        // Evento de escritura en la barra de búsqueda (con Debounce para evitar llamadas excesivas)
        filters.addEventListener('input', function (event) {
            const field = event.target;
            if (field.matches('[data-search]')) {
                state.txtbusqueda = field.value;
                clearTimeout(searchTimer);
                searchTimer = setTimeout(fetchDocuments, 300); // Espera 300ms antes de consultar al servidor
            }
        });

        // Evento de cambio en los menús desplegables (selects) de filtrado
        filters.addEventListener('change', function (event) {
            const field = event.target;
            if (field.matches('[data-type]')) state.type = field.value;
            if (field.matches('[data-category]')) state.categoria = field.value;
            if (field.matches('[data-year]')) state.anio = field.value;
            if (field.matches('[data-document-type]')) state.document_type = field.value;
            if (field.matches('[data-number]')) state.number = field.value;
            fetchDocuments(); // Ejecuta la búsqueda de inmediato al cambiar un select
        });

        // Evento click para el botón de limpiar filtros
        filters.addEventListener('click', function (event) {
            if (event.target.matches('[data-reset]')) {
                state = { txtbusqueda: '', type: '', categoria: '', anio: '', document_type: '', number: '' };
                fetchDocuments();
            }
        });

        // Carga inicial de documentos al montar el componente
        fetchDocuments();
    }

    // Inicializa el plugin cuando el DOM de la página web esté completamente cargado
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-dges-documentos]').forEach(init);
    });
}());