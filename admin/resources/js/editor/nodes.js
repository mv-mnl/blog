// Bloques propios del editor: imagen con pie de foto, aviso, vídeo, gráfico y diagrama.
import { Node, mergeAttributes } from '@tiptap/core';
import { CHART_TYPES, chartToText, parseChartText, renderChart } from './chart-render';
import { blockButtons, openDialog } from './dialog';

const setAttrs = (editor, getPos, attrs) => {
    const pos = getPos();
    const node = editor.state.doc.nodeAt(pos);
    editor.view.dispatch(editor.state.tr.setNodeMarkup(pos, undefined, { ...node.attrs, ...attrs }));
};
const removeNode = (editor, getPos) => {
    const pos = getPos();
    const node = editor.state.doc.nodeAt(pos);
    editor.view.dispatch(editor.state.tr.delete(pos, pos + node.nodeSize));
    editor.commands.focus();
};
const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const isDark = () => matchMedia('(prefers-color-scheme: dark)').matches;

// ---------------------------------------------------------------------------
// Imagen con pie de foto y texto alternativo
// ---------------------------------------------------------------------------
export const Figure = Node.create({
    name: 'figure',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return { src: { default: null }, alt: { default: '' }, caption: { default: '' } };
    },

    parseHTML() {
        return [
            {
                tag: 'figure',
                getAttrs: (el) => {
                    const img = el.querySelector('img');
                    if (!img || el.hasAttribute('data-chart')) return false;
                    return { src: img.getAttribute('src'), alt: img.getAttribute('alt') || '', caption: el.querySelector('figcaption')?.textContent || '' };
                },
            },
            { tag: 'img[src]', getAttrs: (el) => ({ src: el.getAttribute('src'), alt: el.getAttribute('alt') || '' }) },
        ];
    },

    renderHTML({ node }) {
        const img = ['img', { src: node.attrs.src, alt: node.attrs.alt || '' }];
        return node.attrs.caption ? ['figure', {}, img, ['figcaption', {}, node.attrs.caption]] : ['figure', {}, img];
    },

    addNodeView() {
        return ({ node, getPos, editor }) => {
            const dom = document.createElement('figure');
            dom.className = 'group relative not-prose my-8';
            dom.innerHTML = `
                <img class="w-full rounded-lg" alt="">
                <input data-caption placeholder="Añade un pie de foto (opcional)"
                       class="mt-2 w-full border-0 bg-transparent p-0 text-center text-sm text-stone-500 placeholder:text-stone-300 focus:ring-0 focus:outline-none dark:placeholder:text-stone-600">
                <button type="button" data-alt class="absolute top-2 left-2 rounded bg-white/95 px-2.5 py-1 text-xs font-medium text-stone-800 opacity-0 shadow transition group-hover:opacity-100"></button>`;
            const img = dom.querySelector('img');
            const caption = dom.querySelector('[data-caption]');
            const altBtn = dom.querySelector('[data-alt]');
            dom.append(blockButtons(() => altBtn.click(), () => removeNode(editor, getPos)));
            dom.querySelector('[data-edit]').textContent = 'Texto alternativo';
            altBtn.remove();

            let current = node;
            const paint = (n) => {
                img.src = n.attrs.src;
                img.alt = n.attrs.alt || '';
                if (document.activeElement !== caption) caption.value = n.attrs.caption || '';
                dom.querySelector('[data-edit]').textContent = n.attrs.alt ? 'Alt ✓' : 'Texto alternativo';
            };
            paint(node);

            caption.addEventListener('input', () => setAttrs(editor, getPos, { caption: caption.value }));
            caption.addEventListener('keydown', (e) => e.key === 'Enter' && (e.preventDefault(), editor.commands.focus()));
            altBtn.addEventListener('click', () => {
                const alt = prompt('Describe la imagen para quien no puede verla (lectores de pantalla, buscadores):', current.attrs.alt || '');
                if (alt !== null) setAttrs(editor, getPos, { alt: alt.trim() });
            });

            return {
                dom,
                update: (n) => n.type.name === 'figure' && ((current = n), paint(n), true),
                stopEvent: (e) => e.target === caption || !!e.target.closest?.('button'),
                ignoreMutation: () => true,
            };
        };
    },
});

