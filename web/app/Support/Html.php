<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * El contenido de los posts se guarda como HTML (lo genera el editor visual).
 * Todo HTML pasa por aquí antes de guardarse y antes de mostrarse: solo se
 * permiten etiquetas de texto seguras, sin scripts, estilos, eventos ni iframes.
 *
 * Bloques especiales (se guardan como datos, nunca como código ejecutable):
 *   <figure data-chart="{json}">            gráfico (lo dibuja Chart.js en la web)
 *   <pre data-mermaid>texto</pre>           diagrama (lo dibuja Mermaid en la web)
 *   <div data-youtube="ID"></div>           vídeo (la web genera el iframe)
 *   <aside data-callout="note|tip|warning|danger">  bloque de aviso
 *   <code class="language-php">             lenguaje para colorear el código
 */
class Html
{
    public const CALLOUTS = ['note', 'tip', 'warning', 'danger'];

    public const CHART_TYPES = ['bar', 'line', 'pie', 'doughnut', 'area'];

    private static ?HtmlSanitizer $sanitizer = null;

    public static function clean(?string $html): string
    {
        self::$sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->allowLinkSchemes(['http', 'https', 'mailto'])
                ->allowMediaSchemes(['http', 'https'])
                ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
                ->allowAttribute('data-chart', ['figure'])
                ->allowAttribute('data-mermaid', ['pre'])
                ->allowAttribute('data-youtube', ['div'])
                ->allowAttribute('data-callout', ['aside'])
                ->allowAttribute('class', ['code'])
                ->withMaxInputLength(5_000_000)
        );

        $html = self::$sanitizer->sanitize((string) $html);

        // Validar el valor de cada atributo especial (lo que no encaje, se elimina).
        $html = preg_replace_callback('/<code class="([^"]*)"/', fn ($m) => preg_match('/^language-[a-z0-9+#-]{1,30}$/', $m[1]) ? $m[0] : '<code', $html);
        $html = preg_replace_callback('/<div data-youtube="([^"]*)"/', fn ($m) => preg_match('/^[A-Za-z0-9_-]{11}$/', $m[1]) ? $m[0] : '<div', $html);
        $html = preg_replace_callback('/<aside data-callout="([^"]*)"/', fn ($m) => in_array($m[1], self::CALLOUTS, true) ? $m[0] : '<aside data-callout="note"', $html);
        $html = preg_replace_callback('/<figure data-chart="([^"]*)"/', function ($m) {
            $chart = self::normalizeChart(json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true));

            return $chart ? '<figure data-chart="'.e(json_encode($chart, JSON_UNESCAPED_UNICODE)).'"' : '<figure';
        }, $html);

        // Bloques que quedaron vacíos (vídeo con ID inválido, gráfico sin datos).
        $html = str_replace(['<div></div>', '<figure></figure>'], '', $html);

        return self::compact($html);
    }

    /** Deja un gráfico solo con tipo, título, series (nombre) y filas (etiqueta + números). */
    public static function normalizeChart(mixed $c): ?array
    {
        if (! is_array($c) || ! in_array($c['type'] ?? null, self::CHART_TYPES, true)) {
            return null;
        }
        $series = array_slice(array_map(fn ($s) => Str::limit((string) $s, 60, ''), (array) ($c['series'] ?? [])), 0, 8);
        $rows = [];
        foreach (array_slice((array) ($c['rows'] ?? []), 0, 200) as $row) {
            if (! is_array($row) || ! $row) {
                continue;
            }
            $label = Str::limit((string) array_shift($row), 80, '');
            $values = array_map(fn ($v) => is_numeric($v) ? $v + 0 : null, array_slice($row, 0, max(1, count($series))));
            $rows[] = [$label, ...$values];
        }

        return $rows ? [
            'type' => $c['type'],
            'title' => Str::limit((string) ($c['title'] ?? ''), 120, ''),
            'series' => $series ?: ['Valor'],
            'rows' => $rows,
        ] : null;
    }

    /** Quita los saltos de línea alrededor de etiquetas de bloque (no toca los espacios entre palabras). */
    public static function compact(string $html): string
    {
        $block = 'p|h[1-6]|ul|ol|li|blockquote|pre|hr|div|table|thead|tbody|tr|td|th|figure|figcaption|aside|colgroup|col';

        return trim(preg_replace('#\s*(</?(?:'.$block.')\b[^>]*>)\s*#i', '$1', $html));
    }

    /**
     * "Huella" del contenido: texto + enlaces, imágenes y bloques especiales. Sirve para saber
     * si un post cambió de verdad, sin que cuenten diferencias internas del HTML del editor.
     */
    public static function fingerprint(?string $html): string
    {
        preg_match_all('#(?:href|src|data-chart|data-youtube|data-callout|alt)="([^"]*)"#i', (string) $html, $m);

        return self::text($html).'|'.implode('|', $m[1]);
    }

    /** ¿No hay ni texto ni imágenes ni bloques? (el editor vacío envía "<p></p>") */
    public static function isEmpty(?string $html): bool
    {
        $html = (string) $html;

        return trim(strip_tags($html, '<img>')) === '' && ! preg_match('/data-(chart|youtube)=/', $html);
    }

    /** Texto plano, para búsquedas, resúmenes y tiempo de lectura. */
    public static function text(?string $html): string
    {
        $text = strip_tags(str_replace('<', ' <', (string) $html));

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5)));
    }

    public static function fromMarkdown(string $markdown): string
    {
        return self::clean(Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }

    /**
     * HTML listo para mostrar en el blog: limpio, con los vídeos convertidos en reproductor
     * y con id en los títulos. Devuelve [html, índice] donde índice = [[nivel, id, texto], …].
     */
    public static function render(?string $html, string $idPrefix = ''): array
    {
        $html = self::clean($html);

        $html = preg_replace_callback('/<div data-youtube="([A-Za-z0-9_-]{11})"><\/div>/', fn ($m) => '<div class="video-embed"><iframe src="https://www.youtube-nocookie.com/embed/'.$m[1].'" title="Vídeo de YouTube" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>', $html);

        $toc = [];
        $used = [];
        $html = preg_replace_callback('/<h([23])>(.*?)<\/h\1>/s', function ($m) use (&$toc, &$used, $idPrefix) {
            $text = self::text($m[2]);
            $id = $idPrefix.(Str::slug($text) ?: 'seccion');
            $base = $id;
            for ($n = 2; isset($used[$id]); $n++) {
                $id = "$base-$n";
            }
            $used[$id] = true;
            $toc[] = [(int) $m[1], $id, $text];

            return "<h{$m[1]} id=\"{$id}\">{$m[2]}</h{$m[1]}>";
        }, $html);

        return [$html, $toc];
    }
}
