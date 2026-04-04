/**
 * Nemesis Framework — Application JS Entry Point
 * Phase 10 | Added: 2026-04-03
 * Updated: 2026-04-03 — Added generic helper functions extracted from project app.js
 *
 * Helpers are framework-agnostic (no Vue/Bootstrap-Vue dependency).
 * Override toast notifications by defining window.NemesisNotify before this file loads:
 *
 *   window.NemesisNotify = (message, type) => { /* your toast implementation *\/ };
 *
 * Available types: 'error' | 'warning' | 'info' | 'success'
 */

// ─── CSRF / Axios Bootstrap ─────────────────────────────────────────────────

const token = document.querySelector('meta[name="csrf-token"]');
if (token) {
    window.csrfToken = token.getAttribute('content');

    if (typeof window.axios !== 'undefined') {
        window.axios.defaults.headers.common['X-CSRF-TOKEN'] = window.csrfToken;
        window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
    }
}

// Reload page on browser back-button restore (prevents stale form state)
window.addEventListener('pageshow', function (event) {
    const historyTraversal =
        event.persisted ||
        (typeof window.performance !== 'undefined' &&
            window.performance.navigation.type === 2);
    if (historyTraversal) {
        window.location.reload();
    }
});

// ─── Notification helper ─────────────────────────────────────────────────────

/**
 * Show a notification. Override window.NemesisNotify(message, type) to plug in
 * your own toast library (Bootstrap, SweetAlert2, Toastr, etc.).
 *
 * @param {string} message
 * @param {'error'|'warning'|'info'|'success'} type
 */
function _notify(message, type = 'error') {
    if (typeof window.NemesisNotify === 'function') {
        window.NemesisNotify(message, type);
    } else {
        console[type === 'error' ? 'error' : type === 'warning' ? 'warn' : 'log'](
            '[Nemesis ' + type + '] ' + message
        );
    }
}

// ─── Network ─────────────────────────────────────────────────────────────────

/**
 * Check if the browser has network connectivity.
 * Shows an error notification and returns false when offline.
 *
 * @returns {boolean}
 */
function checkNet() {
    if (window.navigator.onLine) return true;
    _notify('You are offline.', 'error');
    return false;
}

// ─── File Upload Validation ───────────────────────────────────────────────────

/**
 * Validate a file input against an extension allowlist and size limits.
 * Resets the input element on failure and shows a notification.
 *
 * Security checks applied (client-side; always validate server-side too):
 *   1. Path traversal in filename
 *   2. Leading / trailing dots
 *   3. Exactly one dot (prevents double extensions like .php.jpg)
 *   4. Extension allowlist
 *   5. Per-file size cap (separate cap for .zip)
 *   6. Max file count per selection
 *
 * @param {Event|HTMLInputElement} eventOrInput  - change event or input element
 * @param {string[]} allowedFileTypes            - e.g. ['.pdf', '.jpg', '.zip']
 * @param {object}  [opts]
 * @param {number}  [opts.maxFiles=10]           - max files per selection
 * @param {number}  [opts.maxFileSizeMB=50]      - per-file size cap in MB
 * @param {number}  [opts.zipMaxMB=50]           - separate cap for .zip files
 * @returns {boolean}
 */