// ---------------------------------------------------------------------------
// Bloque de aviso: Nota, Consejo, Cuidado, Peligro
// ---------------------------------------------------------------------------
export const CALLOUTS = { note: 'Nota', tip: 'Consejo', warning: 'Cuidado', danger: 'Peligro' };

export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            type: {
                default: 'note',
                parseHTML: (el) => (CALLOUTS[el.getAttribute('data-callout')] ? el.getAttribute('data-callout') : 'note'),
                renderHTML: (attrs) => ({ 'data-callout': attrs.type }),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'aside[data-callout]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['aside', mergeAttributes(HTMLAttributes), 0];
    },

    addCommands() {
        return {
            setCallout: (type) => ({ editor, commands }) =>
                editor.isActive('callout') ? commands.updateAttributes('callout', { type }) : commands.wrapIn(this.name, { type }),
            unsetCallout: () => ({ commands }) => commands.lift(this.name),
        };
    },
});

// ---------------------------------------------------------------------------
// Vídeo de YouTube (se guarda solo el ID; la web genera el reproductor)
// ---------------------------------------------------------------------------
export function youtubeId(url) {
    const m = String(url).match(/(?:youtube(?:-nocookie)?\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{11})/);
    return m?.[1] ?? null;
}

export const YouTube = Node.create({
    name: 'youtube',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return { id: { default: null, parseHTML: (el) => el.getAttribute('data-youtube'), renderHTML: (a) => ({ 'data-youtube': a.id }) } };
    },

    parseHTML() {
        return [{ tag: 'div[data-youtube]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', HTMLAttributes];
    },

    addNodeView() {
        return ({ node, getPos, editor }) => {
            const dom = document.createElement('div');
            dom.className = 'group relative not-prose my-8 aspect-video overflow-hidden rounded-lg bg-stone-900';
            dom.innerHTML = `
                <img class="size-full object-cover opacity-80" alt="">
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-white">
                    <span class="flex size-16 items-center justify-center rounded-full bg-red-600 text-2xl shadow-lg">▶</span>
                    <span class="rounded bg-black/60 px-2 py-0.5 text-xs">Vídeo de YouTube · se reproduce en el blog</span>
                </div>`;
            const paint = (n) => (dom.querySelector('img').src = `https://i.ytimg.com/vi/${n.attrs.id}/hqdefault.jpg`);
            paint(node);
            dom.append(
                blockButtons(
                    () => {
                        const url = prompt('Nuevo enlace de YouTube:');
                        const id = url && youtubeId(url);
                        if (id) setAttrs(editor, getPos, { id });
                        else if (url) alert('Ese enlace no parece de YouTube.');
                    },
                    () => removeNode(editor, getPos),
                ),
            );
            return { dom, update: (n) => n.type.name === 'youtube' && (paint(n), true), stopEvent: (e) => !!e.target.closest?.('button'), ignoreMutation: () => true };
        };
    },
});

// ---------------------------------------------------------------------------
// Gráfico
// ---------------------------------------------------------------------------
const EXAMPLE = 'Lenguaje, Uso (%)\nPHP, 45\nJavaScript, 30\nPython, 18\nGo, 7';

