export function createState() {
    return {
        filters: { txtbusqueda: '', type: '', categoria: '', anio: '', number: '' },
        page: 1,
        perPage: parseInt(window.DGESDocumentos?.perPage, 10) || 12,
        hasMore: false,
        loading: false,
        items: [],
    };
}

export function resetFilters(state) {
    state.filters = { txtbusqueda: '', type: '', categoria: '', anio: '', number: '' };
    state.page = 1;
    state.hasMore = false;
    state.items = [];
}