function checkFileType(eventOrInput, allowedFileTypes, opts = {}) {
    const {
        maxFiles    = 10,
        maxFileSizeMB = 50,
        zipMaxMB    = 50,
    } = opts;

    const input     = eventOrInput?.target ?? eventOrInput;
    const files     = Array.from(input?.files || []);
    const allowList = (allowedFileTypes || []).map(t => t.toLowerCase());

    const reset = () => {
        if (input) input.value = '';
    };

    if (files.length > maxFiles) {
        reset();
        _notify('Too many files. Max allowed: ' + maxFiles + '.', 'error');
        return false;
    }

    for (const file of files) {
        const rawName  = file?.name || '';
        const baseName = rawName.split(/[/\\]/).pop();

        // 1) Path traversal
        const forbidden = ['../', '..\\', '/..', '\\..', '/', '\\'];
        if (forbidden.some(s => rawName.includes(s))) {
            reset();
            console.log('checkFileType blocked path traversal:', rawName);
            _notify('Invalid filename: path segments are not allowed.', 'error');
            return false;
        }

        // 2) Leading / trailing dot
        if (baseName.startsWith('.') || baseName.endsWith('.')) {
            reset();
            console.log('checkFileType blocked leading/trailing dot:', baseName);
            _notify('Invalid filename: no leading or trailing dots allowed.', 'error');
            return false;
        }

        // 3) Exactly one dot (prevents double extensions)
        const dotCount = (baseName.match(/\./g) || []).length;
        if (dotCount !== 1) {
            reset();
            console.log('checkFileType blocked multiple extensions:', baseName, 'dots:', dotCount);
            _notify('Invalid filename: use exactly one dot before the extension (e.g., "document.pdf").', 'error');
            return false;
        }

        // 4) Extension allowlist
        const ext = '.' + baseName.split('.').pop().toLowerCase();
        if (!allowList.includes(ext)) {
            reset();
            console.log('checkFileType blocked extension:', ext, 'allowed:', allowList);
            _notify('Invalid file type "' + ext + '". Allowed: ' + allowList.join(', '), 'error');
            return false;
        }

        // 5) Size guards (front-end only; zip-bomb detection is server-side)
        const sizeMB = file.size / (1024 * 1024);
        if (ext === '.zip') {
            if (sizeMB > zipMaxMB) {
                reset();
                console.log('checkFileType blocked zip size:', sizeMB.toFixed(2), 'MB, max:', zipMaxMB);
                _notify('ZIP too large (' + sizeMB.toFixed(2) + ' MB). Max allowed: ' + zipMaxMB + ' MB.', 'error');
                return false;
            }
        } else {
            if (sizeMB > maxFileSizeMB) {
                reset();
                console.log('checkFileType blocked file size:', sizeMB.toFixed(2), 'MB, max:', maxFileSizeMB);
                _notify('File too large (' + sizeMB.toFixed(2) + ' MB). Max allowed: ' + maxFileSizeMB + ' MB.', 'error');
                return false;
            }
        }
    }

    return true;
}

// ─── File Download ────────────────────────────────────────────────────────────

/**
 * Trigger a browser file download from a URL.
 * Handles iOS Safari, IE (ActiveX), and modern browsers.
 *
 * @param {string} fileURL
 */
function download_file(fileURL) {
    if (!checkNet()) return;

    const fileName = fileURL.substring(fileURL.lastIndexOf('/') + 1);

    if (!window.ActiveXObject) {
        const save = document.createElement('a');
        save.href     = fileURL;
        save.target   = '_blank';
        save.download = fileName;

        if (
            navigator.userAgent.toLowerCase().match(/(ipad|iphone|safari)/) &&
            navigator.userAgent.search('Chrome') < 0
        ) {
            document.location = save.href;
        } else {
            const evt = new MouseEvent('click', { view: window, bubbles: true, cancelable: false });
            save.dispatchEvent(evt);
            (window.URL || window.webkitURL).revokeObjectURL(save.href);
        }
    } else if (!!window.ActiveXObject && document.execCommand) {
        const _window = window.open(fileURL, '_blank');
        _window.document.close();
        _window.document.execCommand('SaveAs', true, fileName || fileURL);
        _window.close();
    }
}

/**
 * Trigger a browser download from an Axios blob response.
 * Reads the filename from the Content-Disposition header (supports filename* UTF-8 encoding).
 *
 * @param {object} response  - Axios response with responseType: 'blob'
 */
