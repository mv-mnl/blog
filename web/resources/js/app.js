// Blog público. Cada librería se descarga solo si la página la necesita.
import 'highlight.js/styles/github-dark.css';

const root = document.documentElement;

// --- Tema: automático → claro → oscuro ---
const toggle = document.querySelector('[data-theme-toggle]');
const ORDER = ['auto', 'light', 'dark'];
const NAMES = { auto: 'automático', light: 'claro', dark: 'oscuro' };
const systemDark = matchMedia('(prefers-color-scheme: dark)');

function applyTheme(t) {
    root.dataset.tema = t;
    root.classList.toggle('dark', t === 'dark' || (t === 'auto' && systemDark.matches));
    if (toggle) {
        toggle.title = `Tema: ${NAMES[t]}`;
        toggle.querySelectorAll('[data-icon]').forEach((i) => i.classList.toggle('hidden', i.dataset.icon !== t));
    }
    document.dispatchEvent(new Event('themechange'));
}
applyTheme(root.dataset.tema || 'auto');
toggle?.addEventListener('click', () => {
    const next = ORDER[(ORDER.indexOf(root.dataset.tema) + 1) % ORDER.length];
    try {
        localStorage.setItem('tema', next);
    } catch {}
    applyTheme(next);
});
systemDark.addEventListener('change', () => root.dataset.tema === 'auto' && applyTheme('auto'));

// --- Colores en el código ---
const codeBlocks = document.querySelectorAll('.prose pre:not([data-mermaid]) code');
if (codeBlocks.length) {
    import('highlight.js/lib/common').then(({ default: hljs }) => {
        codeBlocks.forEach((el) => {
            const lang = [...el.classList].find((c) => c.startsWith('language-'))?.slice(9);
            if (lang && !hljs.getLanguage(lang)) el.classList.remove(`language-${lang}`);
            hljs.highlightElement(el);
        });
    });
}

// --- Gráficos ---
const charts = document.querySelectorAll('figure[data-chart]');
if (charts.length) {
    import('./chart-render').then(({ renderChart }) => {
        const draw = () =>
            charts.forEach((fig) => {
                let canvas = fig.querySelector('canvas');
                if (!canvas) fig.append((canvas = document.createElement('canvas')));
                try {
                    renderChart(canvas, JSON.parse(fig.dataset.chart));
                } catch {
                    /* datos inválidos: no se dibuja */
                }
            });
        draw();
        document.addEventListener('themechange', draw);
    });
}

// --- Diagramas ---
const diagrams = [...document.querySelectorAll('pre[data-mermaid]')];
if (diagrams.length) {
    diagrams.forEach((d) => (d.dataset.code = d.textContent));
    import('mermaid').then(({ default: mermaid }) => {
        let n = 0;
        const draw = async () => {
            mermaid.initialize({ startOnLoad: false, securityLevel: 'strict', theme: root.classList.contains('dark') ? 'dark' : 'neutral', fontFamily: 'ui-sans-serif, system-ui, sans-serif' });
            for (const d of diagrams) {
                d.classList.add('is-drawn'); // fuente normal antes de medir
                try {
                    const { svg } = await mermaid.render(`diagrama-${++n}`, d.dataset.code);
                    d.innerHTML = svg;
                } catch {
                    d.classList.remove('is-drawn');
                    d.textContent = d.dataset.code; // si falla, se ve el texto
                }
            }
        };
        draw();
        document.addEventListener('themechange', draw);
    });
}
