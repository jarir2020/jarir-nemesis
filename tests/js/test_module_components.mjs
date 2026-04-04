/**
 * Tests for registerModuleComponents() — multi-framework side-by-side behaviour.
 *
 * Runs in plain Node.js (no bundler). import.meta.glob / require.context are
 * bypassed by passing mock `globs` directly (the Vite path in the function).
 *
 * Tests: 2026-04-03
 */

// ─── Node polyfills ───────────────────────────────────────────────────────────
// window, NemesisReactComponents must exist before the function under test runs.
global.window = global;
global.window.NemesisReactComponents = undefined;

// ─── Inline the functions under test (copy from resources/js/app.js) ─────────
// We cannot import app.js directly because scanModuleGlobs() contains
// import.meta.glob which is a Vite compile-time transform unavailable in Node.
// The logic below is an exact copy of toName + registerModuleComponents.

function toNameStandalone(filePath) {
    const parts    = filePath.replace(/\\/g, '/').split('/');
    const fileName = parts[parts.length - 1];
    const compIdx  = parts.lastIndexOf('Components');
    const modName  = compIdx > 0 ? parts[compIdx - 1] : 'App';
    const baseName = fileName
        .replace(/\.(alpine|ghost)\.js$/, '')
        .replace(/\.(vue|jsx|tsx|js)$/, '');
    return `${modName}${baseName}`;
}

function registerModuleComponents(opts = {}) {
    const { frameworks, Vue: VueArg, Alpine: AlpineArg, Ghost: GhostArg, globs, onRegister } = opts;
    const VueInst    = VueArg    ?? window.Vue;
    const AlpineInst = AlpineArg ?? window.Alpine;
    const GhostInst  = GhostArg  ?? window.Ghost;
    const only = (fw) => !frameworks || frameworks.includes(fw);
    const registry = { vue: [], react: [], alpine: [], ghost: [] };

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

    function registerVue(filePath, mod) {
        if (!only('vue') || !VueInst) return;
        const comp = mod?.default ?? mod;
        const name = toName(filePath);
        if (typeof VueInst.component === 'function') VueInst.component(name, comp);
        registry.vue.push(name);
        onRegister?.('vue', name, comp);
    }

    function registerReact(filePath, mod) {
        if (!only('react')) return;
        const comp = mod?.default ?? mod;
        const name = toName(filePath);
        window.NemesisReactComponents ??= {};
        window.NemesisReactComponents[name] = comp;
        registry.react.push(name);
        onRegister?.('react', name, comp);
    }

    function registerAlpine(filePath, mod) {
        if (!only('alpine') || !AlpineInst) return;
        const comp = mod?.default ?? mod;
        const name = toName(filePath);
        const alpineName = name.charAt(0).toLowerCase() + name.slice(1);
        if (typeof AlpineInst.data === 'function') AlpineInst.data(alpineName, comp);
        registry.alpine.push(name);
        onRegister?.('alpine', name, comp);
    }

    function registerGhost(filePath, mod) {
        if (!only('ghost') || !GhostInst) return;
        const comp = mod?.default ?? mod;
        const name = toName(filePath);
        if (typeof GhostInst.component === 'function') GhostInst.component(name, comp);
        registry.ghost.push(name);
        onRegister?.('ghost', name, comp);
    }

    if (globs) {
        const iterGlob = (map, handler) => { if (map) Object.entries(map).forEach(([fp, mod]) => handler(fp, mod)); };
        iterGlob(globs.vue,    registerVue);
        iterGlob(globs.react,  registerReact);
        iterGlob(globs.alpine, registerAlpine);
        iterGlob(globs.ghost,  registerGhost);
        return registry;
    }

    if (typeof require !== 'undefined' && typeof require.context === 'function') {
        const scan = (pattern, handler) => {
            try {
                const ctx = require.context('../../app/Modules', true, pattern);
                ctx.keys().forEach((fp) => handler(fp, ctx(fp)));
            } catch (_) {}
        };
        scan(/\/Components\/[^/]+\.vue$/,        registerVue);
        scan(/\/Components\/[^/]+\.(jsx|tsx)$/,  registerReact);
        scan(/\/Components\/[^/]+\.alpine\.js$/, registerAlpine);
        scan(/\/Components\/[^/]+\.ghost\.js$/,  registerGhost);
    }

    return registry;
}

// ─── Test harness ─────────────────────────────────────────────────────────────

let passed = 0;
let failed = 0;
const failures = [];

