import { escapeHtml, label } from './utils.js';

export function renderFilters(container, facets, state) {
    container.innerHTML =
        '<label>Categoría <select data-category><option value="">Todas</option>' +
            (facets.categories || []).map(function (i) {
                return '<option value="' + escapeHtml(i) + '"' +
                    (state.filters.categoria === i ? ' selected' : '') + '>' +
                    escapeHtml(i) + '</option>';
            }).join('') + '</select></label>' +
        '<label>Año <select data-year><option value="">Todos</option>' +
            (facets.years || []).map(function (i) {
                return '<option value="' + i + '"' +
                    (String(state.filters.anio) === String(i) ? ' selected' : '') + '>' +
                    i + '</option>';
            }).join('') + '</select></label>' +
        '<label>Número <select data-number><option value="">Todos</option>' +
            (facets.numbers || []).map(function (i) {
                return '<option value="' + escapeHtml(i) + '"' +
                    (state.filters.number === i ? ' selected' : '') + '>' +
                    escapeHtml(i) + '</option>';
            }).join('') + '</select></label>' +
        '<button type="button" data-reset>Limpiar</button>';

    // Sincronizamos solo los selectores que quedaron
    if (container.querySelector('[data-category]')) container.querySelector('[data-category]').value = state.filters.categoria;
    if (container.querySelector('[data-year]')) container.querySelector('[data-year]').value = state.filters.anio;
    if (container.querySelector('[data-number]')) container.querySelector('[data-number]').value = state.filters.number;
}

// El resto del archivo (renderResults, cardTemplate, setStatus, updateLoadMore) se mantiene igual.
export function renderResults(container, items) {
    container.innerHTML = items.length
        ? items.map(cardTemplate).join('')
        : '<p>No se encontraron documentos con esos filtros.</p>';
}

function cardTemplate(item) {
    return '<article class="dges-document-card">' +
        '<div class="dges-card-header">' +
            '<span class="dges-document-type">' + escapeHtml(label(item.type)) + '</span>' +
            (item.destacado ? '<span class="dges-badge-destacado">★ Destacado</span>' : '') +
        '</div>' +
        '<h3>' + escapeHtml(item.title) + '</h3>' +
        (item.description ? '<p>' + escapeHtml(item.description) + '</p>' : '') +
        '<div class="dges-card-meta">' +
            (item.category ? '<span>' + escapeHtml(item.category) + '</span>' : '') +
            (item.year ? '<span>Año ' + escapeHtml(item.year) + '</span>' : '') +
        '</div>' +
        ((item.related || []).length
            ? '<details><summary>Documentos relacionados (' + item.related.length + ')</summary><ul>' +
              item.related.map(function (r) {
                  return '<li><a href="' + escapeHtml(r.url) + '" target="' +
                      escapeHtml(r.target || '_self') + '" rel="noopener">' +
                      escapeHtml(r.nombre) + '</a></li>';
              }).join('') + '</ul></details>'
            : '') +
        (item.url && item.url !== '#'
            ? '<a href="' + escapeHtml(item.url) + '" target="' + escapeHtml(item.target || '_self') + '" rel="noopener" class="dges-btn-open">Descargar ' + escapeHtml(item.type) + '</a>'
            : '') +
        '</article>';
}

export function setStatus(el, text, hidden) {
    if (!el) return;
    el.textContent = text || '';
    el.style.display = hidden ? 'none' : '';
}

export function updateLoadMore(btn, hasMore, loading, i18n) {
    if (!btn) return;
    btn.hidden = !hasMore;
    btn.disabled = !!loading;
    btn.textContent = loading
        ? (i18n.loadingMore || 'Cargando más…')
        : (i18n.loadMore || 'Ver más');
}