// Ventana modal sencilla para editar bloques (gráficos, diagramas).
export function openDialog({ title, body, onSave, saveLabel = 'Guardar', wide = true }) {
    const dlg = document.createElement('dialog');
    dlg.className = `m-auto w-full ${wide ? 'max-w-4xl' : 'max-w-lg'} rounded-xl border border-stone-200 bg-white p-0 text-stone-900 shadow-2xl backdrop:bg-stone-950/40 dark:border-stone-800 dark:bg-stone-900 dark:text-stone-100`;
    dlg.innerHTML = `
        <div class="border-b border-stone-200 px-6 py-4 dark:border-stone-800"><h2 class="text-lg font-bold"></h2></div>
        <div class="p-6" data-body></div>
        <div class="flex justify-end gap-2 rounded-b-xl border-t border-stone-200 bg-stone-50 px-6 py-4 dark:border-stone-800 dark:bg-stone-950">
            <button type="button" data-cancel class="rounded-md px-4 py-2 text-sm hover:bg-stone-200 dark:hover:bg-stone-800">Cancelar</button>
            <button type="button" data-save class="rounded-md bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-400"></button>
        </div>`;
    dlg.querySelector('h2').textContent = title;
    dlg.querySelector('[data-save]').textContent = saveLabel;
    dlg.querySelector('[data-body]').append(body);
    document.body.append(dlg);
    const close = () => {
        dlg.close();
        dlg.remove();
    };
    dlg.querySelector('[data-cancel]').addEventListener('click', close);
    dlg.addEventListener('cancel', close);
    dlg.querySelector('[data-save]').addEventListener('click', () => {
        if (onSave() !== false) close();
    });
    dlg.showModal();
    return dlg;
}

export const blockButtons = (onEdit, onDelete) => {
    const bar = document.createElement('div');
    bar.contentEditable = 'false';
    bar.className = 'absolute top-2 right-2 z-10 flex gap-1 opacity-0 transition group-hover:opacity-100';
    bar.innerHTML = `
        <button type="button" data-edit class="rounded bg-white/95 px-2.5 py-1 text-xs font-medium text-stone-800 shadow hover:bg-white">Editar</button>
        <button type="button" data-del class="rounded bg-white/95 px-2.5 py-1 text-xs font-medium text-red-700 shadow hover:bg-white">Quitar</button>`;
    bar.querySelector('[data-edit]').addEventListener('click', onEdit);
    bar.querySelector('[data-del]').addEventListener('click', onDelete);
    return bar;
};
