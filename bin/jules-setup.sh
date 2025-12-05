#!/bin/bash

# 1. Instalar dependencias de PHP
composer install --no-interaction --prefer-dist

# 2. Configurar entorno
if [ ! -f .env ]; then
    cp .env.example .env
fi

# 3. Configurar SQLite (Clave para que Jules no falle intentando conectar a MySQL)
# Forzamos la conexión a sqlite en el .env si no está configurada
sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
sed -i 's/DB_DATABASE=.*/DB_DATABASE=database\/database.sqlite/' .env
# Eliminar credenciales que sobran para sqlite
sed -i '/DB_HOST/d' .env
sed -i '/DB_PORT/d' .env
sed -i '/DB_USERNAME/d' .env
sed -i '/DB_PASSWORD/d' .env

touch database/database.sqlite

# 4. Generar key y migrar
php artisan key:generate
php artisan migrate --force