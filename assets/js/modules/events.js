import { debounce } from './utils.js';

export function bindEvents({ filtersEl, loadMoreBtn, state, onSearch, onFilterChange, onReset, onLoadMore }) {
    const searchInput = document.querySelector('[data-search]');
    const toggleBtn = document.querySelector('[data-toggle-filters]');

    // 1. Escuchar la barra de búsqueda principal (la de arriba)
    if (searchInput) {
        const debouncedSearch = debounce(() => {
            state.filters.txtbusqueda = searchInput.value;
            onSearch();
        }, 300);

        searchInput.addEventListener('input', debouncedSearch);
    }

    // 2. Escuchar los selectores de filtros (Categoría, Año, Número)
    if (filtersEl) {
        filtersEl.addEventListener('change', (e) => {
            const map = {
                '[data-category]': 'categoria',
                '[data-year]': 'anio',
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

        // 3. Escuchar el botón de "Limpiar"
        filtersEl.addEventListener('click', (e) => {
            if (e.target.matches('[data-reset]')) {
                if (searchInput) searchInput.value = ''; // Limpiamos la barra de arriba
                onReset();
            }
        });
    }

    // 4. Mostrar/ocultar el panel de filtros
    if (toggleBtn && filtersEl) {
        toggleBtn.addEventListener('click', () => {
            const isHidden = filtersEl.style.display === 'none';
            filtersEl.style.display = isHidden ? 'flex' : 'none';
            toggleBtn.classList.toggle('is-active', isHidden);
        });
    }

    // 5. Botón "Ver más"
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', () => {
            if (state.hasMore && !state.loading) onLoadMore();
        });
    }
}