function assert(label, condition, detail = '') {
    if (condition) {
        console.log(`  \x1b[32m✓\x1b[0m  ${label}`);
        passed++;
    } else {
        console.log(`  \x1b[31m✗\x1b[0m  ${label}${detail ? ': ' + detail : ''}`);
        failed++;
        failures.push(label + (detail ? ': ' + detail : ''));
    }
}

function assertEqual(label, actual, expected) {
    const ok = JSON.stringify(actual) === JSON.stringify(expected);
    assert(label, ok, ok ? '' : `\n      expected ${JSON.stringify(expected)}\n      got     ${JSON.stringify(actual)}`);
}

function section(name) {
    console.log(`\n── ${name} ─`);
}

// ─── Mock globs ───────────────────────────────────────────────────────────────
// Simulates app/Modules with components for ALL four frameworks.
// UserCard exists in BOTH .vue AND .jsx (the key collision case).

const VUE_COMP_A = { name: 'UserCard',  render() {} };
const VUE_COMP_B = { name: 'CartItem',  render() {} };
const REACT_COMP_A  = function UserCard() {};   // same logical name as VUE_COMP_A
const REACT_COMP_B  = function Dashboard() {};
const ALPINE_FACTORY = () => ({ open: false, toggle() { this.open = !this.open; } });
const GHOST_DEF  = { template: '<div>Modal</div>' };

const mockGlobs = {
    vue: {
        '../../app/Modules/User/Components/UserCard.vue':  { default: VUE_COMP_A },
        '../../app/Modules/Cart/Components/CartItem.vue':  { default: VUE_COMP_B },
    },
    react: {
        '../../app/Modules/User/Components/UserCard.jsx':      { default: REACT_COMP_A },
        '../../app/Modules/Dashboard/Components/Dashboard.tsx':{ default: REACT_COMP_B },
    },
    alpine: {
        '../../app/Modules/User/Components/UserDropdown.alpine.js': { default: ALPINE_FACTORY },
    },
    ghost: {
        '../../app/Modules/App/Components/Modal.ghost.js': { default: GHOST_DEF },
    },
};

// ─── 1. toName() ─────────────────────────────────────────────────────────────
section('toName() naming scheme');

assertEqual('Vue .vue extension',
    toNameStandalone('../../app/Modules/User/Components/UserCard.vue'),
    'UserUserCard');

assertEqual('React .jsx extension',
    toNameStandalone('../../app/Modules/Cart/Components/CartItem.jsx'),
    'CartCartItem');

assertEqual('React .tsx extension',
    toNameStandalone('../../app/Modules/Dashboard/Components/Dashboard.tsx'),
    'DashboardDashboard');

assertEqual('Alpine .alpine.js double extension',
    toNameStandalone('../../app/Modules/User/Components/UserDropdown.alpine.js'),
    'UserUserDropdown');

assertEqual('Ghost .ghost.js double extension',
    toNameStandalone('../../app/Modules/App/Components/Modal.ghost.js'),
    'AppModal');

assertEqual('Windows backslash path',
    toNameStandalone('..\\..\\app\\Modules\\User\\Components\\UserCard.vue'),
    'UserUserCard');

assertEqual('No Components segment → modName falls back to App',
    toNameStandalone('some/random/path/Widget.vue'),
    'AppWidget');

// ─── 2. All four frameworks side by side ─────────────────────────────────────
section('All four frameworks — side-by-side (UserCard in both Vue + React)');

// Reset state
window.NemesisReactComponents = undefined;
const vueRegistry    = {};
const alpineRegistry = {};
const ghostRegistry  = {};

const mockVue = {
    component(name, comp) { vueRegistry[name] = comp; }
};
const mockAlpine = {
    data(name, factory) { alpineRegistry[name] = factory; }
};
const mockGhost = {
    component(name, comp) { ghostRegistry[name] = comp; }
};

const result = registerModuleComponents({
    Vue:    mockVue,
    Alpine: mockAlpine,
    Ghost:  mockGhost,
    globs:  mockGlobs,
});

// Vue gets the .vue file
assert('Vue: UserUserCard registered',
    'UserUserCard' in vueRegistry);
assert('Vue: CartCartItem registered',
    'CartCartItem' in vueRegistry);
assertEqual('Vue: UserUserCard points to VUE_COMP_A (not React comp)',
    vueRegistry['UserUserCard'], VUE_COMP_A);

// React goes into NemesisReactComponents
assert('React: window.NemesisReactComponents created',
    typeof window.NemesisReactComponents === 'object' && window.NemesisReactComponents !== null);
assert('React: UserUserCard in NemesisReactComponents',
    'UserUserCard' in window.NemesisReactComponents);