export function editChart(initial, onSave) {
    const body = document.createElement('div');
    body.className = 'grid gap-6 md:grid-cols-2';
    body.innerHTML = `
        <div class="space-y-4">
            <div>
                <span class="mb-1 block text-sm font-medium">Tipo</span>
                <div class="flex flex-wrap gap-1" data-types></div>
            </div>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Título (opcional)</span>
                <input data-title class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 text-sm dark:border-stone-700 dark:bg-stone-950">
            </label>
            <label class="block">
                <span class="mb-1 block text-sm font-medium">Datos</span>
                <textarea data-text rows="9" spellcheck="false" class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 font-mono text-sm dark:border-stone-700 dark:bg-stone-950"></textarea>
                <span class="mt-1 block text-xs text-stone-500">
                    Una fila por línea: <code>etiqueta, valor</code>. Para varias series añade más columnas.
                    La primera línea puede ser la cabecera. <strong>Puedes pegar celdas de Excel o Google Sheets.</strong>
                </span>
            </label>
        </div>
        <div>
            <span class="mb-1 block text-sm font-medium">Vista previa</span>
            <div class="h-80 rounded-lg border border-stone-200 p-3 dark:border-stone-800"><canvas></canvas></div>
            <p data-error class="mt-2 text-sm text-red-600"></p>
        </div>`;
    const typesBox = body.querySelector('[data-types]');
    const title = body.querySelector('[data-title]');
    const text = body.querySelector('[data-text]');
    const canvas = body.querySelector('canvas');
    const error = body.querySelector('[data-error]');
    let type = initial?.type || 'bar';
    title.value = initial?.title || '';
    text.value = initial ? chartToText(initial) : EXAMPLE;

    const current = () => ({ type, title: title.value.trim(), ...parseChartText(text.value) });
    const preview = () => {
        const c = current();
        const valid = c.rows.length && c.rows.some((r) => r.slice(1).some((v) => v !== null));
        error.textContent = valid ? '' : 'Añade al menos una fila con un número.';
        renderChart(canvas, valid ? c : null);
    };
    for (const [key, label] of Object.entries(CHART_TYPES)) {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        b.className = 'rounded-md border border-stone-300 px-3 py-1.5 text-sm data-[on]:border-amber-500 data-[on]:bg-amber-50 data-[on]:font-medium dark:border-stone-700 dark:data-[on]:bg-amber-950';
        b.toggleAttribute('data-on', key === type);
        b.addEventListener('click', () => {
            type = key;
            typesBox.querySelectorAll('button').forEach((x) => x.toggleAttribute('data-on', x === b));
            preview();
        });
        typesBox.append(b);
    }
    title.addEventListener('input', preview);
    text.addEventListener('input', preview);

    openDialog({
        title: initial ? 'Editar gráfico' : 'Insertar gráfico',
        body,
        saveLabel: initial ? 'Guardar gráfico' : 'Insertar gráfico',
        onSave: () => {
            const c = current();
            if (!c.rows.length) return false;
            onSave(c);
        },
    });
    requestAnimationFrame(preview);
}

export const ChartBlock = Node.create({
    name: 'chart',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            data: {
                default: null,
                parseHTML: (el) => {
                    try {
                        return JSON.parse(el.getAttribute('data-chart'));
                    } catch {
                        return null;
                    }
                },
                renderHTML: (a) => (a.data ? { 'data-chart': JSON.stringify(a.data) } : {}),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'figure[data-chart]', priority: 100 }]; // antes que la imagen con pie (<figure>)
    },

    renderHTML({ HTMLAttributes }) {
        return ['figure', HTMLAttributes];
    },

    addNodeView() {
        return ({ node, getPos, editor }) => {
            const dom = document.createElement('div');
            dom.className = 'group relative not-prose my-8 h-80 rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900';
            const canvas = document.createElement('canvas');
            dom.append(canvas);
            let current = node;
            const edit = () => editChart(current.attrs.data, (data) => setAttrs(editor, getPos, { data }));
            dom.append(blockButtons(edit, () => removeNode(editor, getPos)));
            dom.addEventListener('dblclick', edit);
            const empty = document.createElement('p');
            empty.className = 'absolute inset-0 flex items-center justify-center text-sm text-stone-400';
            empty.textContent = 'Gráfico vacío: pulsa «Editar» para añadir datos o «Quitar» para borrarlo.';
            dom.append(empty);
            const paint = (data) => {
                empty.classList.toggle('hidden', !!data?.rows?.length);
                renderChart(canvas, data);
            };
            requestAnimationFrame(() => paint(node.attrs.data));
            return {
                dom,
                update: (n) => n.type.name === 'chart' && ((current = n), paint(n.attrs.data), true),
                stopEvent: (e) => !!e.target.closest?.('button'),
                ignoreMutation: () => true,
            };
        };
    },
});

// ---------------------------------------------------------------------------
// Diagrama (Mermaid)
// ---------------------------------------------------------------------------
let mermaidPromise;
let mermaidCount = 0;
const getMermaid = () =>
    (mermaidPromise ??= import('mermaid').then(({ default: m }) => {
        m.initialize({ startOnLoad: false, securityLevel: 'strict', theme: isDark() ? 'dark' : 'neutral', fontFamily: 'ui-sans-serif, system-ui, sans-serif' });
        return m;
    }));

