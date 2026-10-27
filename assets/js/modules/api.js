export async function fetchDocuments(state, signal) {
    const config = window.DGESDocumentos || {};
    const endpoint = config.endpoint || '';

    if (!endpoint) {
        throw new Error('DGES: falta el endpoint de documentos.');
    }

    const params = new URLSearchParams();
    params.set('page', String(state.page));
    params.set('per_page', String(state.perPage));

    if (state.search) params.set('search', state.search);
    if (state.category) params.set('category', state.category);

    const separator = endpoint.includes('?') ? '&' : '?';
    const url = `${endpoint}${separator}${params.toString()}`;

    const response = await fetch(url, {
        signal,
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        let message = '';

        try {
            const data = await response.json();
            message = data?.message || '';
        } catch (error) {
            // La respuesta de error no era JSON; usamos el status.
        }

        const requestError = new Error(message || `HTTP ${response.status}`);
        requestError.status = response.status;
        throw requestError;
    }

    return response.json();
}
