import { debounce } from './utils.js';

export function bindEvents(root, state, handlers) {
    const form = root.querySelector('[data-dges-search-form]');
    const input = root.querySelector('[data-search]');
    const select = root.querySelector('[data-category]');
    const clearSearch = root.querySelector('[data-clear-search]');
    const clearFilters = root.querySelector('.dges-search-bar [data-clear-filters]');
    const loadMore = root.querySelector('[data-load-more]');
    const retry = root.querySelector('[data-error] [data-retry]');
    const emptyClear = root.querySelector('[data-empty] [data-clear-filters]');

    const debouncedSearch = debounce(function (value) {
        handlers.onSearch(value, { push: false });
    }, 400);

    if (input) {
        input.addEventListener('input', function () {
            handlers.onInput(input.value);
            debouncedSearch(input.value);
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                debouncedSearch.cancel();
                handlers.onSearch(input.value, { push: true });
                return;
            }

            if (event.key === 'Escape' && input.value !== '') {
                event.preventDefault();
                debouncedSearch.cancel();
                input.value = '';
                handlers.onClearSearch();
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            debouncedSearch.cancel();
            handlers.onSearch(input ? input.value : '', { push: true });
        });
    }

    if (select) {
        select.addEventListener('change', function () {
            debouncedSearch.cancel();
            handlers.onCategory(select.value);
        });
    }

    if (clearSearch) {
        clearSearch.addEventListener('click', function () {
            debouncedSearch.cancel();
            if (input) {
                input.value = '';
                input.focus();
            }
            handlers.onClearSearch();
        });
    }

    if (clearFilters) {
        clearFilters.addEventListener('click', function () {
            debouncedSearch.cancel();
            if (input) input.value = '';
            if (select) select.value = '';
            handlers.onClear();
            if (input) input.focus();
        });
    }

    if (emptyClear) {
        emptyClear.addEventListener('click', function (event) {
            event.preventDefault();
            debouncedSearch.cancel();
            if (input) input.value = '';
            if (select) select.value = '';
            handlers.onClear();
            if (input) input.focus();
        });
    }

    if (loadMore) {
        loadMore.addEventListener('click', function (event) {
            event.preventDefault();
            debouncedSearch.cancel();

            if (!state.loading && state.hasMore) {
                handlers.onLoadMore();
            }
        });
    }

    if (retry) {
        retry.addEventListener('click', function (event) {
            event.preventDefault();
            debouncedSearch.cancel();
            handlers.onRetry();
        });
    }

    window.addEventListener('popstate', function () {
        debouncedSearch.cancel();
        handlers.onPopState();
    });
}
