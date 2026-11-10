#!/usr/bin/env bash
# Se ejecuta en cada arranque (después de que admin aplique las migraciones).
# Deja al usuario de la web con los permisos MÍNIMOS:
#   - leer todo
#   - añadir comentarios (quedan pendientes) y visitas
# No puede modificar ni borrar nada.
set -euo pipefail

mysql -h mysql -uroot <<SQL
CREATE USER IF NOT EXISTS '${WEB_USER}'@'%' IDENTIFIED BY '${WEB_PASSWORD}';
ALTER USER '${WEB_USER}'@'%' IDENTIFIED BY '${WEB_PASSWORD}';
REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${WEB_USER}'@'%';
GRANT SELECT ON \`${DB}\`.* TO '${WEB_USER}'@'%';
GRANT INSERT ON \`${DB}\`.\`comments\` TO '${WEB_USER}'@'%';
GRANT INSERT ON \`${DB}\`.\`page_views\` TO '${WEB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

echo "Permisos de '${WEB_USER}' aplicados: SELECT en todo, INSERT solo en comments y page_views."
