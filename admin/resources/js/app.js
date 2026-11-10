import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import { Placeholder } from '@tiptap/extensions';
import { TableKit } from '@tiptap/extension-table';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import { common, createLowlight } from 'lowlight';
import dockerfile from 'highlight.js/lib/languages/dockerfile';
import 'highlight.js/styles/github-dark.css';
import { CALLOUTS, Callout, ChartBlock, Diagram, Figure, YouTube, editChart, editDiagram, youtubeId } from './editor/nodes';
import { renderChart } from './editor/chart-render';

const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

// Aviso flotante "Guardado": desaparece solo.
const toast = document.querySelector('[data-toast]');
if (toast) {
    setTimeout(() => toast.classList.add('opacity-0', 'translate-y-2'), 3500);
    setTimeout(() => toast.remove(), 4200);
}

// Gráfico de la página de estadísticas.
const statsCanvas = document.querySelector('[data-stats-chart]');
if (statsCanvas) {
    const data = JSON.parse(statsCanvas.dataset.statsChart);
    renderChart(statsCanvas, {
        type: 'area',
        title: '',
        series: ['Visitas', 'Visitantes'],
        rows: data.map((d) => [d.label, d.views, d.visitors]),
    });
}

// ---------------------------------------------------------------------------
// Subida de imágenes al servidor
// ---------------------------------------------------------------------------
async function uploadImage(file) {
    const data = new FormData();
    data.append('image', file);
    const res = await fetch('/upload', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        body: data,
    });
    const json = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(json.message || `Error ${res.status}`);
    return json.url;
}

const imagesIn = (list) => [...(list || [])].filter((f) => f.type.startsWith('image/'));

async function insertImages(editor, files, pos = null) {
    for (const file of files) {
        try {
            const src = await uploadImage(file);
            const node = { type: 'figure', attrs: { src, alt: '', caption: '' } };
            const chain = editor.chain().focus();
            (pos !== null ? chain.insertContentAt(pos, node) : chain.insertContent(node)).run();
        } catch (err) {
            alert(`No se pudo subir «${file.name}»: ${err.message}`);
        }
    }
}

// ---------------------------------------------------------------------------
// Barra de formato (una sola, arriba del todo, para todos los editores)
// ---------------------------------------------------------------------------
const lowlight = createLowlight(common);
lowlight.register({ dockerfile });

const LANGUAGES = {
    '': 'Automático', bash: 'Bash / Terminal', css: 'CSS', dockerfile: 'Dockerfile', go: 'Go', xml: 'HTML / XML',
    java: 'Java', javascript: 'JavaScript', json: 'JSON', markdown: 'Markdown', php: 'PHP', python: 'Python',
    rust: 'Rust', sql: 'SQL', typescript: 'TypeScript', yaml: 'YAML', plaintext: 'Texto plano',
};

const svg = (d) => `<svg viewBox="0 0 24 24" class="inline size-4 -mt-0.5 mr-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${d}</svg>`;
const ICON_LINK = svg('<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>');
const ICON_IMAGE = svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>');

const TOOLBAR = [
    ['H2', 'Título grande', (e) => e.chain().focus().toggleHeading({ level: 2 }).run(), (e) => e.isActive('heading', { level: 2 })],
    ['H3', 'Título pequeño', (e) => e.chain().focus().toggleHeading({ level: 3 }).run(), (e) => e.isActive('heading', { level: 3 })],
    '|',
    ['<b>B</b>', 'Negrita (Ctrl+B)', (e) => e.chain().focus().toggleBold().run(), (e) => e.isActive('bold')],
    ['<i>I</i>', 'Cursiva (Ctrl+I)', (e) => e.chain().focus().toggleItalic().run(), (e) => e.isActive('italic')],
    ['<s>S</s>', 'Tachado', (e) => e.chain().focus().toggleStrike().run(), (e) => e.isActive('strike')],
    ['<code>&lt;/&gt;</code>', 'Código en línea', (e) => e.chain().focus().toggleCode().run(), (e) => e.isActive('code')],
    [`${ICON_LINK}Enlace`, 'Enlace (Ctrl+K)', (e) => promptLink(e), (e) => e.isActive('link')],
    '|',
    ['• Lista', 'Lista con puntos', (e) => e.chain().focus().toggleBulletList().run(), (e) => e.isActive('bulletList')],
    ['1. Lista', 'Lista numerada', (e) => e.chain().focus().toggleOrderedList().run(), (e) => e.isActive('orderedList')],
    ['❝ Cita', 'Cita', (e) => e.chain().focus().toggleBlockquote().run(), (e) => e.isActive('blockquote')],
    ['{ } Código', 'Bloque de código (con colores)', (e) => e.chain().focus().toggleCodeBlock().run(), (e) => e.isActive('codeBlock')],
    '|',
    ['insert'],
    '|',
    ['↶', 'Deshacer (Ctrl+Z)', (e) => e.chain().focus().undo().run()],
    ['↷', 'Rehacer (Ctrl+Shift+Z)', (e) => e.chain().focus().redo().run()],
];

