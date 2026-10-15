FROM php:8.3-cli-bookworm

RUN apt-get update \
 && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql gd zip \
 && rm -rf /var/lib/apt/lists/*

# Las fotos de las propiedades pesan más que el límite por defecto de PHP.
RUN printf 'upload_max_filesize=8M\npost_max_size=40M\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/lit.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Se copia todo el repositorio porque backend/ necesita la carpeta hermana frontend/.
COPY . .

WORKDIR /app/backend
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

ENV PHP_CLI_SERVER_WORKERS=4

# Al arrancar: migra, carga los datos compartidos solo si la base está vacía y enciende el servidor.
CMD ["sh", "-c", "mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache public/uploads && php artisan package:discover --ansi && php artisan migrate --force && php artisan db:seed --force && php artisan config:cache && exec php artisan serve --host=0.0.0.0 --port=${PORT:-8080} --no-reload"]
