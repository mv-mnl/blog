#!/usr/bin/env bash
# Copias de seguridad automáticas: base de datos + imágenes.
#   Cada día a las BACKUP_HOUR (hora local) → ./backups/
#   Se borran las de más de BACKUP_KEEP_DAYS días.
# Copia manual ahora mismo:   docker compose exec backup bash /backup.sh ahora
set -euo pipefail

HOUR="${BACKUP_HOUR:-3}"
KEEP="${BACKUP_KEEP_DAYS:-14}"

run_backup() {
    local stamp; stamp="$(date +%Y-%m-%d_%H%M)"
    echo "[$(date '+%F %T')] Copia de seguridad ${stamp}…"

    mysqldump -h mysql -uroot --single-transaction --routines --no-tablespaces \
        --default-character-set=utf8mb4 "${DB}" | gzip -9 > "/backups/blog-${stamp}.sql.gz.tmp"
    mv "/backups/blog-${stamp}.sql.gz.tmp" "/backups/blog-${stamp}.sql.gz"

    tar -czf "/backups/imagenes-${stamp}.tar.gz.tmp" -C /uploads .
    mv "/backups/imagenes-${stamp}.tar.gz.tmp" "/backups/imagenes-${stamp}.tar.gz"

    find /backups -maxdepth 1 -type f \( -name 'blog-*.sql.gz' -o -name 'imagenes-*.tar.gz' \) -mtime "+${KEEP}" -print -delete
    echo "[$(date '+%F %T')] Listo: $(du -h "/backups/blog-${stamp}.sql.gz" | cut -f1) de base de datos, $(du -h "/backups/imagenes-${stamp}.tar.gz" | cut -f1) de imágenes."
}

if [[ "${1:-}" == "ahora" || "${1:-}" == "now" ]]; then
    run_backup
    exit 0
fi

echo "Copias diarias a las ${HOUR}:00, se guardan ${KEEP} días."
while true; do
    now=$(date +%s)
    next=$(date -d "today ${HOUR}:00" +%s)
    (( next <= now )) && next=$(date -d "tomorrow ${HOUR}:00" +%s)
    sleep $(( next - now ))
    run_backup || echo "[$(date '+%F %T')] ERROR en la copia de seguridad" >&2
done