// Menú "+ Insertar": bloques que se añaden en la posición del cursor.
const INSERT_MENU = [
    [`${ICON_IMAGE}Imagen`, 'Sube una foto (también puedes pegarla o arrastrarla)', 'image'],
    ['▶ Vídeo de YouTube', 'Pega el enlace de un vídeo', (e) => {
        const url = prompt('Enlace del vídeo de YouTube:');
        if (!url) return;
        const id = youtubeId(url);
        id ? e.chain().focus().insertContent({ type: 'youtube', attrs: { id } }).run() : alert('Ese enlace no parece de YouTube.');
    }],
    ['▦ Tabla', 'Tabla de 3×3 con cabecera', (e) => e.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run()],
    ['📊 Gráfico', 'Barras, líneas, tarta… a partir de datos', (e) => editChart(null, (data) => e.chain().focus().insertContent({ type: 'chart', attrs: { data } }).run())],
    ['⧉ Diagrama', 'Diagrama de flujo, secuencia… escrito como texto', (e) => editDiagram(null, (code) => e.chain().focus().insertContent({ type: 'diagram', attrs: { code } }).run())],
    ['ℹ Aviso / nota', 'Caja destacada: nota, consejo, cuidado o peligro', (e) => e.chain().focus().setCallout('note').run()],
    ['― Línea separadora', 'Separa secciones', (e) => e.chain().focus().setHorizontalRule().run()],
];

function promptLink(editor) {
    const previous = editor.getAttributes('link').href || '';
    const url = window.prompt('Dirección del enlace (déjala vacía para quitarlo):', previous || 'https://');
    if (url === null) return;
    if (url.trim() === '' || url.trim() === 'https://') {
        editor.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        editor.chain().focus().extendMarkRange('link').setLink({ href: url.trim() }).run();
    }
}

let activeEditor = null;
let mainEditor = null; // el del post: se usa cuando no hay cursor en otro editor
const toolbarButtons = [];
let contextBar = null;

function setActiveEditor(editor) {
    activeEditor = editor;
    refreshToolbar();
}

const btnClass = 'rounded px-2 py-1 text-sm text-stone-700 hover:bg-stone-200 dark:text-stone-300 dark:hover:bg-stone-800 data-[active]:bg-amber-100 data-[active]:text-amber-900 dark:data-[active]:bg-amber-950 dark:data-[active]:text-amber-300';

function makeButton(html, title, onClick) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.title = title;
    btn.innerHTML = html;
    btn.className = btnClass;
    btn.addEventListener('mousedown', (e) => e.preventDefault()); // no perder la selección
    btn.addEventListener('click', onClick);
    return btn;
}

function refreshToolbar() {
    const e = activeEditor;
    toolbarButtons.forEach(([btn, isActive]) => btn.toggleAttribute('data-active', !!e && isActive(e)));
    if (!contextBar) return;

    // Controles que aparecen según dónde está el cursor.
    contextBar.replaceChildren();
    if (!e) return;
    const label = (t) => contextBar.insertAdjacentHTML('beforeend', `<span class="text-xs font-medium text-stone-500">${t}</span>`);

    if (e.isActive('codeBlock')) {
        label('Lenguaje:');
        const sel = document.createElement('select');
        sel.className = 'rounded border border-stone-300 bg-white py-0.5 pr-7 pl-2 text-sm dark:border-stone-700 dark:bg-stone-900';
        const current = e.getAttributes('codeBlock').language || '';
        for (const [value, name] of Object.entries(LANGUAGES)) sel.add(new Option(name, value, false, value === current));
        sel.addEventListener('change', () => e.chain().focus().updateAttributes('codeBlock', { language: sel.value || null }).run());
        contextBar.append(sel);
    }
    if (e.isActive('table')) {
        label('Tabla:');
        contextBar.append(
            makeButton('+ Fila', 'Añadir fila debajo', () => e.chain().focus().addRowAfter().run()),
            makeButton('+ Columna', 'Añadir columna a la derecha', () => e.chain().focus().addColumnAfter().run()),
            makeButton('− Fila', 'Quitar esta fila', () => e.chain().focus().deleteRow().run()),
            makeButton('− Columna', 'Quitar esta columna', () => e.chain().focus().deleteColumn().run()),
            makeButton('<span class="text-red-600">Borrar tabla</span>', 'Eliminar la tabla entera', () => e.chain().focus().deleteTable().run()),
        );
    }
    if (e.isActive('callout')) {
        label('Aviso:');
        const type = e.getAttributes('callout').type;
        for (const [key, name] of Object.entries(CALLOUTS)) {
            const b = makeButton(name, `Cambiar a «${name}»`, () => e.chain().focus().setCallout(key).run());
            b.toggleAttribute('data-active', key === type);
            contextBar.append(b);
        }
        contextBar.append(makeButton('<span class="text-red-600">Quitar</span>', 'Quitar la caja (el texto se queda)', () => e.chain().focus().unsetCallout().run()));
    }
    contextBar.classList.toggle('hidden', !contextBar.children.length);
}

