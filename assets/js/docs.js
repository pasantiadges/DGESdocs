import { createState, resetFilters } from './modules/state.js';
import { fetchDocuments } from './modules/api.js';
import { renderFilters, renderResults, setStatus, updateLoadMore } from './modules/render.js';
import { bindEvents } from './modules/events.js';

const cfg = window.DGESDocumentos || {};
const i18n = cfg.i18n || {};

async function loadPage(root, state, append) {
    if (state.loading) return;
    state.loading = true;

    // Buscamos los elementos de forma segura
    const statusEl = root.querySelector('[data-status]');
    const resultsEl = root.querySelector('[data-results]');
    const filtersEl = root.querySelector('[data-filters]');
    const loadMoreEl = root.querySelector('[data-load-more]');

    updateLoadMore(loadMoreEl, state.hasMore, true, i18n);
    if (!append) setStatus(statusEl, i18n.loading || 'Cargando…', false);

    try {
        const data = await fetchDocuments(state);
        state.hasMore = !!data.has_more;

        if (!append) state.items = [];
        state.items.push(...(data.items || []));

        if (data.facets) renderFilters(filtersEl, data.facets, state);
        renderResults(resultsEl, state.items);

        setStatus(statusEl, state.items.length
            ? `${state.items.length} documento(s) encontrado(s).`
            : (i18n.noResults || 'Sin resultados.'), state.items.length > 0);

        updateLoadMore(loadMoreEl, state.hasMore, false, i18n);
    } catch (err) {
        console.error('[DGES]', err);
        setStatus(statusEl, i18n.error || 'Error al cargar.', false);
    } finally {
        state.loading = false;
    }
}

function init(root) {
    // ¡IMPORTANTE! Evitamos que el script se ejecute dos veces en la misma sección
    if (root.dataset.dgesInit) return;
    root.dataset.dgesInit = 'true';

    const state = createState();

    const reload = () => {
        state.page = 1;
        state.hasMore = false;
        loadPage(root, state, false);
    };

    bindEvents({
        filtersEl: root.querySelector('[data-filters]'),
        loadMoreBtn: root.querySelector('[data-load-more]'),
        state,
        onSearch: reload,
        onFilterChange: reload,
        onReset: () => { resetFilters(state); reload(); },
        onLoadMore: () => {
            state.page += 1;
            loadPage(root, state, true);
        },
    });

    loadPage(root, state, false);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-dges-documentos]').forEach(init);
});