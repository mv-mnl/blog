// Dibuja un gráfico del blog con Chart.js. Compartido por el editor (vista previa) y por los ajustes.
import Chart from 'chart.js/auto';

export const PALETTE = ['#f59e0b', '#0ea5e9', '#10b981', '#f43f5e', '#8b5cf6', '#14b8a6', '#f97316', '#64748b'];

export const CHART_TYPES = {
    bar: 'Barras',
    line: 'Líneas',
    area: 'Área',
    pie: 'Tarta',
    doughnut: 'Donut',
};

/** Convierte texto (pegado de Excel/Sheets o escrito a mano) en { series, rows }. */
export function parseChartText(text) {
    const lines = text.split(/\r?\n/).map((l) => l.trimEnd()).filter((l) => l.trim() !== '');
    if (!lines.length) return { series: [], rows: [] };
    const sep = lines[0].includes('\t') ? '\t' : lines[0].includes(';') ? ';' : ',';
    const num = (v) => {
        let s = String(v).trim().replace(/[%€$\s]/g, '');
        if (sep !== ',') s = s.replace(/\.(?=\d{3}(\D|$))/g, '').replace(',', '.');
        const n = Number(s);
        return s === '' || Number.isNaN(n) ? null : n;
    };
    const cells = lines.map((l) => l.split(sep).map((c) => c.trim()));
    const hasHeader = cells[0].slice(1).some((c) => num(c) === null);
    const header = hasHeader ? cells.shift() : null;
    const width = Math.max(2, ...cells.map((r) => r.length));
    const series = Array.from({ length: width - 1 }, (_, i) => header?.[i + 1] || (width === 2 ? 'Valor' : `Serie ${i + 1}`));
    const rows = cells.map((r) => [r[0] ?? '', ...series.map((_, i) => num(r[i + 1]))]);
    return { series, rows };
}

export function chartToText(chart) {
    if (!chart?.rows?.length) return '';
    return [['Etiqueta', ...chart.series].join(', '), ...chart.rows.map((r) => r.map((v) => v ?? '').join(', '))].join('\n');
}

const isDark = () => document.documentElement.classList.contains('dark') || (!document.documentElement.classList.contains('light') && matchMedia('(prefers-color-scheme: dark)').matches);

export function renderChart(canvas, chart) {
    Chart.getChart(canvas)?.destroy();
    if (!chart?.rows?.length) return null;

    const text = isDark() ? '#d6d3d1' : '#44403c';
    const grid = isDark() ? 'rgba(255,255,255,.08)' : 'rgba(0,0,0,.07)';
    const round = chart.type === 'pie' || chart.type === 'doughnut';
    const labels = chart.rows.map((r) => r[0]);

    const datasets = round
        ? [{ label: chart.series[0], data: chart.rows.map((r) => r[1]), backgroundColor: labels.map((_, i) => PALETTE[i % PALETTE.length]), borderWidth: 0 }]
        : chart.series.map((name, i) => ({
              label: name,
              data: chart.rows.map((r) => r[i + 1]),
              backgroundColor: chart.type === 'area' ? PALETTE[i % PALETTE.length] + '33' : PALETTE[i % PALETTE.length],
              borderColor: PALETTE[i % PALETTE.length],
              borderWidth: chart.type === 'bar' ? 0 : 2.5,
              borderRadius: chart.type === 'bar' ? 4 : 0,
              fill: chart.type === 'area',
              tension: 0.3,
              pointRadius: chart.type === 'bar' ? 0 : 3,
          }));

    return new Chart(canvas, {
        type: chart.type === 'area' ? 'line' : chart.type,
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 400 },
            plugins: {
                legend: { display: round || chart.series.length > 1, position: 'bottom', labels: { color: text, usePointStyle: true } },
                title: { display: !!chart.title, text: chart.title, color: text, font: { size: 15, weight: '600' }, padding: { bottom: 14 } },
                tooltip: { callbacks: { label: (c) => ` ${c.dataset.label ? c.dataset.label + ': ' : ''}${c.formattedValue}` } },
            },
            scales: round ? {} : {
                x: { ticks: { color: text }, grid: { display: false } },
                y: { beginAtZero: true, ticks: { color: text }, grid: { color: grid } },
            },
        },
    });
}
