/**
 * Carrusel de documentos destacados.
 *
 * Sin JavaScript las tarjetas igual se pueden deslizar con el dedo o la
 * barra de desplazamiento. Con JavaScript se suman las flechas, los puntos
 * y el manejo con el teclado.
 */
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function init(root) {
        var track = root.querySelector('[data-carousel-track]');
        var prev = root.querySelector('[data-carousel-prev]');
        var next = root.querySelector('[data-carousel-next]');
        var dotsWrap = root.querySelector('[data-carousel-dots]');

        if (!track) {
            return;
        }

        var slides = Array.prototype.slice.call(track.children);
        var dots = [];
        var ticking = false;

        if (slides.length < 2) {
            return;
        }

        function behavior() {
            return reduceMotion ? 'auto' : 'smooth';
        }

        function slideStep() {
            var gap = parseFloat(window.getComputedStyle(track).columnGap) || 0;
            return slides[0].getBoundingClientRect().width + gap;
        }

        function currentIndex() {
            return Math.round(track.scrollLeft / slideStep());
        }

        function goTo(index) {
            var max = slides.length - 1;
            index = Math.max(0, Math.min(max, index));
            track.scrollTo({ left: index * slideStep(), behavior: behavior() });
        }

        function update() {
            var index = currentIndex();
            var maxScroll = track.scrollWidth - track.clientWidth;
            // Margen de 2px por redondeos del navegador.
            var atStart = track.scrollLeft <= 2;
            var atEnd = track.scrollLeft >= maxScroll - 2;

            if (prev) prev.disabled = atStart;
            if (next) next.disabled = atEnd;

            // Si todas las tarjetas entran en pantalla, no hacen falta controles.
            root.classList.toggle('is-static', maxScroll <= 2);

            dots.forEach(function (dot, i) {
                var active = atEnd ? i === dots.length - 1 : i === index;
                dot.classList.toggle('is-active', active);
                dot.setAttribute('aria-current', active ? 'true' : 'false');
            });
        }

        if (dotsWrap) {
            slides.forEach(function (slide, i) {
                var dot = document.createElement('button');
                dot.type = 'button';
                dot.className = 'dges-carousel-dot';
                dot.setAttribute('aria-label', 'Ir al destacado ' + (i + 1) + ' de ' + slides.length);
                dot.addEventListener('click', function () {
                    goTo(i);
                });
                dotsWrap.appendChild(dot);
                dots.push(dot);
            });
        }

        if (prev) {
            prev.addEventListener('click', function () {
                goTo(currentIndex() - 1);
            });
        }

        if (next) {
            next.addEventListener('click', function () {
                goTo(currentIndex() + 1);
            });
        }

        track.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                goTo(currentIndex() - 1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                goTo(currentIndex() + 1);
            }
        });

        track.addEventListener('scroll', function () {
            if (ticking) {
                return;
            }
            ticking = true;
            window.requestAnimationFrame(function () {
                update();
                ticking = false;
            });
        }, { passive: true });

        window.addEventListener('resize', update);

        root.classList.add('is-ready');
        update();
    }

    function start() {
        var roots = document.querySelectorAll('[data-dges-carousel]');

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
