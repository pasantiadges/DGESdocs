/**
 * Lógica del frontend para el plugin DGES Documentos.
 * Maneja la carga asíncrona de datos, los filtros dinámicos y la interfaz de usuario.
 */
(function () {
    'use strict';

    // Función auxiliar para escapar HTML y evitar vulnerabilidades XSS.
    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    // Capitaliza la primera letra de un texto.
    function label(value) {
        return value ? value.charAt(0).toUpperCase() + value.slice(1) : '';
    }

    // Inicializa el contenedor del plugin en la página.
    function init(root) {
        var filters = root.querySelector('[data-filters]');
        var results = root.querySelector('[data-results]');
        var status = root.querySelector('[data-status]');
        var state = { search: '', type: '', category: '', year: '', document_type: '', number: '' };
        var searchTimer;
        var allItems = [];

        // Normaliza texto para quitar tildes y facilitar la búsqueda insensible a acentos.
        function normalize(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^\p{L}\p{N}]+/gu, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        // Genera el texto indexado para búsquedas dentro del documento y sus relaciones.
        function itemSearchText(item) {
            var related = (item.related || []).map(function (document) {
                return [document.title, document.description, document.url].join(' ');
            }).join(' ');
            return normalize([
                item.title, item.description, item.category, item.type,
                item.document_type, item.number, item.year, item.url, related
            ].join(' '));
        }

        // Petición AJAX / Fetch a la API REST de WordPress para obtener los documentos.
        function fetchDocuments() {
            status.textContent = 'Buscando documentos...';
            fetch(DGESDocumentos.endpoint, { headers: { 'X-WP-Nonce': DGESDocumentos.nonce } })
                .then(function (response) {
                    if (!response.ok) throw new Error('No se pudieron cargar los documentos.');
                    return response.json();
                })
                .then(function (payload) {
                    allItems = payload.items || [];
                    renderFilters(payload.facets || {});
                    applyFilters();
                })
                .catch(function (error) {
                    status.textContent = error.message;
                    results.innerHTML = '';
                });
        }

        // Aplica los filtros locales sobre el listado de documentos obtenido.
        function applyFilters() {
            var search = normalize(state.search);
            var searchTokens = search ? search.split(' ') : [];
            var items = allItems.filter(function (item) {
                var indexedText = itemSearchText(item);
                var matchesSearch = !search || searchTokens.every(function (token) {
                    return indexedText.indexOf(token) !== -1;
                });
                return matchesSearch &&
                    (!state.type || item.type === state.type) &&
                    (!state.category || item.category === state.category) &&
                    (!state.year || String(item.year) === String(state.year)) &&
                    (!state.document_type || item.document_type === state.document_type) &&
                    (!state.number || item.number === state.number);
            });
            renderResults(items);
            status.textContent = items.length + ' documento(s) encontrado(s).';
        }

        // Renderiza los campos de selección y búsqueda en el HTML.
        function renderFilters(facets) {
            filters.innerHTML =
                '<label>Buscar <input type="search" data-search placeholder="Ej.: Resolución 234-2023"></label>' +
                '<label>Tipo <select data-type><option value="">Todos</option>' +
                (facets.types || []).map(function (item) { return '<option value="' + escapeHtml(item) + '">' + escapeHtml(label(item)) + '</option>'; }).join('') +
                '</select></label>' +
                '<label>Categoría <select data-category><option value="">Todas</option>' +
                (facets.categories || []).map(function (item) { return '<option value="' + escapeHtml(item) + '">' + escapeHtml(item) + '</option>'; }).join('') +
                '</select></label>' +
                '<label>Año <select data-year><option value="">Todos</option>' +
                (facets.years || []).map(function (item) { return '<option value="' + item + '">' + item + '</option>'; }).join('') +
                '</select></label>' +
                '<button type="button" data-reset>Limpiar</button>';

            filters.querySelector('[data-search]').value = state.search;
            filters.querySelector('[data-type]').value = state.type;
            filters.querySelector('[data-category]').value = state.category;
            filters.querySelector('[data-year]').value = state.year;
        }

        // Dibuja las tarjetas de resultados en pantalla.
        function renderResults(items) {
            results.innerHTML = items.length ? items.map(function (item) {
                return '<article class="dges-document-card"><span class="dges-document-type">' +
                    escapeHtml(label(item.type)) + '</span><h3>' + escapeHtml(item.title) +
                    '</h3>' + (item.description ? '<p>' + escapeHtml(item.description) + '</p>' : '') +
                    '<a href="' + escapeHtml(item.url) + '" target="' + escapeHtml(item.target) +
                    '" rel="noopener">Abrir documento</a></article>';
            }).join('') : '<p>No se encontraron documentos con esos filtros.</p>';
        }

        // Eventos de interacción del usuario con los filtros.
        filters.addEventListener('input', function (event) {
            if (event.target.matches('[data-search]')) state.search = event.target.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 150);
        });

        filters.addEventListener('change', function (event) {
            if (event.target.matches('[data-type]')) state.type = event.target.value;
            if (event.target.matches('[data-category]')) state.category = event.target.value;
            if (event.target.matches('[data-year]')) state.year = event.target.value;
            applyFilters();
        });

        filters.addEventListener('click', function (event) {
            if (event.target.matches('[data-reset]')) {
                state = { search: '', type: '', category: '', year: '', document_type: '', number: '' };
                applyFilters();
            }
        });

        // Llamada inicial para cargar los documentos al montar el componente.
        fetchDocuments();
    }

    // Disparador principal cuando el DOM carga por completo.
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-dges-documentos]').forEach(init);
    });
}());