function buildToolbar(toolbar) {
    const fileInput = Object.assign(document.createElement('input'), { type: 'file', accept: 'image/*', multiple: true, hidden: true });
    fileInput.addEventListener('change', () => {
        if (activeEditor) insertImages(activeEditor, imagesIn(fileInput.files));
        fileInput.value = '';
    });
    toolbar.append(fileInput);

    for (const item of TOOLBAR) {
        if (item === '|') {
            toolbar.insertAdjacentHTML('beforeend', '<span class="mx-1 h-5 w-px bg-stone-300 dark:bg-stone-700"></span>');
            continue;
        }
        if (item[0] === 'insert') {
            toolbar.append(buildInsertMenu(fileInput));
            continue;
        }
        const [html, title, run, isActive] = item;
        const btn = makeButton(html, title, () => activeEditor && run(activeEditor));
        toolbar.append(btn);
        if (isActive) toolbarButtons.push([btn, isActive]);
    }

    contextBar = document.createElement('div');
    contextBar.className = 'hidden basis-full flex flex-wrap items-center justify-center gap-1 border-t border-stone-200 pt-1.5 mt-1 dark:border-stone-800';
    toolbar.append(contextBar);
}

function buildInsertMenu(fileInput) {
    const wrap = document.createElement('div');
    wrap.className = 'relative';
    const trigger = makeButton('<span class="font-medium">+ Insertar</span> ▾', 'Imagen, vídeo, tabla, gráfico, diagrama…', () => menu.classList.toggle('hidden'));
    const menu = document.createElement('div');
    menu.className = 'absolute top-full left-0 z-30 mt-1 hidden w-72 rounded-lg border border-stone-200 bg-white p-1 shadow-xl dark:border-stone-700 dark:bg-stone-900';
    for (const [html, hint, action] of INSERT_MENU) {
        const item = makeButton(`<span class="block text-sm">${html}</span><span class="block text-xs text-stone-500">${hint}</span>`, '', () => {
            menu.classList.add('hidden');
            if (!activeEditor) return;
            action === 'image' ? fileInput.click() : action(activeEditor);
        });
        item.className = 'block w-full rounded-md px-3 py-2 text-left hover:bg-stone-100 dark:hover:bg-stone-800';
        menu.append(item);
    }
    document.addEventListener('click', (e) => !wrap.contains(e.target) && menu.classList.add('hidden'));
    wrap.append(trigger, menu);
    return wrap;
}

