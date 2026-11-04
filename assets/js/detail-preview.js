/**
 * Vista previa de archivos PDF en la página de detalle.
 *
 * Sin JavaScript, el botón "Vista previa" abre el PDF en una pestaña nueva.
 * Con JavaScript, el PDF se muestra dentro de la página, debajo de la lista
 * de archivos, y recién se descarga cuando la persona lo pide.
 */
(function () {
    'use strict';

    function isSafeUrl(url) {
        try {
            var parsed = new URL(url, window.location.href);
            return parsed.protocol === 'https:' || parsed.protocol === 'http:';
        } catch (e) {
            return false;
        }
    }

    function makeButton(tag, text) {
        var el = document.createElement(tag);
        el.className = 'dges-btn-secondary';
        el.textContent = text;
        return el;
    }

    function init(root) {
        // Toda la fila de un documento relacionado tiene que navegar, incluso
        // si el click cae en el borde o en un adorno de la fila.
        root.addEventListener('click', function (e) {
            var related = e.target.closest('.dges-related-link');

            if (related) {
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || 0 !== e.button) {
                    return;
                }

                e.preventDefault();
                window.location.href = related.href;
                return;
            }

            var item = e.target.closest('.dges-related-item');

            if (!item) {
                return;
            }

            var link = item.querySelector('.dges-related-link');

            if (link) {
                window.location.href = link.href;
            }
        });

        var viewer = root.querySelector('[data-dges-viewer]');
        var current = null;

        if (!viewer) {
            return;
        }

        function hide() {
            var trigger = current;

            viewer.hidden = true;
            // Al vaciar el visor, el navegador deja de cargar el PDF.
            viewer.innerHTML = '';

            if (trigger) {
                trigger.classList.remove('is-active');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            }

            current = null;
        }

        function show(trigger, url) {
            var title = trigger.getAttribute('data-dges-preview-title') || 'Documento';

            if (current) {
                current.classList.remove('is-active');
                current.setAttribute('aria-expanded', 'false');
            }

            viewer.innerHTML = '';

            var header = document.createElement('div');
            header.className = 'dges-viewer-header';

            var heading = document.createElement('h3');
            heading.className = 'dges-viewer-title';
            heading.textContent = title;

            var actions = document.createElement('div');
            actions.className = 'dges-viewer-actions';

            var open = makeButton('a', 'Abrir en pestaña nueva');
            open.href = url;
            open.target = '_blank';
            open.rel = 'noopener noreferrer';

            var close = makeButton('button', 'Cerrar vista previa');
            close.type = 'button';
            close.addEventListener('click', hide);

            actions.appendChild(open);
            actions.appendChild(close);
            header.appendChild(heading);
            header.appendChild(actions);

            var frame = document.createElement('iframe');
            frame.className = 'dges-viewer-frame';
            frame.src = url;
            frame.title = 'Vista previa: ' + title;

            var hint = document.createElement('p');
            hint.className = 'dges-viewer-hint';
            hint.textContent = '¿No se ve el documento? Usá «Abrir en pestaña nueva».';

            viewer.appendChild(header);
            viewer.appendChild(frame);
            viewer.appendChild(hint);
            viewer.hidden = false;

            trigger.classList.add('is-active');
            trigger.setAttribute('aria-expanded', 'true');
            current = trigger;

            viewer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        root.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-dges-preview]');

            if (!trigger || !root.contains(trigger)) {
                return;
            }

            // Sin visor de PDF integrado (por ejemplo Chrome en Android),
            // se deja que el enlace abra el archivo en una pestaña nueva.
            if (navigator.pdfViewerEnabled === false) {
                return;
            }

            var url = trigger.getAttribute('data-dges-preview');

            if (!isSafeUrl(url)) {
                return;
            }

            e.preventDefault();

            if (current === trigger) {
                hide();
            } else {
                show(trigger, url);
            }
        });
    }

    function start() {
        var roots = document.querySelectorAll('.dges-documento-detalle');

        for (var i = 0; i < roots.length; i++) {
            init(roots[i]);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