export async function renderMermaid(el, code) {
    if (!code.trim()) {
        el.innerHTML = '<p class="text-sm text-stone-400">Escribe el diagrama a la izquierda.</p>';
        return;
    }
    const mermaid = await getMermaid();
    try {
        const { svg } = await mermaid.render(`mmd-${++mermaidCount}`, code);
        el.innerHTML = svg;
    } catch (e) {
        el.innerHTML = `<p class="text-sm text-red-600">El diagrama tiene un error de sintaxis:<br><code class="text-xs">${escapeHtml(e.message || e).slice(0, 300)}</code></p>`;
    }
}

const DIAGRAM_EXAMPLE = `flowchart LR
    Visitante -->|lee| Web
    Yo -->|escribe| Admin
    Web -->|SELECT| MySQL[(MySQL)]
    Admin --> MySQL`;

export function editDiagram(initial, onSave) {
    const body = document.createElement('div');
    body.className = 'grid gap-6 md:grid-cols-2';
    body.innerHTML = `
        <label class="block">
            <span class="mb-1 block text-sm font-medium">Código del diagrama</span>
            <textarea data-code rows="14" spellcheck="false" class="w-full rounded-md border border-stone-300 bg-white px-3 py-2 font-mono text-sm dark:border-stone-700 dark:bg-stone-950"></textarea>
            <span class="mt-1 block text-xs text-stone-500">
                Flechas: <code>A --&gt; B</code>, con texto: <code>A --&gt;|texto| B</code>.
                También secuencias, tiempos (gantt), clases…
                <a href="https://mermaid.js.org/intro/" target="_blank" class="underline">Ver ejemplos de Mermaid ↗</a>
            </span>
        </label>
        <div>
            <span class="mb-1 block text-sm font-medium">Vista previa</span>
            <div data-preview data-diagram-out class="tiptap flex min-h-80 items-center justify-center overflow-auto rounded-lg border border-stone-200 p-3 dark:border-stone-800"></div>
        </div>`;
    const code = body.querySelector('[data-code]');
    const preview = body.querySelector('[data-preview]');
    code.value = initial ?? DIAGRAM_EXAMPLE;
    let timer;
    code.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => renderMermaid(preview, code.value), 300);
    });
    openDialog({
        title: initial ? 'Editar diagrama' : 'Insertar diagrama',
        body,
        saveLabel: initial ? 'Guardar diagrama' : 'Insertar diagrama',
        onSave: () => (code.value.trim() ? onSave(code.value) : false),
    });
    renderMermaid(preview, code.value);
}

export const Diagram = Node.create({
    name: 'diagram',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return { code: { default: '' } };
    },

    parseHTML() {
        return [{ tag: 'pre[data-mermaid]', priority: 100, preserveWhitespace: 'full', getAttrs: (el) => ({ code: el.textContent }) }]; // antes que el bloque de código
    },

    renderHTML({ node }) {
        return ['pre', { 'data-mermaid': '' }, node.attrs.code];
    },

    addNodeView() {
        return ({ node, getPos, editor }) => {
            const dom = document.createElement('div');
            dom.className = 'group relative not-prose my-8 flex min-h-40 items-center justify-center overflow-auto rounded-lg border border-stone-200 bg-white p-4 dark:border-stone-800 dark:bg-stone-900';
            const out = document.createElement('div');
            out.setAttribute('data-diagram-out', '');
            out.className = 'w-full';
            dom.append(out);
            let current = node;
            const edit = () => editDiagram(current.attrs.code, (code) => setAttrs(editor, getPos, { code }));
            dom.append(blockButtons(edit, () => removeNode(editor, getPos)));
            dom.addEventListener('dblclick', edit);
            renderMermaid(out, node.attrs.code);
            return {
                dom,
                update: (n) => {
                    if (n.type.name !== 'diagram') return false;
                    if (n.attrs.code !== current.attrs.code) renderMermaid(out, n.attrs.code);
                    current = n;
                    return true;
                },
                stopEvent: (e) => !!e.target.closest?.('button'),
                ignoreMutation: () => true,
            };
        };
    },
});