// ---------------------------------------------------------------------------
// Editor visual (Tiptap)
// ---------------------------------------------------------------------------
function mountEditor(root, onChange) {
    if (root.dataset.mounted) return root._editor;
    root.dataset.mounted = '1';

    const value = root.querySelector('[data-value]');
    const content = root.querySelector('[data-content]');
    const small = root.classList.contains('is-small');
    const sync = (editor) => (value.value = editor.isEmpty ? '' : editor.getHTML());

    const editor = new Editor({
        element: content,
        content: value.value,
        extensions: [
            StarterKit.configure({
                heading: { levels: [2, 3] },
                codeBlock: false,
                link: { openOnClick: false, autolink: true, defaultProtocol: 'https' },
            }),
            CodeBlockLowlight.configure({ lowlight, defaultLanguage: null }),
            TableKit.configure({ table: { resizable: false } }),
            Figure,
            Callout,
            YouTube,
            ChartBlock,
            Diagram,
            Placeholder.configure({ placeholder: root.dataset.placeholder || 'Escribe…' }),
        ],
        editorProps: {
            attributes: {
                class: `tiptap prose prose-stone max-w-none focus:outline-none dark:prose-invert prose-headings:font-serif prose-img:rounded-lg ${small ? 'min-h-32' : 'prose-lg min-h-72'}`,
            },
            handlePaste: (view, event) => {
                const files = imagesIn(event.clipboardData?.files);
                if (files.length) {
                    insertImages(editor, files);
                    return true;
                }
                // Pegar solo un enlace de YouTube lo convierte en vídeo.
                const text = event.clipboardData?.getData('text/plain')?.trim();
                const id = text && /^\S+$/.test(text) && youtubeId(text);
                if (id) {
                    editor.chain().focus().insertContent({ type: 'youtube', attrs: { id } }).run();
                    return true;
                }
                return false;
            },
            handleDrop: (view, event) => {
                const files = imagesIn(event.dataTransfer?.files);
                if (!files.length) return false;
                const pos = view.posAtCoords({ left: event.clientX, top: event.clientY })?.pos ?? null;
                insertImages(editor, files, pos);
                return true;
            },
            handleKeyDown: (view, event) => {
                if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
                    event.preventDefault();
                    promptLink(editor);
                    return true;
                }
                return false;
            },
        },
        onUpdate: () => {
            sync(editor);
            onChange?.();
        },
    });
    sync(editor);
    root._editor = editor;
    root._setContent = (html) => {
        editor.commands.setContent(html || '');
        sync(editor);
    };

    editor.on('focus', () => setActiveEditor(editor));
    editor.on('transaction', () => editor === activeEditor && refreshToolbar());
    if (!mainEditor) mainEditor = editor;
    if (!activeEditor) setActiveEditor(editor);

    return editor;
}

// ---------------------------------------------------------------------------
// Portada: clic o arrastrar
// ---------------------------------------------------------------------------
function mountCover(root, onChange) {
    if (root.dataset.mounted) return;
    root.dataset.mounted = '1';

    const zone = root.querySelector('[data-cover-zone]');
    const input = root.querySelector('[data-cover-input]');
    const img = root.querySelector('[data-cover-img]');
    const empty = root.querySelector('[data-cover-empty]');
    const actions = root.querySelector('[data-cover-actions]');
    const removed = root.querySelector('[data-cover-removed]');

    const show = (src) => {
        img.src = src || '';
        img.classList.toggle('hidden', !src);
        empty.classList.toggle('hidden', !!src);
        actions.classList.toggle('hidden', !src);
        zone.toggleAttribute('data-filled', !!src);
    };

    input.addEventListener('change', () => {
        const file = input.files[0];
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
            alert('La imagen pesa más de 5 MB.');
            input.value = '';
            return;
        }
        removed.value = '0';
        show(URL.createObjectURL(file));
        onChange?.();
    });

    root.querySelector('[data-cover-change]').addEventListener('click', () => input.click());
    root.querySelector('[data-cover-remove]').addEventListener('click', () => {
        input.value = '';
        removed.value = '1';
        show(null);
        onChange?.();
    });

    zone.addEventListener('dragover', (e) => {
        e.preventDefault();
        zone.setAttribute('data-dragging', '');
    });
    zone.addEventListener('dragleave', () => zone.removeAttribute('data-dragging'));
    zone.addEventListener('drop', (e) => {
        e.preventDefault();
        zone.removeAttribute('data-dragging');
        const files = imagesIn(e.dataTransfer.files);
        if (!files.length) return;
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        input.files = dt.files;
        input.dispatchEvent(new Event('change'));
    });
}

// ---------------------------------------------------------------------------
// Página de edición de un post
// ---------------------------------------------------------------------------
const form = document.querySelector('[data-post-form]');

