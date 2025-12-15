#!/bin/bash
set -euo pipefail

# =============================
#   DETECTAR PROYECTO
# =============================
DOCKER_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$DOCKER_DIR")"
PROJECT_NAME="$(basename "$PROJECT_DIR")"
NGINX_DIR="$DOCKER_DIR/nginx"

echo "✅ Proyecto detectado: $PROJECT_NAME"
echo "📁 Directorio del proyecto: $PROJECT_DIR"
echo "📁 Directorio Docker: $DOCKER_DIR"
echo "📁 Directorio Nginx: $NGINX_DIR"

ENV_FILE="$PROJECT_DIR/.env"
NETWORK="shared_network"

# =============================
#   LEER APP_URL DEL ENV
# =============================
if [ ! -f "$ENV_FILE" ]; then
    echo "❌ ERROR: No se encontró $ENV_FILE"
    exit 1
fi

APP_URL=$(grep -E '^APP_URL=' "$ENV_FILE" | cut -d '=' -f2- | tr -d '[:space:]')

if [ -z "$APP_URL" ]; then
    echo "❌ ERROR: Falta APP_URL en el .env"
    exit 1
fi

# Extraer el puerto de la URL si existe, sino usar 80
if [[ "$APP_URL" =~ :([0-9]+)$ ]]; then
    APP_PORT="${BASH_REMATCH[1]}"
else
    APP_PORT=80
fi

echo "🌐 URL de la aplicación: $APP_URL"
echo "🔌 Puerto detectado: $APP_PORT"

# =============================
#   GENERAR CONFIGURACIÓN NGINX
# =============================
mkdir -p "$NGINX_DIR"

cat > "$NGINX_DIR/default.conf" <<EOF
server {
    listen 80;
    index index.php index.html;
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    root /var/www/html/public;

    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
        gzip_static on;
    }
}
EOF

echo "✅ Configuración de Nginx generada en $NGINX_DIR/default.conf"

# =============================
#   DETECTAR DB HOST
# =============================
echo "🔍 Buscando contenedor de base de datos en $NETWORK..."
DB_HOST=$(docker network inspect "$NETWORK" -f '{{range .Containers}}{{.Name}} {{end}}' | tr ' ' '\n' | grep mysql | head -n 1 || true)

if [ -z "$DB_HOST" ]; then
    echo "⚠️  No se detectó un contenedor MySQL en $NETWORK. Usando configuración del .env."
    DB_HOST_ENV=""
else
    echo "✅ Base de datos detectada: $DB_HOST"
    DB_HOST_ENV="DB_HOST: $DB_HOST"
fi

# =============================
#   GENERAR DOCKER COMPOSE
# =============================
cat > "$DOCKER_DIR/docker-compose.yml" <<EOF
services:
    app:
        build:
            context: .
            dockerfile: php/Dockerfile
        container_name: ${PROJECT_NAME}_app
        restart: unless-stopped
        working_dir: /var/www/html
        environment:
            $DB_HOST_ENV
        volumes:
            - ../:/var/www/html
        networks:
            - $NETWORK

    nginx:
        image: nginx:alpine
        container_name: ${PROJECT_NAME}_nginx
        restart: unless-stopped
        ports:
            - "$APP_PORT:80"
        volumes:
            - ../:/var/www/html
            - ./nginx/default.conf:/etc/nginx/conf.d/default.conf
        depends_on:
            - app
        networks:
            - $NETWORK

networks:
    $NETWORK:
        external: true
EOF

echo "✅ docker-compose.yml generado en $DOCKER_DIR/docker-compose.yml"

# =============================
#   INICIAR DOCKER
# =============================
echo "� Ajustando permisos..."
chmod -R 777 "$PROJECT_DIR/storage" "$PROJECT_DIR/bootstrap/cache"

echo "�🚀 Iniciando contenedores..."
cd "$DOCKER_DIR"
docker compose up --build

echo "✅ ¡Despliegue completado! Accede en: $APP_URL"
