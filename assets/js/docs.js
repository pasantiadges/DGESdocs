import { createState, readStateFromUrl, writeStateToUrl, resetFilters } from './modules/state.js';
import { fetchDocuments } from './modules/api.js';
import {
    renderItems,
    setStatus,
    updateLoadMore,
    updateClearButtons,
    setLoading,
    showEmpty,
    hideEmpty,
    showError,
    hideError,
    statusText,
    emptyMessage,
    categoryLabel,
} from './modules/render.js';
import { bindEvents } from './modules/events.js';

const DEFAULT_I18N = {
    loading: 'Cargando documentos…',
    loadingMore: 'Cargando más…',
    loadMore: 'Ver más',
    error: 'No pudimos cargar los documentos.',
    retry: 'Reintentar',
    clearFilters: 'Limpiar filtros',
    emptySearch: 'No encontramos documentos para “%s”.',
    emptyBoth: 'No encontramos documentos para “%1$s” en “%2$s”.',
    emptyCategory: 'No hay documentos en esta categoría.',
    emptyAll: 'Todavía no hay documentos disponibles.',
    documentsOne: '%s documento',
    documentsMany: '%s documentos',
    showingOne: 'Mostrando %1$s de %2$s documento',
    showingMany: 'Mostrando %1$s de %2$s documentos',
};

const config = window.DGESDocumentos || {};
const i18n = Object.assign({}, DEFAULT_I18N, config.i18n || {});

let urlStateOwner = null;

function readInitial(root) {
    const data = root.dataset;

    return {
        search: data.initialSearch || '',
        category: data.initialCategory || '',
        page: parseInt(data.initialPage, 10) || 1,
        total: parseInt(data.initialTotal, 10) || 0,
        count: parseInt(data.initialCount, 10) || 0,
        hasMore: data.initialHasMore === '1',
        error: data.initialError === '1',
    };
}

function init(root) {
    if (root.dataset.dgesInit) return;
    root.dataset.dgesInit = 'true';

    const state = createState(readInitial(root));
    const owner = !urlStateOwner;

    if (owner) urlStateOwner = root;

    const elements = {
        form: root.querySelector('[data-dges-search-form]'),
        input: root.querySelector('[data-search]'),
        select: root.querySelector('[data-category]'),
        results: root.querySelector('[data-results]'),
        status: root.querySelector('[data-status]'),
        loadMore: root.querySelector('[data-load-more]'),
        empty: root.querySelector('[data-empty]'),
        error: root.querySelector('[data-error]'),
    };

    const syncControls = function () {
        if (elements.input) elements.input.value = state.search;

        if (elements.select) {
            if (state.category && !Array.from(elements.select.options).some(function (option) {
                return option.value === state.category;
            })) {
                const option = document.createElement('option');
                option.value = state.category;
                option.textContent = state.category;
                elements.select.appendChild(option);
            }

            elements.select.value = state.category;
        }

        updateClearButtons(root, state);
    };

    const reload = async function ({ append = false, push = false } = {}) {
        if (state.abort) state.abort.abort();

        const controller = new AbortController();
        const requestId = ++state.requestId;

        state.abort = controller;
        state.loading = true;

        setLoading(root, true);
        hideError(root);
        updateLoadMore(elements.loadMore, state, i18n);

        if (!append) {
            setStatus(elements.status, i18n.loading || DEFAULT_I18N.loading, false);
            hideEmpty(root);
        }

        try {
            const data = await fetchDocuments(state, controller.signal);

            if (requestId !== state.requestId) return;

            const items = data.items || [];
            const pagination = data.pagination || {};

            if (append) {
                renderItems(elements.results, items, true);
                state.count += items.length;
            } else {
                renderItems(elements.results, items, false);
                state.count = items.length;
            }

            state.page = parseInt(pagination.page, 10) || state.page;
            state.total = parseInt(pagination.total, 10) || 0;
            state.hasMore = !!pagination.has_more;
            state.error = false;

            hideError(root);

            if (state.total === 0) {
                showEmpty(root, emptyMessage(state, categoryLabel(root, state), i18n), i18n);
            } else {
                hideEmpty(root);
                setStatus(elements.status, statusText(state, i18n), false);
            }

            updateClearButtons(root, state);
            updateLoadMore(elements.loadMore, state, i18n);

            if (owner) {
                writeStateToUrl(state, { push });
            }
        } catch (error) {
            if (error.name === 'AbortError' || requestId !== state.requestId) {
                return;
            }

            console.error('[DGES]', error);
            state.error = true;
            showError(root, i18n.error || DEFAULT_I18N.error, i18n, !append);
            setStatus(elements.status, '', true);
        } finally {
            if (requestId === state.requestId) {
                state.loading = false;
                state.abort = null;
                setLoading(root, false);
                updateLoadMore(elements.loadMore, state, i18n);
            }
        }
    };

    const onInput = function (value) {
        updateClearButtons(root, { search: value, category: state.category });
    };

    const onSearch = function (value, { push = false } = {}) {
        state.search = value;
        state.category = elements.select ? elements.select.value : state.category;
        state.page = 1;
        state.count = 0;
        state.total = 0;
        state.hasMore = false;

        updateClearButtons(root, state);
        reload({ append: false, push });
    };

    const onClearSearch = function () {
        if (elements.input) elements.input.value = '';
        onSearch('', { push: true });
    };

    const onClear = function () {
        if (elements.input) elements.input.value = '';
        if (elements.select) elements.select.value = '';

        resetFilters(state);
        updateClearButtons(root, state);
        reload({ append: false, push: true });
    };

    const onCategory = function (value) {
        state.category = value;
        state.search = elements.input ? elements.input.value : state.search;
        state.page = 1;
        state.count = 0;
        state.total = 0;
        state.hasMore = false;

        updateClearButtons(root, state);
        reload({ append: false, push: true });
    };

    const onLoadMore = function () {
        if (state.loading || !state.hasMore) return;

        state.page += 1;
        reload({ append: true, push: true });
    };

    const onRetry = function () {
        state.page = 1;
        state.count = 0;
        state.total = 0;
        state.hasMore = false;

        reload({ append: false, push: false });
    };

    const onPopState = function () {
        if (!owner) return;

        const urlState = readStateFromUrl();

        state.search = urlState.search;
        state.category = urlState.category;
        state.page = urlState.page;
        state.count = 0;
        state.total = 0;
        state.hasMore = false;
        state.error = false;

        syncControls();
        reload({ append: false, push: false });
    };

    bindEvents(root, state, {
        onInput,
        onSearch,
        onCategory,
        onClear,
        onClearSearch,
        onLoadMore,
        onRetry,
        onPopState,
    });

    syncControls();
    updateLoadMore(elements.loadMore, state, i18n);

    if (state.error) {
        setStatus(elements.status, '', true);
    }
}

function boot() {
    document.querySelectorAll('[data-dges-documentos]').forEach(init);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