if (form) {
    const dirtyNote = form.querySelector('[data-dirty-note]');
    let dirty = false;
    let autosaveTimer;
    const markDirty = () => {
        dirty = true;
        dirtyNote.classList.remove('hidden');
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(autosave, 1500);
    };

    const mountAll = (scope) => {
        scope.querySelectorAll('[data-rich-editor]').forEach((el) => mountEditor(el, markDirty));
        scope.querySelectorAll('[data-cover]').forEach((el) => mountCover(el, markDirty));
        scope.querySelectorAll('[data-autosize]').forEach(autosize);
    };

    // Título y subtítulo crecen con el texto; Enter salta al siguiente campo.
    function autosize(el) {
        const fit = () => {
            el.style.height = 'auto';
            el.style.height = `${el.scrollHeight}px`;
        };
        el._fit = fit;
        el.addEventListener('input', fit);
        el.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (el.matches('[data-title]')) form.querySelector('[data-subtitle]').focus();
                else form.querySelector('[data-rich-editor] .tiptap')?.focus();
            }
        });
        requestAnimationFrame(fit);
    }

    // --- Actualizaciones ---
    const list = form.querySelector('[data-updates-list]');
    const template = form.querySelector('[data-update-template]');

    const setupCard = (card) => {
        const del = card.querySelector('[data-delete-input]');
        const body = card.querySelector('[data-update-body]');
        const note = card.querySelector('[data-update-deleted-note]');
        const isNew = !card.querySelector('input[name$="[id]"]').value;
        const sync = () => {
            const deleted = del.value === '1';
            body.classList.toggle('hidden', deleted);
            note.classList.toggle('hidden', !deleted);
        };
        card.querySelector('[data-update-delete]').addEventListener('click', () => {
            if (card.contains(activeEditor?.view.dom)) setActiveEditor(mainEditor);
            if (isNew) {
                card.remove();
            } else {
                del.value = '1';
                sync();
            }
            markDirty();
        });
        card.querySelector('[data-update-undo]').addEventListener('click', () => {
            del.value = '0';
            sync();
            markDirty();
        });
        sync();
    };

    const addUpdateCard = (key = `n${Date.now()}`) => {
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__KEY__', key));
        const card = list.lastElementChild;
        setupCard(card);
        mountAll(card);
        return card;
    };

    list.querySelectorAll('[data-update-card]').forEach(setupCard);

    form.querySelector('[data-add-update]').addEventListener('click', () => {
        const card = addUpdateCard();
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });
        card.querySelector('input[name$="[title]"]').focus();
        markDirty();
    });

    buildToolbar(form.querySelector('[data-global-toolbar]'));
    mountAll(form);
    form.addEventListener('input', markDirty);

    // --- Autoguardado en este navegador (por si se cierra la pestaña o se va la luz) ---
    const draftKey = `blog-borrador:${form.dataset.postId || 'nuevo'}`;
    const field = (name) => form.querySelector(`[name="${name}"]`);

    function collect() {
        return {
            savedAt: Date.now(),
            title: field('title').value,
            excerpt: field('excerpt').value,
            body: field('body').value,
            tags: field('tags').value,
            slug: field('slug').value,
            series: field('series').value,
            updates: [...list.querySelectorAll('[data-update-card]')].map((card) => {
                const key = card.querySelector('input[name$="[id]"]').name.match(/^updates\[([^\]]+)\]/)[1];
                return {
                    key,
                    title: card.querySelector(`[name="updates[${key}][title]"]`).value,
                    body: card.querySelector(`[name="updates[${key}][body]"]`).value,
                    delete: card.querySelector(`[name="updates[${key}][delete]"]`).value,
                };
            }),
        };
    }

    function autosave() {
        try {
            localStorage.setItem(draftKey, JSON.stringify(collect()));
            const note = form.querySelector('[data-autosave-note]');
            note.textContent = `· copia local ${new Date().toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })}`;
            note.classList.remove('hidden');
        } catch {
            /* sin espacio o almacenamiento bloqueado: no pasa nada */
        }
    }

    function restore(d) {
        for (const name of ['title', 'excerpt', 'tags', 'slug', 'series']) {
            field(name).value = d[name] ?? '';
            field(name)._fit?.();
        }
        form.querySelector('article [data-rich-editor]')._setContent(d.body);
        for (const u of d.updates || []) {
            let card = list.querySelector(`[name="updates[${u.key}][title]"]`)?.closest('[data-update-card]');
            if (!card) card = addUpdateCard(u.key);
            card.querySelector(`[name="updates[${u.key}][title]"]`).value = u.title;
            card.querySelector('[data-rich-editor]')._setContent(u.body);
            const del = card.querySelector(`[name="updates[${u.key}][delete]"]`);
            if (del.value !== u.delete) card.querySelector(u.delete === '1' ? '[data-update-delete]' : '[data-update-undo]').click();
        }
        markDirty();
    }

    try {
        const saved = JSON.parse(localStorage.getItem(draftKey) || 'null');
        const serverTime = Number(form.dataset.updatedAt || 0);
        const now = collect();
        const differs = saved && ['title', 'excerpt', 'body', 'tags', 'slug', 'series'].some((k) => (saved[k] ?? '') !== (now[k] ?? '')) ||
            (saved && JSON.stringify(saved.updates) !== JSON.stringify(now.updates));
        if (saved && saved.savedAt > serverTime && differs && !form.dataset.hasErrors) {
            const banner = form.querySelector('[data-restore-banner]');
            banner.querySelector('[data-restore-time]').textContent = new Date(saved.savedAt).toLocaleString('es', { dateStyle: 'medium', timeStyle: 'short' });
            banner.classList.remove('hidden');
            banner.querySelector('[data-restore]').addEventListener('click', () => {
                restore(saved);
                banner.remove();
            });
            banner.querySelector('[data-discard]').addEventListener('click', () => {
                localStorage.removeItem(draftKey);
                banner.remove();
            });
        } else if (saved && !differs) {
            localStorage.removeItem(draftKey);
        }
    } catch {
        /* almacenamiento no disponible */
    }

    // --- Ajustes / Publicar ---
    const settings = form.querySelector('[data-settings]');
    const title = field('title');
    const slug = form.querySelector('[data-slug]');
    const publishNow = settings.querySelector('[data-publish-now]');

    const slugify = (s) =>
        s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 190);

    const openSettings = (publishing) => {
        if (!slug.value) slug.placeholder = slugify(title.value) || 'se-genera-del-titulo';
        settings.querySelector('[data-settings-title]').textContent = publishing ? 'Publicar el post' : 'Ajustes del post';
        settings.querySelector('[data-publish-intro]').classList.toggle('hidden', !publishing);
        publishNow.classList.toggle('hidden', !publishing);
        settings.showModal();
    };

    form.querySelector('[data-open-settings]').addEventListener('click', () => openSettings(false));
    form.querySelector('[data-open-publish]')?.addEventListener('click', () => {
        if (!title.value.trim()) {
            title.focus();
            title.reportValidity();
            return;
        }
        openSettings(true);
    });
    settings.querySelector('[data-close-settings]').addEventListener('click', () => settings.close());
    settings.addEventListener('click', (e) => e.target === settings && settings.close()); // clic fuera

    settings.querySelector('[data-unpublish]')?.addEventListener('click', (e) => {
        if (!confirm('El post dejará de verse en el blog. ¿Continuar?')) e.preventDefault();
    });

    // --- Historial de versiones ---
    const history = document.querySelector('[data-history]');
    if (history) {
        const preview = history.querySelector('[data-history-preview]');
        const restoreForm = history.querySelector('[data-history-restore]');
        document.querySelector('[data-open-history]').addEventListener('click', () => history.showModal());
        history.querySelector('[data-close-history]').addEventListener('click', () => history.close());
        history.querySelectorAll('[data-revision]').forEach((btn) =>
            btn.addEventListener('click', async () => {
                history.querySelectorAll('[data-revision]').forEach((b) => b.toggleAttribute('data-active', b === btn));
                preview.innerHTML = '<p class="text-stone-400">Cargando…</p>';
                const r = await (await fetch(btn.dataset.url, { headers: { Accept: 'application/json' } })).json();
                preview.innerHTML = '';
                const h = document.createElement('h1');
                h.className = 'font-serif';
                h.textContent = r.title;
                preview.append(h);
                if (r.excerpt) preview.insertAdjacentHTML('beforeend', `<p class="lead"></p>`), (preview.lastChild.textContent = r.excerpt);
                preview.insertAdjacentHTML('beforeend', r.html); // HTML ya limpiado por el servidor
                restoreForm.action = btn.dataset.restore;
                restoreForm.classList.remove('hidden');
            }),
        );
        restoreForm.addEventListener('submit', (e) => {
            if (dirty && !confirm('Tienes cambios sin guardar que se perderán. ¿Restaurar igualmente?')) e.preventDefault();
            else dirty = false;
        });
    }

    // --- Guardar ---
    form.addEventListener('submit', (e) => {
        const body = field('body');
        if (!body.value.trim()) {
            e.preventDefault();
            settings.close();
            alert('Escribe algo en el post antes de guardarlo.');
            form.querySelector('[data-rich-editor] .tiptap')?.focus();
            return;
        }
        dirty = false;
        clearTimeout(autosaveTimer);
        try {
            localStorage.removeItem(draftKey);
        } catch {
            /* nada */
        }
    });

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            form.requestSubmit(form.querySelector('[data-save-default]'));
        }
    });

    window.addEventListener('beforeunload', (e) => {
        if (dirty) e.preventDefault();
    });
}