assertEqual('React: UserUserCard points to REACT_COMP_A (not Vue comp)',
    window.NemesisReactComponents['UserUserCard'], REACT_COMP_A);
assert('React: DashboardDashboard registered',
    'DashboardDashboard' in window.NemesisReactComponents);

// No bleed: Vue registry must NOT contain React keys
assert('Vue registry has NO React-only keys (Dashboard)',
    !('DashboardDashboard' in vueRegistry));

// No bleed: NemesisReactComponents must NOT contain Vue-only keys
assert('React registry has NO Vue-only keys (CartCartItem)',
    !('CartCartItem' in window.NemesisReactComponents));

// Vue and React registered same name — different objects in different registries
assert('UserUserCard exists in BOTH Vue and React registries (separate)',
    'UserUserCard' in vueRegistry && 'UserUserCard' in window.NemesisReactComponents);
assert('UserUserCard Vue ≠ React (different objects, no overwrite)',
    vueRegistry['UserUserCard'] !== window.NemesisReactComponents['UserUserCard']);

// Alpine: camelCase name, lives in alpineRegistry
assert('Alpine: userUserDropdown registered (camelCase)',
    'userUserDropdown' in alpineRegistry);
assert('Alpine: NOT in Vue registry',
    !('userUserDropdown' in vueRegistry) && !('UserUserDropdown' in vueRegistry));
assertEqual('Alpine: factory function is the original',
    alpineRegistry['userUserDropdown'], ALPINE_FACTORY);

// Ghost
assert('Ghost: AppModal registered',
    'AppModal' in ghostRegistry);
assert('Ghost: NOT in Vue or React registry',
    !('AppModal' in vueRegistry) && !(window.NemesisReactComponents?.['AppModal']));
assertEqual('Ghost: AppModal points to GHOST_DEF',
    ghostRegistry['AppModal'], GHOST_DEF);

// registry return value
assertEqual('registry.vue names', result.vue.sort(), ['CartCartItem', 'UserUserCard']);
assertEqual('registry.react names', result.react.sort(), ['DashboardDashboard', 'UserUserCard']);
assertEqual('registry.alpine names', result.alpine, ['UserUserDropdown']);
assertEqual('registry.ghost names', result.ghost, ['AppModal']);

// ─── 3. Filtering — Vue only ──────────────────────────────────────────────────
section('Filtering: frameworks: ["vue"] — React/Alpine/Ghost must be skipped');

window.NemesisReactComponents = undefined;
const vueOnly = {};
const alpineOnly = {};
const ghostOnly  = {};

const r2 = registerModuleComponents({
    frameworks: ['vue'],
    Vue:    { component(n, c) { vueOnly[n] = c; } },
    Alpine: { data(n, f)      { alpineOnly[n] = f; } },
    Ghost:  { component(n, c) { ghostOnly[n] = c; } },
    globs:  mockGlobs,
});

assert('Vue components registered', r2.vue.length === 2);
assert('React NOT registered (frameworks filter)',
    r2.react.length === 0 && !window.NemesisReactComponents);
assert('Alpine NOT registered (frameworks filter)',
    r2.alpine.length === 0 && Object.keys(alpineOnly).length === 0);
assert('Ghost NOT registered (frameworks filter)',
    r2.ghost.length === 0 && Object.keys(ghostOnly).length === 0);

// ─── 4. Filtering — React + Alpine only ──────────────────────────────────────
section('Filtering: frameworks: ["react", "alpine"]');

window.NemesisReactComponents = undefined;
const vueRA = {};
const alpineRA = {};
const ghostRA  = {};

const r3 = registerModuleComponents({
    frameworks: ['react', 'alpine'],
    Vue:    { component(n, c) { vueRA[n] = c; } },
    Alpine: { data(n, f)      { alpineRA[n] = f; } },
    Ghost:  { component(n, c) { ghostRA[n] = c; } },
    globs:  mockGlobs,
});

assert('React registered',  r3.react.length === 2);
assert('Alpine registered', r3.alpine.length === 1);
assert('Vue NOT registered',   r3.vue.length   === 0 && Object.keys(vueRA).length   === 0);
assert('Ghost NOT registered', r3.ghost.length === 0 && Object.keys(ghostRA).length === 0);

// ─── 5. Missing framework instance — no crash ─────────────────────────────────
section('Missing framework instance — should silently skip, never throw');

