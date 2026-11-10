# Blog

Blog hecho desde cero con **Laravel 13 + MySQL 8.4 + Tailwind 4**, desplegado con Docker.

Son **dos apps Laravel separadas** que comparten la misma base de datos:

| App | Carpeta | Para qué | Dónde escucha |
|---|---|---|---|
| **web** | `web/` | Lo que ven los visitantes (solo lectura) | Puerto `80`, abierto a la red |
| **admin** | `admin/` | Escribir y editar posts (sin login) | `127.0.0.1:8080`, solo dentro del servidor |

```
               red local                       servidor
  visitante ───────────────▶ :80  ┌───────┐
                                  │  web  │──SELECT──┐
                                  └───────┘          ▼
                                                 ┌───────┐
  tú ──túnel SSH──▶ 127.0.0.1:8080 ┌───────┐     │ mysql │
                                   │ admin │─────▶│       │
                                   └───────┘ todo └───────┘
                     volumen "uploads": admin escribe, web solo lee
```

## Seguridad: por qué el admin no necesita login

- El admin **solo escucha en `127.0.0.1` del servidor**, así que desde la red no se llega a él.
  Para usarlo abres un túnel SSH (ver abajo).
- La app web usa un **usuario de MySQL que solo puede leer**, y añadir filas en `comments` (quedan
  pendientes) y `page_views`. Aunque alguien encontrara un fallo en el blog público, no podría
  modificar ni borrar nada. El servicio `db-setup` aplica estos permisos en cada arranque.
- La web monta las imágenes en **solo lectura** y no tiene ninguna ruta para escribir.
- El HTML del editor se **limpia con un sanitizador** (symfony/html-sanitizer) al guardar y otra vez al mostrar:
  sin `<script>`, estilos, eventos `on…` ni enlaces `javascript:`.
- Los contenedores corren como el usuario `www-data`, no como root.

## Funciones

**Escribir (admin)**
- **Editor visual** tipo Medium con una sola barra de formato arriba; `Ctrl+S` guarda, `Ctrl+K` enlace
- Menú **+ Insertar**: imagen (con pie de foto y texto alternativo), vídeo de YouTube (o pega el enlace),
  tabla, **gráfico** (barras, líneas, área, tarta, donut; pega datos de Excel/Sheets), **diagrama** (Mermaid),
  bloque de **aviso** (nota, consejo, cuidado, peligro) y línea separadora
- **Código con colores** y selector de lenguaje
- **Imágenes optimizadas** al subirlas: máx. 1600 px (portadas 2000), WebP, sin EXIF/GPS
- **Autoguardado** en el navegador: si se cierra la pestaña, al volver ofrece recuperar los cambios
- **Historial de versiones**: cada cambio guarda la versión anterior; se puede ver y restaurar
- Borrador/Publicado con **fecha automática** y **fecha de edición**; **actualizaciones** como mini-posts
- **Series** («Parte 1, 2, 3…»), etiquetas, URL editable — en el panel Ajustes
- **Comentarios**: moderación (aprobar / spam / eliminar) con contador de pendientes
- **Estadísticas** propias sin cookies: visitas, visitantes, posts más leídos y de dónde llegan

**Leer (web)**
- Índice automático en posts con varias secciones, posts relacionados, navegación de series
- Gráficos interactivos, diagramas, vídeos sin cookies (youtube-nocookie), código con colores
  (cada librería se descarga solo en los posts que la usan)
- Botón de tema automático / claro / oscuro, búsqueda, etiquetas, paginación, RSS
- Comentarios con antispam sin captchas (campo trampa, tiempo mínimo, límite por visitante)

## Instalar en el servidor

Requisitos: Docker con Docker Compose.

```bash
git clone <tu-repo> blog && cd blog     # o copia la carpeta
cp .env.example .env
nano .env                               # BLOG_URL con la IP del servidor, claves y contraseñas
```

Genera las claves de Laravel y las contraseñas:

```bash
echo "base64:$(openssl rand -base64 32)"   # para WEB_APP_KEY y otra para ADMIN_APP_KEY
openssl rand -hex 24                        # para cada contraseña de MySQL
```

Arranca:

