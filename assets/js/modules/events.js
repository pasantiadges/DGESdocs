import { debounce } from './utils.js';

export function bindEvents({ filtersEl, loadMoreBtn, state, onSearch, onFilterChange, onReset, onLoadMore }) {
    if (filtersEl) {
        const debouncedSearch = debounce(onSearch, 300);

        // Mostrar/ocultar el panel de filtros
        const toggleBtn = document.querySelector('[data-toggle-filters]');
        if (toggleBtn && filtersEl) {
            toggleBtn.addEventListener('click', () => {
                const isHidden = filtersEl.style.display === 'none';
                filtersEl.style.display = isHidden ? 'flex' : 'none';
                toggleBtn.classList.toggle('is-active', isHidden);
            });
        }

        filtersEl.addEventListener('input', (e) => {
            if (e.target.matches('[data-search]')) {
                state.filters.txtbusqueda = e.target.value;
                debouncedSearch();
            }
        });

        filtersEl.addEventListener('change', (e) => {
            const map = {
                '[data-type]': 'type',
                '[data-category]': 'categoria',
                '[data-year]': 'anio',
                '[data-document-type]': 'document_type',
                '[data-number]': 'number',
            };
            for (const sel in map) {
                if (e.target.matches(sel)) {
                    state.filters[map[sel]] = e.target.value;
                    onFilterChange();
                    return;
                }
            }
        });
        filtersEl.addEventListener('click', (e) => {
            if (e.target.matches('[data-reset]')) {
                const searchInput = document.querySelector('[data-search]');
                if (searchInput) searchInput.value = ''; // Limpia la barra de búsqueda principal
                onReset();
            }
        });
    }

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            if (state.hasMore && !state.loading) onLoadMore();
        });
    }
}