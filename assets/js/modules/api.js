export async function fetchDocuments(state) {
    const cfg = window.DGESDocumentos || {};
    const params = new URLSearchParams();
    params.set('page', state.page);
    params.set('per_page', state.perPage);

    Object.entries(state.filters).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) params.set(k, v);
    });

    const sep = cfg.endpoint.includes('?') ? '&' : '?';
    const url = cfg.endpoint + sep + params.toString();

    const res = await fetch(url, { headers: { 'X-WP-Nonce': cfg.nonce } });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}