function global_download_file_content(response) {
    const contentDisposition = response.headers['content-disposition'];
    let fileName = 'download';

    if (contentDisposition) {
        // Handles: filename="foo.zip", filename=foo.zip, filename*=UTF-8''foo.zip
        const match = contentDisposition.match(
            /filename\*?=(?:UTF-8''|["']?)([^"';\r\n]+)["']?/i
        );
        if (match && match[1]) fileName = decodeURIComponent(match[1].trim());
    }

    const blob = new Blob([response.data], { type: response.headers['content-type'] });
    const link = document.createElement('a');
    link.href     = window.URL.createObjectURL(blob);
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Fetch an image from a URL as a Blob and display it in an <img> element.
 * Useful for serving images through authenticated endpoints (avoids exposing
 * raw URLs, handles CORS, and works with server-proxied image routes).
 *
 * Uses Axios if available, otherwise falls back to the Fetch API.
 * Call revokeObjectURL on the returned URL when the image is no longer needed
 * to free memory (e.g. on component unmount).
 *
 * @param {string}          url        - Image endpoint URL
 * @param {HTMLImageElement|null} [imgEl]  - Optional <img> element to populate
 * @param {object}          [opts]
 * @param {object}          [opts.headers={}]   - Extra request headers
 * @param {string}          [opts.fallbackSrc]  - src to set on the img if fetch fails
 * @returns {Promise<string|null>}  The blob object URL, or null on failure
 *
 * // Updated: 2026-04-03
 */
async function showImageAsBlob(url, imgEl = null, opts = {}) {
    const { headers = {}, fallbackSrc = '' } = opts;

    try {
        let blob;

        if (typeof window.axios !== 'undefined') {
            const response = await window.axios.get(url, {
                responseType: 'blob',
                headers,
            });
            blob = response.data;
        } else {
            const response = await fetch(url, { headers });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            blob = await response.blob();
        }

        // Only accept image MIME types
        if (!blob.type.startsWith('image/')) {
            throw new Error('Response is not an image (got: ' + blob.type + ')');
        }

        const objectURL = (window.URL || window.webkitURL).createObjectURL(blob);

        if (imgEl instanceof HTMLImageElement) {
            imgEl.src = objectURL;
        }

        return objectURL;

    } catch (error) {
        console.error('showImageAsBlob failed for', url, error);
        if (imgEl instanceof HTMLImageElement && fallbackSrc) {
            imgEl.src = fallbackSrc;
        }
        return null;
    }
}

// ─── HTTP Error Handler ───────────────────────────────────────────────────────

/**
 * Handle common Axios/fetch HTTP errors with user-facing notifications.
 * Reloads the page on 401/419 (session expired) after a short delay.
 *
 * @param {object} error  - Axios error object
 * @returns {false|undefined}
 */
function serverError(error) {
    if (!error?.response?.status) return;

    const { status, data } = error.response;

    if (status === 500) {
        _notify("Something went wrong! We'll be back soon!", 'warning');
        return false;
    }

    if (status === 403) {
        _notify('You are not authorized to access this.', 'error');
        return false;
    }

    if (status === 422) {
        _notify(data?.message || 'Validation failed.', 'error');
        return false;
    }

    if (status === 419 || status === 401) {
        _notify('Session expired. The page will reload.', 'warning');
        setTimeout(() => window.location.reload(), 2000);
        return false;
    }

    _notify(data?.message || 'An unexpected error occurred.', 'error');
}

// ─── Date Utilities ───────────────────────────────────────────────────────────

/**
 * Returns true if the given date is in the past.
 *
 * @param {string|Date} d
 * @returns {boolean}
 */
function checkPastDate(d) {
    return new Date() > new Date(d);
}

/**
 * Returns the current financial year as a string, e.g. "2025-2026".
 * Assumes the financial year starts in July (month 7).
 *
 * @returns {string}
 */
function getCurrentFinancialYear() {
    const today = new Date();
    const year  = today.getFullYear();
    return (today.getMonth() + 1) <= 6
        ? (year - 1) + '-' + year
        : year + '-' + (year + 1);
}

/**
 * Generate an array of year option objects from startYear up to the current year.
 *
 * @param {number} [startYear=2019]
 * @returns {{ value: number, text: number }[]}
 */
function generateYearOptions(startYear = 2019) {
    const currentYear = new Date().getFullYear();
    return Array.from(
        { length: currentYear - startYear + 1 },
        (_, i) => ({ value: startYear + i, text: startYear + i })
    );
}

/**
 * Generate an array of month option objects (01–12 with full month names).
 *
 * @returns {{ value: string, text: string }[]}
 */
function generateMonthOptions() {
    const names = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December',
    ];
    return names.map((text, i) => ({
        value: ('0' + (i + 1)).slice(-2),
        text,
    }));
}

// ─── Array Utilities ──────────────────────────────────────────────────────────

/**
 * Find the index of the first element in an array where element[key] === value.
 *
 * @param {object[]} array
 * @param {string}   key
 * @param {*}        value
 * @returns {number}  -1 if not found
 */
function getIndex(array, key, value) {
    return array.findIndex(i => i[key] == value);
}

// ─── Money / Number Formatting ────────────────────────────────────────────────

/**
 * Strip commas and parse a value to a fixed-2 decimal number.
 * Returns the original value if it is already "0" or NaN.
 *
 * @param {string|number} amount
 * @returns {string|number}
 */
function toMoney(amount) {
    if (amount !== '0' && amount !== 0) {
        if (!isNaN(amount)) return Number(amount);
        const stripped = String(amount).replace(/,/g, '');
        return Number(stripped).toFixed(2);
    }
    return amount;
}

/**
 * Format a number as money with commas and exactly 2 decimal places.
 * Negative values are shown as (1,234.56) when show_neg is truthy.
 *
 * @param {string|number} amount
 * @param {1|0}           [show_neg=1]  - 1 = wrap negatives in parentheses
 * @returns {string}
 */
function moneyFormat(amount, show_neg = 1) {
    if (amount == null || isNaN(parseFloat(amount))) return '0.00';

    let neg = false;
    let val = parseFloat(amount);

    if (show_neg && val < 0) {
        neg = true;
        val = Math.abs(val);
    }

    let formatted = val.toFixed(2);
    formatted = parseFloat(formatted).toLocaleString();

    if (formatted.indexOf('.') > 0) {
        if (formatted.length - formatted.lastIndexOf('.') === 2) formatted += '0';
    } else {
        formatted += '.00';
    }

    return neg ? '(' + formatted + ')' : formatted;
}

/**
 * Format a number as locale-aware money with 2 decimal places.
 * Strips existing commas before formatting.
 *
 * @param {string|number} amount
 * @returns {string}
 */
function formatMoney(amount) {
    const num = Number(String(amount).replace(/,/g, ''));
    if (isNaN(num)) return String(amount);
    return num.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

// ─── String Utilities ─────────────────────────────────────────────────────────

/**
 * Replace every dot in a string with an underscore.
 * Returns an empty string for falsy input.
 *
 * @param {string} value
 * @returns {string}
 */
function replaceDotWithUnderscore(value) {
    return value ? value.replaceAll('.', '_') : '';
}

// ─── Module Component Auto-Registration ──────────────────────────────────────
//
// Convention:  app/Modules/{ModuleName}/Components/
// Name scheme: {ModuleName}{ComponentName}
//   e.g.  Modules/User/Components/UserCard.vue   → "UserUserCard"
//         Modules/Cart/Components/CartItem.jsx    → "CartCartItem"
//
// Extension → Framework:
//   .vue          → Vue 2 (Vue.component) / Vue 3 (app.component)
//   .jsx / .tsx   → React  → window.NemesisReactComponents registry
//   .alpine.js    → Alpine.js (Alpine.data)
//   .ghost.js     → Ghost.js  (Ghost.component)
//
// Filtering (when a module has BOTH .vue and .jsx for the same component):
//   registerModuleComponents({ Vue, frameworks: ['vue'] })  ← Vue only
//   registerModuleComponents({ frameworks: ['react'] })     ← React only
//   registerModuleComponents({ Vue, Alpine, Ghost })        ← all detected
//
// Bundler support:
//   Vite   → pass globs from scanModuleGlobs() (static import.meta.glob calls)
//   Webpack → require.context used automatically when globs is omitted
//
// Updated: 2026-04-03

/**
 * [Vite only] Pre-scan all module component files via import.meta.glob.
 *
 * Must be called inside a Vite-processed ES module so Vite can resolve
 * the static glob literals at build time.
 *
 * Usage:
 *   import { registerModuleComponents, scanModuleGlobs } from './app.js';
 *   registerModuleComponents({ Vue: app, globs: scanModuleGlobs() });
 *
 * @returns {{ vue: object, react: object, alpine: object, ghost: object }}
 *          Each value is an { [filePath]: moduleOrFactory } map.
 */
function scanModuleGlobs() {
    // String literals are REQUIRED here — Vite resolves them at build time.
    // Do not replace with variables or template literals.
    return {
        vue:    import.meta.glob('../../app/Modules/*/Components/*.vue',       { eager: true }),
        react:  import.meta.glob('../../app/Modules/*/Components/*.{jsx,tsx}', { eager: true }),
        alpine: import.meta.glob('../../app/Modules/*/Components/*.alpine.js', { eager: true }),
        ghost:  import.meta.glob('../../app/Modules/*/Components/*.ghost.js',  { eager: true }),
    };
}

/**
 * Auto-register components from every Nemesis module for one or more
 * JS frameworks.
 *
 * Supports Vite (via globs from scanModuleGlobs) and Webpack (require.context).
 * Falls back gracefully if a framework instance is not provided.
 *
 * @param {object}    [opts]
 * @param {string[]}  [opts.frameworks]    Which frameworks to register.
 *                                         Default: all for which an instance is found.
 *                                         Values: 'vue' | 'react' | 'alpine' | 'ghost'
 * @param {object}    [opts.Vue]           Vue 2 global  OR  Vue 3 app instance.
 *                                         Falls back to window.Vue.
 * @param {object}    [opts.Alpine]        Alpine.js instance.
 *                                         Falls back to window.Alpine.
 * @param {object}    [opts.Ghost]         Ghost.js instance.
 *                                         Falls back to window.Ghost.
 * @param {object}    [opts.globs]         Pre-scanned glob maps from scanModuleGlobs().
 *                                         Required for Vite; omit for Webpack.
 * @param {Function}  [opts.onRegister]    Called after each registration:
 *                                         (framework, componentName, component) => void
 * @returns {{ vue: string[], react: string[], alpine: string[], ghost: string[] }}
 *          Lists of registered component names per framework.
 */
function registerModuleComponents(opts = {}) {
    const {
        frameworks,
        Vue:    VueArg,
        Alpine: AlpineArg,
        Ghost:  GhostArg,
        globs,
        onRegister,
    } = opts;

    const VueInst    = VueArg    ?? window.Vue;
    const AlpineInst = AlpineArg ?? window.Alpine;
    const GhostInst  = GhostArg  ?? window.Ghost;

    // Register this framework? True if frameworks list is omitted OR includes it.
    const only = (fw) => !frameworks || frameworks.includes(fw);

    const registry = { vue: [], react: [], alpine: [], ghost: [] };

    /**
     * Derive the component registration name from a file path.
     *
     * Path:  ../../app/Modules/User/Components/UserCard.vue
     * Name:  "UserUserCard"
     *
     * Strips: .alpine.js, .ghost.js, .vue, .jsx, .tsx, .js extensions.
     */
    function toName(filePath) {
        const parts    = filePath.replace(/\\/g, '/').split('/');
        const fileName = parts[parts.length - 1];
        const compIdx  = parts.lastIndexOf('Components');
        const modName  = compIdx > 0 ? parts[compIdx - 1] : 'App';
        const baseName = fileName
            .replace(/\.(alpine|ghost)\.js$/, '')
            .replace(/\.(vue|jsx|tsx|js)$/, '');
        return `${modName}${baseName}`;
    }

    // ── Vue registration ──────────────────────────────────────────────────────
    function registerVue(filePath, mod) {
        if (!only('vue') || !VueInst) return;
        const comp = mod?.default ?? mod;
        const name = toName(filePath);
        // Works for both Vue 2 (Vue.component) and Vue 3 (app.component)
        if (typeof VueInst.component === 'function') {
            VueInst.component(name, comp);
        }
        registry.vue.push(name);
        onRegister?.('vue', name, comp);
    }

    // ── React registration ────────────────────────────────────────────────────
    // React has no global registry; components are collected for use with
    // dynamic rendering (e.g. <NemesisComponent name="UserUserCard" {...props} />)
    function registerReact(filePath, mod) {
        if (!only('react')) return;
        const comp = mod?.default ?? mod;
        const name = toName(filePath);
        window.NemesisReactComponents ??= {};
        window.NemesisReactComponents[name] = comp;
        registry.react.push(name);
        onRegister?.('react', name, comp);
    }

    // ── Alpine registration ───────────────────────────────────────────────────
    // Alpine.data(name, factory) — name must be camelCase, no dashes
    function registerAlpine(filePath, mod) {
        if (!only('alpine') || !AlpineInst) return;
        const comp = mod?.default ?? mod;
        const name = toName(filePath);
        // Alpine data names are camelCase: "UserUserCard" → "userUserCard"
        const alpineName = name.charAt(0).toLowerCase() + name.slice(1);
        if (typeof AlpineInst.data === 'function') {
            AlpineInst.data(alpineName, comp);
        }
        registry.alpine.push(name);
        onRegister?.('alpine', name, comp);
    }

    // ── Ghost.js registration ─────────────────────────────────────────────────
    // Ghost.js is the owner's custom framework at D:\Project Ghost.js
    // Assumes Ghost.component(name, definition) registration API
    function registerGhost(filePath, mod) {
        if (!only('ghost') || !GhostInst) return;
        const comp = mod?.default ?? mod;
        const name = toName(filePath);
        if (typeof GhostInst.component === 'function') {
            GhostInst.component(name, comp);
        }
        registry.ghost.push(name);
        onRegister?.('ghost', name, comp);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // VITE path — caller passes pre-scanned globs from scanModuleGlobs()
    // ─────────────────────────────────────────────────────────────────────────
    if (globs) {
        const iterGlob = (map, handler) => {
            if (!map) return;
            Object.entries(map).forEach(([fp, mod]) => handler(fp, mod));
        };

        iterGlob(globs.vue,    registerVue);
        iterGlob(globs.react,  registerReact);
        iterGlob(globs.alpine, registerAlpine);
        iterGlob(globs.ghost,  registerGhost);

        return registry;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // WEBPACK path — require.context scanned automatically
    // ─────────────────────────────────────────────────────────────────────────
    if (typeof require !== 'undefined' && typeof require.context === 'function') {
        /**
         * Scan a directory recursively for files matching pattern and invoke handler.
         * Swallows errors if the Modules directory does not exist.
         */
        const scan = (pattern, handler) => {
            try {
                const ctx = require.context('../../app/Modules', true, pattern);
                ctx.keys().forEach((fp) => handler(fp, ctx(fp)));
            } catch (_) { /* Modules dir may not exist in all setups */ }
        };

        scan(/\/Components\/[^/]+\.vue$/,          registerVue);
        scan(/\/Components\/[^/]+\.(jsx|tsx)$/,    registerReact);
        scan(/\/Components\/[^/]+\.alpine\.js$/,   registerAlpine);
        scan(/\/Components\/[^/]+\.ghost\.js$/,    registerGhost);
    }

    return registry;
}

// ─── Export / Global Registration ────────────────────────────────────────────

/**
 * All helpers are available as named ES module exports for bundled apps,
 * and also attached to window.Nemesis for use in plain <script> tags.
 */
const NemesisHelpers = {
    checkNet,
    checkFileType,
    download_file,
    global_download_file_content,
    showImageAsBlob,
    serverError,
    checkPastDate,
    getCurrentFinancialYear,
    generateYearOptions,
    generateMonthOptions,
    getIndex,
    toMoney,
    moneyFormat,
    formatMoney,
    replaceDotWithUnderscore,
    scanModuleGlobs,
    registerModuleComponents,
};

window.Nemesis = Object.assign(window.Nemesis || {}, NemesisHelpers);

export {
    checkNet,
    checkFileType,
    download_file,
    global_download_file_content,
    showImageAsBlob,
    serverError,
    checkPastDate,
    getCurrentFinancialYear,
    generateYearOptions,
    generateMonthOptions,
    getIndex,
    toMoney,
    moneyFormat,
    formatMoney,
    replaceDotWithUnderscore,
    scanModuleGlobs,
    registerModuleComponents,
};

export default NemesisHelpers;
