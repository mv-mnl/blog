<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Series;
use App\Models\Tag;
use App\Support\Html;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Datos de prueba: posts publicados, editados y borradores, con etiquetas y portadas.
 *
 *   docker compose exec admin php artisan db:seed --force
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Post::exists()) {
            $this->command->warn('Ya hay posts en la base de datos; no se cargan datos de prueba.');
            $this->command->line('Para empezar de cero: docker compose exec admin php artisan migrate:fresh --seed --force');

            return;
        }

        foreach ($this->posts() as $data) {
            $published = $data['days_ago'] === null ? null : now()->subDays($data['days_ago'])->setTime(rand(8, 21), rand(0, 59));
            $edited = $published && isset($data['edited_days_later'])
                ? $published->copy()->addDays($data['edited_days_later'])->addHours(rand(1, 6))
                : null;

            $post = new Post([
                'title' => $data['title'],
                'slug' => Post::uniqueSlug($data['title']),
                'excerpt' => $data['excerpt'],
                'body' => isset($data['html'])
                    ? Html::clean(str_replace('{{FIGURA}}', '<figure><img src="/uploads/'.$this->makeCover('#0ea5e9', '#6366f1').'" alt="Degradado de azul a violeta"><figcaption>Un pie de foto de ejemplo: así se ven bajo cada imagen.</figcaption></figure>', $data['html']))
                    : Html::fromMarkdown(trim($data['body'])),
                'series_id' => Series::idFromTitle($data['series'] ?? null),
                'published_at' => $published,
                'edited_at' => $edited,
                'cover_image' => isset($data['cover']) ? $this->makeCover(...$data['cover']) : null,
            ]);
            $created = ($published ?? now()->subDays(rand(0, 3)))->copy()->subHours(rand(2, 48));
            $post->created_at = $created;
            $post->updated_at = $edited ?? $published ?? $created;
            $post->save();

            $post->tags()->sync(Tag::idsFromString($data['tags']));

            foreach ($data['updates'] ?? [] as $u) {
                [$daysAfter, $title, $text] = $u;
                $update = $post->updates()->make([
                    'title' => $title,
                    'body' => Html::fromMarkdown(trim($text)),
                    'cover_image' => isset($u[3]) ? $this->makeCover(...$u[3]) : null,
                ]);
                $update->created_at = $update->updated_at = $published->copy()->addDays($daysAfter)->setTime(rand(8, 22), rand(0, 59));
                $update->save();
            }
            $post->refreshSearchText();
        }

        $this->seedRevisions();
        $this->seedComments();
        $this->seedViews();

        $this->command->info(sprintf(
            'Cargados %d posts, %d actualizaciones, %d etiquetas, %d series, %d comentarios, %d visitas y %d versiones en el historial.',
            Post::count(), \App\Models\PostUpdate::count(), Tag::count(), Series::count(), Comment::count(), DB::table('page_views')->count(), DB::table('post_revisions')->count(),
        ));
    }

    /** Versiones antiguas para los posts editados (así el historial tiene algo que mostrar). */
    private function seedRevisions(): void
    {
        foreach (Post::whereNotNull('edited_at')->get() as $post) {
            $paragraphs = preg_split('#(?=<h2|<p>)#', $post->body, -1, PREG_SPLIT_NO_EMPTY);
            $older = implode('', array_slice($paragraphs, 0, max(1, count($paragraphs) - 2)));
            $rev = $post->revisions()->create(['title' => $post->title, 'excerpt' => $post->excerpt, 'body' => $older]);
            $rev->forceFill(['created_at' => $post->edited_at->copy()->subMinutes(1)])->save();
        }
    }

    private function seedComments(): void
    {
        $comments = [
            ['docker-compose-para-principiantes', 'Laura', "¡Muy claro! Por fin entiendo para qué sirven los volúmenes.", 'approved', 50],
            ['docker-compose-para-principiantes', 'Carlos M.', "¿Podrías hacer uno sobre redes personalizadas?\nGracias por el post.", 'approved', 49],
            ['tailwind-css-amor-a-la-segunda-vista', 'Ana', 'Me pasó exactamente lo mismo con Tailwind 😅', 'approved', 30],
            ['tailwind-css-amor-a-la-segunda-vista', 'Diego', 'Yo sigo prefiriendo CSS normal, pero entiendo el punto.', 'pending', 2],
            ['busqueda-de-texto-completo-con-mysql', 'Marta', '¿Funciona igual con PostgreSQL?', 'pending', 1],
            ['separar-el-admin-del-blog-publico', 'Pablo', 'Buena idea lo del usuario de solo lectura, lo voy a copiar.', 'approved', 4],
            ['bienvenido-a-mi-blog', 'casino-bonus-123', 'Gana dinero rápido >>> http://spam.example', 'spam', 5],
        ];
        foreach ($comments as [$slug, $name, $body, $status, $daysAgo]) {
            $post = Post::where('slug', $slug)->first();
            if (! $post) {
                continue;
            }
            $c = new Comment;
            $c->forceFill([
                'post_id' => $post->id, 'name' => $name, 'body' => $body, 'status' => $status,
                'created_at' => now()->subDays($daysAgo)->setTime(rand(9, 22), rand(0, 59)),
            ])->save();
        }
    }

    /** 60 días de visitas inventadas para que la página de estadísticas tenga algo que enseñar. */
    private function seedViews(): void
    {
        $posts = Post::whereNotNull('published_at')->get(['id', 'slug', 'published_at']);
        $referrers = [null, null, null, null, 'www.google.com', 'www.google.com', 'duckduckgo.com', 'news.ycombinator.com', 'twitter.com', 'www.reddit.com', 'github.com'];
        $rows = [];
        for ($d = 59; $d >= 0; $d--) {
            $day = now()->subDays($d);
            $visitors = array_map(fn () => substr(md5(random_bytes(8)), 0, 16), range(1, rand(8, 20) + (60 - $d) / 3));
            foreach ($visitors as $v) {
                foreach (range(1, rand(1, 3)) as $_) {
                    $available = $posts->filter(fn ($p) => $p->published_at->lte($day));
                    $post = rand(0, 4) && $available->isNotEmpty() ? $available->random() : null;
                    if (! $post && rand(0, 1)) {
                        continue;
                    }
                    $rows[] = [
                        'post_id' => $post?->id,
                        'path' => $post ? '/posts/'.$post->slug : '/',
                        'referrer_host' => $referrers[array_rand($referrers)],
                        'visitor' => $v,
                        'created_at' => $day->copy()->setTime(rand(7, 23), rand(0, 59)),
                    ];
                }
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('page_views')->insert($chunk);
        }
    }

    /** Genera una portada PNG 1200×600 con un degradado diagonal (sin dependencias extra). */
    private function makeCover(string $from, string $to): string
    {
        [$w, $h] = [1200, 600];
        $a = sscanf($from, '#%02x%02x%02x');
        $b = sscanf($to, '#%02x%02x%02x');

        $raw = '';
        for ($y = 0; $y < $h; $y++) {
            $raw .= "\0";
            for ($x = 0; $x < $w; $x++) {
                $t = ($x / $w) * 0.7 + ($y / $h) * 0.3;
                $raw .= chr((int) ($a[0] + ($b[0] - $a[0]) * $t))
                    .chr((int) ($a[1] + ($b[1] - $a[1]) * $t))
                    .chr((int) ($a[2] + ($b[2] - $a[2]) * $t));
            }
        }

        $chunk = fn (string $type, string $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $png = "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($raw, 6))
            .$chunk('IEND', '');

        $path = date('Y/m').'/seed-'.Str::random(12).'.png';
        Storage::disk('uploads')->put($path, $png);

        return $path;
    }

    private function posts(): array
    {
        return [
            [
                'title' => 'Bienvenido a mi blog',
                'series' => 'Construyendo este blog',
                'excerpt' => 'Por qué decidí construir mi propio blog desde cero en lugar de usar una plataforma.',
                'tags' => 'personal, laravel',
                'days_ago' => 60,
                'cover' => ['#f59e0b', '#b45309'],
                'body' => <<<'MD'
Hola 👋. Este es el primer post de un blog que construí **desde cero**.

Podría haber usado WordPress, Ghost o Medium, pero quería entender cada pieza:
cómo se guardan los posts, cómo se sirven, cómo se despliega todo.

## Qué vas a encontrar aquí

- Notas de lo que voy aprendiendo
- Proyectos personales
- Algún que otro tutorial

> La mejor forma de aprender algo es explicárselo a otra persona.

Nos leemos pronto.
MD,
            ],
            [
                'title' => 'Docker Compose para principiantes',
                'series' => 'Construyendo este blog',
                'excerpt' => 'Servicios, volúmenes y redes explicados con un ejemplo real: este mismo blog.',
                'tags' => 'docker, tutorial',
                'days_ago' => 52,
                'edited_days_later' => 3,
                'cover' => ['#0ea5e9', '#1e3a8a'],
                'body' => <<<'MD'
Docker Compose te deja describir varios contenedores en un solo archivo y levantarlos con un comando.

## Lo mínimo

```yaml
services:
  web:
    image: nginx:alpine
    ports:
      - "80:80"
```

```bash
docker compose up -d
```

## Volúmenes

Los datos dentro de un contenedor desaparecen al borrarlo. Un **volumen** los guarda fuera:

```yaml
volumes:
  - mysql_data:/var/lib/mysql
```

## Redes

Todos los servicios de un mismo `docker-compose.yml` se ven entre sí por su nombre.
Por eso Laravel se conecta a la base de datos con `DB_HOST=mysql`.

**Actualización:** añadí la sección de redes después de que me preguntaran por ella.
MD,
            ],
            [
                'title' => 'Laravel: rutas, controladores y vistas',
                'excerpt' => 'El recorrido completo de una petición en Laravel, desde la URL hasta el HTML.',
                'tags' => 'laravel, php, tutorial',
                'days_ago' => 41,
                'cover' => ['#ef4444', '#7f1d1d'],
                'updates' => [
                    [20, 'Bonus: Route Model Binding', <<<'MD'
Me escribieron preguntando por los **Route Model Bindings**. En lugar de buscar el post a mano:

```php
Route::get('/etiqueta/{tag:slug}', [BlogController::class, 'tag']);

public function tag(Tag $tag) { /* Laravel ya lo buscó por slug */ }
```

Es más limpio y te da el 404 gratis.
MD],
                ],
                'body' => <<<'MD'
Cuando visitas `/posts/mi-post`, Laravel hace tres cosas:

1. **La ruta** decide qué código ejecutar.
2. **El controlador** busca los datos.
3. **La vista** los convierte en HTML.

## La ruta

```php
Route::get('/posts/{slug}', [BlogController::class, 'show']);
```

## El controlador

```php
public function show(string $slug)
{
    $post = Post::published()->where('slug', $slug)->firstOrFail();

    return view('blog.show', compact('post'));
}
```

`firstOrFail()` devuelve un 404 si el post no existe. Cómodo.

## La vista

```blade
<h1>{{ $post->title }}</h1>
{!! $post->html() !!}
```

Las llaves dobles escapan el HTML; `{!! !!}` no, así que úsalo solo con contenido de confianza.
MD,
            ],
            [
                'title' => 'Tailwind CSS: amor a la segunda vista',
                'excerpt' => 'Al principio odiaba las clases utilitarias. Ahora no sé vivir sin ellas.',
                'tags' => 'css, tailwind, frontend',
                'days_ago' => 33,
                'updates' => [
                    [9, 'Matizo: los componentes largos', <<<'MD'
Después de usarlo en un proyecto más grande, matizo: **los componentes largos se vuelven difíciles de leer**.
Una lista de 15 clases en un `div` no es bonita.

Lo que me funciona es extraer componentes de Blade (`<x-boton>`) en lugar de repetir clases.
MD],
                    [27, 'Mi opinión final, un mes después', <<<'MD'
Un mes después, mi opinión final: **sí, me quedo con Tailwind**, pero ya no lo defiendo como la solución a todo.

- Para apps con muchos componentes: genial.
- Para un post largo con mucho texto: el plugin `typography` hace el 90 % del trabajo.
- Para algo muy pequeño: CSS normal sigue siendo perfectamente válido.

> Antes pensaba que era una broma. Luego que era magia. Ahora creo que es solo una buena herramienta.
MD, ['#14b8a6', '#134e4a']],
                ],
                'body' => <<<'MD'
La primera vez que vi esto pensé que era una broma:

```html
<button class="rounded-md bg-amber-500 px-4 py-2 font-semibold hover:bg-amber-400">
  Guardar
</button>
```

¿Tanto texto para un botón? Pero después de un par de semanas me di cuenta de que:

- **No tengo que inventar nombres** de clases.
- **No hay CSS muerto**: Tailwind solo incluye lo que uso.
- **Los cambios son locales**: tocar un botón no rompe otro.

El modo oscuro es tan fácil como `dark:bg-stone-900`. Y con el plugin
`@tailwindcss/typography`, los artículos se ven bien sin escribir CSS.
MD,
            ],
            [
                'title' => 'Mi setup de desarrollo en 2026',
                'excerpt' => 'Sistema, editor, terminal y las herramientas que uso todos los días.',
                'tags' => 'personal, herramientas',
                'days_ago' => 24,
                'edited_days_later' => 10,
                'cover' => ['#8b5cf6', '#312e81'],
                'body' => <<<'MD'
Me preguntan seguido qué uso, así que aquí va.

| Qué | Herramienta |
|---|---|
| Sistema | Linux (CachyOS) |
| Shell | zsh |
| Contenedores | Docker + Compose |
| Base de datos | MySQL 8.4 |

## Lo que más me ha cambiado la vida

Tener **todo en contenedores**. No instalo PHP ni MySQL en mi máquina:
cada proyecto trae su propio entorno y se borra sin dejar rastro.

*Editado: actualicé la tabla con el cambio de shell.*
MD,
            ],
            [
                'title' => 'Búsqueda de texto completo con MySQL',
                'excerpt' => 'Cómo funciona el buscador de este blog sin Elasticsearch ni servicios externos.',
                'tags' => 'mysql, laravel',
                'days_ago' => 15,
                'cover' => ['#10b981', '#064e3b'],
                'body' => <<<'MD'
Para un blog pequeño no hace falta Elasticsearch. MySQL trae índices **FULLTEXT**.

## El índice

```php
$table->fullText(['title', 'excerpt', 'body']);
```

## La consulta

```php
Post::whereFullText(['title', 'excerpt', 'body'], '+docker* +volumen*', ['mode' => 'boolean']);
```

En modo booleano, `+` obliga a que aparezca la palabra y `*` acepta prefijos:
`docker*` encuentra «docker», «dockerfile» y «dockerizar».

> Ojo: por defecto InnoDB ignora palabras de menos de 3 letras.
MD,
            ],
            [
                'title' => 'Separar el admin del blog público',
                'series' => 'Construyendo este blog',
                'excerpt' => 'Dos apps Laravel, una base de datos y un usuario de solo lectura. Por qué y cómo.',
                'tags' => 'seguridad, laravel, docker',
                'days_ago' => 6,
                'edited_days_later' => 1,
                'updates' => [
                    [4, 'Una capa más: imágenes en solo lectura', <<<'MD'
Al final añadí una cosa más: las imágenes las comparten las dos apps con un volumen de Docker,
pero **la web lo monta en solo lectura** (`:ro`). Otra capa más por si acaso.
MD],
                ],
                'body' => <<<'MD'
Este blog son **dos aplicaciones distintas**:

- `web`: lo que ves tú. Solo lee de la base de datos.
- `admin`: donde escribo. No está expuesta a internet.

## ¿Por qué?

Si alguien encontrara un fallo en la parte pública, **no podría modificar nada**:
el usuario de MySQL de `web` solo tiene permiso `SELECT`.

```sql
GRANT SELECT ON blog.* TO 'blog_web'@'%';
```

Y el admin no necesita login porque solo escucha en `127.0.0.1`.
Para usarlo abro un túnel SSH:

```bash
ssh -L 8080:localhost:8080 yo@mi-servidor
```

Menos código, menos superficie de ataque.
MD,
            ],
            [
                'title' => 'Chuleta de formato',
                'excerpt' => 'Todo el formato que puedes usar en los posts, en un solo lugar.',
                'tags' => 'tutorial, escritura',
                'days_ago' => 2,
                'cover' => ['#ec4899', '#831843'],
                'body' => <<<'MD'
## Texto

**Negrita**, *cursiva*, ~~tachado~~ y `código en línea`.

## Listas

- Uno
- Dos
  - Dos y medio

1. Primero
2. Segundo

- [x] Tarea hecha
- [ ] Tarea pendiente

## Enlaces

[Documentación de Laravel](https://laravel.com/docs)

## Citas

> El código se lee muchas más veces de las que se escribe.

## Código

```js
const saludo = (nombre) => `Hola, ${nombre}`;
```

---

Y eso es todo.
MD,
            ],
            [
                'title' => 'Gráficos, diagramas y todo lo que puede llevar un post',
                'excerpt' => 'Un recorrido por los bloques del editor: gráficos, diagramas, tablas, avisos, código con colores y más.',
                'tags' => 'escritura, tutorial',
                'days_ago' => 1,
                'cover' => ['#6366f1', '#1e1b4b'],
                'html' => <<<'HTML'
<p>Este post sirve de muestra de todo lo que se puede meter en un post desde el editor visual, con el menú <strong>+ Insertar</strong>.</p>
<h2>Gráficos</h2>
<p>Se crean pegando datos (también desde Excel o Google Sheets) y en el blog son interactivos: pasa el ratón por encima.</p>
<figure data-chart='{"type":"bar","title":"Lenguajes que más uso","series":["Uso (%)"],"rows":[["PHP",45],["JavaScript",30],["Python",18],["Go",7]]}'></figure>
<p>Con varias series y en forma de área:</p>
<figure data-chart='{"type":"area","title":"Visitas al blog por mes","series":["Visitas","Visitantes"],"rows":[["Jul",320,210],["Ago",410,260],["Sep",580,350],["Oct",760,470],["Nov",1020,610]]}'></figure>
<figure data-chart='{"type":"doughnut","title":"¿Dónde leo?","series":["Lectores"],"rows":[["Móvil",58],["Ordenador",36],["Tablet",6]]}'></figure>
<h2>Diagramas</h2>
<p>Se escriben como texto y se dibujan solos. Así funciona este blog:</p>
<pre data-mermaid>flowchart LR
    V[Visitante] -->|lee| W[Web]
    Y[Yo] -->|túnel SSH| A[Admin]
    W -->|SELECT| M[(MySQL)]
    A -->|todo| M
    B[Backup] -->|cada noche| M</pre>
<h2>Tablas</h2>
<table><tbody><tr><th><p>Contenedor</p></th><th><p>Para qué</p></th><th><p>Expuesto</p></th></tr><tr><td><p>web</p></td><td><p>Blog público</p></td><td><p>Puerto 80</p></td></tr><tr><td><p>admin</p></td><td><p>Escribir posts</p></td><td><p>Solo localhost</p></td></tr><tr><td><p>mysql</p></td><td><p>Base de datos</p></td><td><p>No</p></td></tr><tr><td><p>backup</p></td><td><p>Copias diarias</p></td><td><p>No</p></td></tr></tbody></table>
<h2>Avisos</h2>
<aside data-callout="note"><p>Las notas sirven para aclarar algo sin cortar el hilo del texto.</p></aside>
<aside data-callout="tip"><p>Pega un enlace de YouTube en el editor y se convierte en vídeo automáticamente.</p></aside>
<aside data-callout="warning"><p>Si cambias la URL de un post publicado, los enlaces antiguos dejarán de funcionar.</p></aside>
<aside data-callout="danger"><p><code>migrate:fresh</code> borra toda la base de datos. Úsalo solo con datos de prueba.</p></aside>
<h2>Código con colores</h2>
<pre><code class="language-php">Route::get('/posts/{slug}', function (string $slug) {
    return Post::published()->where('slug', $slug)->firstOrFail();
});</code></pre>
<pre><code class="language-bash"># Copia de seguridad manual
docker compose exec backup bash /backup.sh ahora</code></pre>
<pre><code class="language-yaml">services:
  web:
    build: ./web
    ports:
      - "80:80"</code></pre>
<h3>Imágenes con pie de foto</h3>
<p>Al subir una imagen se optimiza sola (WebP, tamaño adecuado y sin datos de ubicación) y puedes añadirle un pie de foto y una descripción.</p>
{{FIGURA}}
HTML,
            ],
            [
                'title' => 'Ideas para próximos posts',
                'excerpt' => 'Borrador: lista de temas pendientes.',
                'tags' => 'personal',
                'days_ago' => null,
                'body' => <<<'MD'
- Backups automáticos con cron
- HTTPS con Caddy
- Tests en Laravel
MD,
            ],
            [
                'title' => 'Colas y jobs en Laravel (a medio escribir)',
                'excerpt' => null,
                'tags' => 'laravel',
                'days_ago' => null,
                'body' => <<<'MD'
Las colas sirven para hacer trabajo pesado fuera de la petición…

TODO: ejemplo con un job que redimensiona imágenes.
MD,
            ],
        ];
    }
}
