const cfg = () => window.DGESDocumentos || {};

export function createState(initial = {}) {
    return {
        search: initial.search || '',
        category: initial.category || '',
        page: Math.max(1, parseInt(initial.page, 10) || 1),
        perPage: parseInt(cfg().perPage, 10) || 12,
        total: Math.max(0, parseInt(initial.total, 10) || 0),
        count: Math.max(0, parseInt(initial.count, 10) || 0),
        hasMore: !!initial.hasMore,
        loading: false,
        error: !!initial.error,
        abort: null,
        requestId: 0,
    };
}

export function readStateFromUrl() {
    const params = new URLSearchParams(window.location.search);

    return {
        search: params.get('buscar') || '',
        category: params.get('categoria') || '',
        page: Math.max(1, parseInt(params.get('pagina'), 10) || 1),
    };
}

export function writeStateToUrl(state, { push = false } = {}) {
    const url = new URL(window.location.href);

    setOrDelete(url.searchParams, 'buscar', state.search);
    setOrDelete(url.searchParams, 'categoria', state.category);

    if (state.page > 1) {
        url.searchParams.set('pagina', String(state.page));
    } else {
        url.searchParams.delete('pagina');
    }

    const next = url.pathname + (url.searchParams.toString() ? `?${url.searchParams}` : '') + url.hash;
    const current = window.location.pathname + window.location.search + window.location.hash;

    if (next === current) return;

    if (push) {
        window.history.pushState({ dges: true }, '', next);
    } else {
        window.history.replaceState({ dges: true }, '', next);
    }
}

export function pageUrl(state, page) {
    const url = new URL(window.location.href);

    setOrDelete(url.searchParams, 'buscar', state.search);
    setOrDelete(url.searchParams, 'categoria', state.category);

    if (page > 1) {
        url.searchParams.set('pagina', String(page));
    } else {
        url.searchParams.delete('pagina');
    }

    return url.toString();
}

export function resetFilters(state) {
    state.search = '';
    state.category = '';
    state.page = 1;
    state.total = 0;
    state.count = 0;
    state.hasMore = false;
    state.error = false;
}

function setOrDelete(params, key, value) {
    if (value) {
        params.set(key, value);
    } else {
        params.delete(key);
    }
}
