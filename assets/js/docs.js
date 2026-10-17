(function () {
    'use strict';

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (char) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
        });
    }

    function label(value) {
        return value ? value.charAt(0).toUpperCase() + value.slice(1) : '';
    }

    function init(root) {
        var filters = root.querySelector('[data-filters]');
        var results = root.querySelector('[data-results]');
        var status = root.querySelector('[data-status]');
        var state = { search: '', type: '', category: '', year: '', document_type: '', number: '' };
        var searchTimer;
        var allItems = [];

        function normalize(value) {
            return String(value || '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .replace(/[^\p{L}\p{N}]+/gu, ' ')
                .replace(/\s+/g, ' ')
                .trim();
        }

        function itemSearchText(item) {
            var related = (item.related || []).map(function (document) {
                return [document.title, document.description, document.url].join(' ');
            }).join(' ');
            return normalize([
                item.title, item.description, item.category, item.type,
                item.document_type, item.number, item.year, item.url, related
            ].join(' '));
        }

        function fetchDocuments() {
            status.textContent = 'Buscando…';
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

            filters.querySelector('[data-search]').value = state.search;
            filters.querySelector('[data-type]').value = state.type;
            filters.querySelector('[data-category]').value = state.category;
            filters.querySelector('[data-year]').value = state.year;
            filters.querySelector('[data-document-type]').value = state.document_type;
            filters.querySelector('[data-number]').value = state.number;
        }

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

        filters.addEventListener('input', function (event) {
            var field = event.target;
            if (field.matches('[data-search]')) state.search = field.value;
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, 150);
        });
        filters.addEventListener('change', function (event) {
            var field = event.target;
            if (field.matches('[data-type]')) state.type = field.value;
            if (field.matches('[data-category]')) state.category = field.value;
            if (field.matches('[data-year]')) state.year = field.value;
            if (field.matches('[data-document-type]')) state.document_type = field.value;
            if (field.matches('[data-number]')) state.number = field.value;
            applyFilters();
        });
        filters.addEventListener('click', function (event) {
            if (event.target.matches('[data-reset]')) {
                state = { search: '', type: '', category: '', year: '', document_type: '', number: '' };
                applyFilters();
            }
        });

        fetchDocuments();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-dges-documentos]').forEach(init);
    });
}());