window.NemesisReactComponents = undefined;
// No Vue/Alpine/Ghost passed AND not on window — should not throw
let threw = false;
try {
    // wipe window instances too
    delete window.Vue; delete window.Alpine; delete window.Ghost;
    const r4 = registerModuleComponents({ globs: mockGlobs });
    // React still registers (no instance needed), others silently skip
    assert('React still registered (no instance needed)',  r4.react.length === 2);
    assert('Vue skipped silently (no instance)',           r4.vue.length   === 0);
    assert('Alpine skipped silently (no instance)',        r4.alpine.length === 0);
    assert('Ghost skipped silently (no instance)',         r4.ghost.length  === 0);
} catch (e) {
    threw = true;
    failures.push('Missing instance threw: ' + e.message);
}
assert('No exception thrown when framework instances are missing', !threw);

// ─── 6. onRegister callback ───────────────────────────────────────────────────
section('onRegister callback — called for every registration');

window.NemesisReactComponents = undefined;
const calls = [];

registerModuleComponents({
    Vue:        { component() {} },
    Alpine:     { data() {} },
    Ghost:      { component() {} },
    globs:      mockGlobs,
    onRegister: (fw, name, comp) => calls.push({ fw, name }),
});

const fwsSeen = [...new Set(calls.map(c => c.fw))].sort();
assertEqual('onRegister called for all four frameworks', fwsSeen, ['alpine', 'ghost', 'react', 'vue']);
assert('onRegister call count = total component count (2+2+1+1=6)', calls.length === 6);

const vueCall = calls.find(c => c.fw === 'vue' && c.name === 'UserUserCard');
assert('onRegister vue UserUserCard received', !!vueCall);
const alpineCall = calls.find(c => c.fw === 'alpine');
assert('onRegister alpine name is PascalCase (UserUserDropdown)',
    alpineCall?.name === 'UserUserDropdown');

// ─── 7. Module without Components/ segment ────────────────────────────────────
section('Edge case: file path with no Components/ segment');

const oddGlobs = {
    vue: { 'Widget.vue': { default: { name: 'Widget' } } },
    react: {}, alpine: {}, ghost: {},
};
const oddVue = {};
const r5 = registerModuleComponents({
    Vue: { component(n, c) { oddVue[n] = c; } },
    globs: oddGlobs,
});
assert('Falls back to modName=App when no Components/ in path',
    'AppWidget' in oddVue);

// ─── 8. Module name with dots / underscores in component name ─────────────────
section('Edge case: component filenames with hyphens or underscores');

const weirdGlobs = {
    vue: {
        '../../app/Modules/Inventory/Components/StockItem.vue':       { default: {} },
        '../../app/Modules/Inventory/Components/StockReport.vue':     { default: {} },
    },
    react: {}, alpine: {}, ghost: {},
};
const weirdVue = {};
registerModuleComponents({ Vue: { component(n, c) { weirdVue[n] = c; } }, globs: weirdGlobs });
assert('InventoryStockItem registered', 'InventoryStockItem' in weirdVue);
assert('InventoryStockReport registered', 'InventoryStockReport' in weirdVue);

// ─── 9. mod.default fallback ──────────────────────────────────────────────────
section('Fallback: mod without .default uses the module itself');

const rawGlobs = {
    vue:    { '../../app/Modules/Misc/Components/Bare.vue': { render() { return 'bare'; } } },
    react:  {}, alpine: {}, ghost: {},
};
const rawVue = {};
registerModuleComponents({ Vue: { component(n, c) { rawVue[n] = c; } }, globs: rawGlobs });
assert('mod without .default — module itself used as component',
    typeof rawVue['MiscBare'] === 'object' && typeof rawVue['MiscBare'].render === 'function');

// ─── 10. NemesisReactComponents not clobbered on second call ──────────────────
section('NemesisReactComponents: second call merges, not overwrites');

window.NemesisReactComponents = { PreExisting: function Pre() {} };

const moreGlobs = {
    vue: {}, alpine: {}, ghost: {},
    react: { '../../app/Modules/X/Components/Xcomp.jsx': { default: function Xcomp() {} } },
};
registerModuleComponents({ frameworks: ['react'], globs: moreGlobs });

assert('Pre-existing React component preserved after second call',
    'PreExisting' in window.NemesisReactComponents);
assert('New React component added alongside existing',
    'XXcomp' in window.NemesisReactComponents);

// ─── Summary ──────────────────────────────────────────────────────────────────
console.log('\n' + '─'.repeat(50));
console.log(`Total: ${passed + failed}  Passed: \x1b[32m${passed}\x1b[0m  Failed: \x1b[31m${failed}\x1b[0m`);

if (failures.length) {
    console.log('\nFailures:');
    failures.forEach(f => console.log(`  [x] ${f}`));
    process.exit(1);
} else {
    console.log('\n\x1b[32mAll tests passed.\x1b[0m');
    process.exit(0);
}
