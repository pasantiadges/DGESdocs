export function debounce(fn, wait) {
    let timer = null;

    const debounced = function (...args) {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function () {
            timer = null;
            fn.apply(this, args);
        }, wait);
    };

    debounced.cancel = function () {
        if (timer) clearTimeout(timer);
        timer = null;
    };

    debounced.pending = function () {
        return timer !== null;
    };

    return debounced;
}

/**
 * Reemplaza %s y %1$s en los textos traducidos de WordPress.
 */
export function sprintf(template, ...args) {
    let sequential = 0;

    return String(template || '').replace(/%(\d+\$)?s/g, function (match, position) {
        if (position) {
            return args[parseInt(position, 10) - 1] ?? '';
        }
        return args[sequential++] ?? '';
    });
}