```bash
docker compose up -d --build
```

La primera vez, MySQL crea la base de datos y el usuario de solo lectura, y el admin aplica las migraciones.
La web espera a que el admin esté listo.

## Escribir posts

Desde tu PC, abre un túnel al servidor y deja esa terminal abierta:

```bash
ssh -L 8080:localhost:8080 usuario@IP_DEL_SERVIDOR
```

Ahora entra en **http://localhost:8080** desde tu navegador. El blog público está en `http://IP_DEL_SERVIDOR`.

### Admin accesible desde toda la LAN (sin túnel)

En `.env` pon `ADMIN_BIND=0.0.0.0` y entra desde cualquier equipo de la red a `http://IP_DEL_SERVIDOR:ADMIN_PORT`.
El admin rechaza (403) cualquier IP que no sea de red local, pero **no tiene login**: cualquiera conectado a tu
red (incluida la wifi de invitados) puede editar. **No lo publiques** con un túnel (Cloudflare, ngrok) ni con un
proxy: las peticiones llegarían desde una IP interna y el filtro no serviría.

> Si estás trabajando en el propio servidor (o en tu PC en local), entra directo a http://localhost:8080.

## Datos de prueba

```bash
docker compose exec admin php artisan db:seed --force                 # solo si no hay posts
docker compose exec admin php artisan migrate:fresh --seed --force    # ⚠️ BORRA todo y recarga
```

Carga 11 posts (uno de muestra con todos los bloques), actualizaciones, una serie, comentarios
(aprobados, pendientes y spam), 60 días de visitas y versiones en el historial.

## Comandos útiles

```bash
docker compose ps                         # estado
docker compose logs -f web admin          # logs
docker compose up -d --build              # aplicar cambios de código
docker compose exec admin php artisan migrate:status
docker compose down                       # parar (los datos se conservan)
```

### Copias de seguridad (automáticas)

El servicio `backup` guarda cada día (a las `BACKUP_HOUR`, por defecto 3:00) en `./backups/`:
`blog-FECHA.sql.gz` (base de datos) e `imagenes-FECHA.tar.gz`, y borra las de más de `BACKUP_KEEP_DAYS` días.

```bash
docker compose exec backup bash /backup.sh ahora     # copia manual ahora mismo
```

Restaurar:
```bash
set -a; . ./.env; set +a
gunzip -c backups/blog-FECHA.sql.gz | docker compose exec -T mysql mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"
docker run --rm -v blog_uploads:/data -v "$PWD/backups":/b alpine tar xzf /b/imagenes-FECHA.tar.gz -C /data
```

> Copia también `./backups` fuera del servidor (otro disco, otro equipo) de vez en cuando.

## Cambios en la base de datos

El esquema lo controla **solo la app admin** (`admin/database/migrations`). La app web
no tiene migraciones; si añades una columna, añade también lo necesario en `web/app/Models`.
Las migraciones se aplican solas al arrancar el contenedor admin.

> Si añades una tabla en la que la web deba escribir, añade su `GRANT INSERT` en `docker/db-setup.sh`.

## Estructura

```
blog/
├── docker-compose.yml        # los 3 servicios
├── .env                      # configuración y secretos (no se sube a git)
├── backups/                  # copias de seguridad diarias (no se sube a git)
├── docker/db-setup.sh        # permisos mínimos del usuario de la web
├── docker/backup.sh          # copias de seguridad
├── admin/                    # Laravel: panel de escritura
│   ├── app/Http/Controllers/PostController.php
│   ├── app/Models/{Post,Tag}.php
│   ├── database/migrations/  # dueño del esquema
│   ├── resources/views/      # layout, posts/index, posts/form
│   ├── resources/js/app.js   # vista previa y subida de imágenes
│   └── Dockerfile
└── web/                      # Laravel: blog público
    ├── app/Http/Controllers/BlogController.php
    ├── app/Models/{Post,Tag}.php
    ├── resources/views/      # layout, blog/*, errors/404
    └── Dockerfile
```

Cada `Dockerfile` compila Tailwind con Node en una etapa previa y la imagen final solo lleva
PHP 8.4 + FrankenPHP (servidor web) + el código.
