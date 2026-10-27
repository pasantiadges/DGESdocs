import { sprintf } from './utils.js';
import { pageUrl } from './state.js';

export function renderItems(container, items, append = false) {
    if (!container) return;

    const html = (items || [])
        .map(function (item) {
            return item && item.html ? item.html : '';
        })
        .join('');

    if (append) {
        if (html) container.insertAdjacentHTML('beforeend', html);
    } else {
        container.innerHTML = html;
    }
}

export function setStatus(el, text, hidden = false) {
    if (!el) return;

    el.textContent = text || '';
    el.hidden = !!hidden || !text;
}

export function updateLoadMore(link, state, i18n) {
    if (!link) return;

    link.hidden = !state.hasMore;
    link.textContent = state.loading
        ? (i18n.loadingMore || 'Cargando más…')
        : (i18n.loadMore || 'Ver más');
    link.setAttribute('aria-disabled', state.loading ? 'true' : 'false');
    link.href = pageUrl(state, state.page + 1);
}

export function updateClearButtons(root, state) {
    const clearSearch = root.querySelector('[data-clear-search]');
    const clearFilters = root.querySelector('.dges-search-bar [data-clear-filters]');

    if (clearSearch) clearSearch.hidden = !state.search;
    if (clearFilters) clearFilters.hidden = !(state.search || state.category);
}

export function setLoading(root, loading) {
    root.classList.toggle('is-loading', !!loading);
    root.setAttribute('aria-busy', loading ? 'true' : 'false');
}

export function showEmpty(root, message, i18n) {
    const empty = root.querySelector('[data-empty]');
    const results = root.querySelector('[data-results]');
    const status = root.querySelector('[data-status]');

    if (empty) {
        empty.hidden = false;
        const paragraph = empty.querySelector('p');
        if (paragraph) paragraph.textContent = message;

        const clear = empty.querySelector('[data-clear-filters]');
        if (clear) clear.textContent = i18n.clearFilters || 'Limpiar filtros';
    }

    if (results) results.hidden = true;
    setStatus(status, '', true);
}

export function hideEmpty(root) {
    const empty = root.querySelector('[data-empty]');
    const results = root.querySelector('[data-results]');

    if (empty) empty.hidden = true;
    if (results) results.hidden = false;
}

export function showError(root, message, i18n, hideResults = false) {
    const error = root.querySelector('[data-error]');
    const empty = root.querySelector('[data-empty]');
    const results = root.querySelector('[data-results]');

    if (error) {
        error.hidden = false;
        const paragraph = error.querySelector('p');
        if (paragraph) paragraph.textContent = message;

        const retry = error.querySelector('[data-retry]');
        if (retry) retry.textContent = i18n.retry || 'Reintentar';
    }

    if (empty) empty.hidden = true;
    if (hideResults && results) results.hidden = true;
}

export function hideError(root) {
    const error = root.querySelector('[data-error]');
    if (error) error.hidden = true;
}

export function statusText(state, i18n) {
    if (state.total < 1) return '';

    const total = formatNumber(state.total);
    const count = formatNumber(state.count);

    if (state.count >= state.total) {
        return sprintf(state.total === 1 ? i18n.documentsOne : i18n.documentsMany, total);
    }

    return sprintf(
        state.total === 1 ? i18n.showingOne : i18n.showingMany,
        count,
        total
    );
}

export function emptyMessage(state, currentCategory, i18n) {
    const search = (state.search || '').trim();
    const category = (state.category || '').trim();

    if (search && category) {
        return sprintf(i18n.emptyBoth, search, currentCategory || category);
    }
    if (search) {
        return sprintf(i18n.emptySearch, search);
    }
    if (category) {
        return i18n.emptyCategory || 'No hay documentos en esta categoría.';
    }

    return i18n.emptyAll || 'Todavía no hay documentos disponibles.';
}

export function categoryLabel(root, state) {
    const select = root.querySelector('[data-category]');

    if (select && select.selectedIndex >= 0 && select.options[select.selectedIndex]) {
        return select.options[select.selectedIndex].text.trim();
    }

    return state.category || '';
}

function formatNumber(value) {
    try {
        return new Intl.NumberFormat().format(value);
    } catch (error) {
        return String(value);
    }
}
