FROM node:22-alpine AS frontend-build
WORKDIR /app/frontend
COPY frontend/package*.json ./
RUN npm install
COPY frontend/ ./
RUN npm run build

FROM php:8.4-cli-alpine
RUN apk add --no-cache sqlite sqlite-dev libzip-dev oniguruma-dev curl git unzip \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql mbstring zip bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY backend/ ./
COPY --from=frontend-build /app/frontend/dist/assets ./public/assets
COPY --from=frontend-build /app/frontend/dist/clique-cafe-logo.jpg ./public/clique-cafe-logo.jpg
COPY --from=frontend-build /app/frontend/dist/index.html ./public/app.html

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && cp .env.example .env \
    && sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env \
    && sed -i 's/^DB_HOST=/#DB_HOST=/' .env \
    && sed -i 's/^DB_PORT=/#DB_PORT=/' .env \
    && sed -i 's/^DB_DATABASE=/#DB_DATABASE=/' .env \
    && sed -i 's/^DB_USERNAME=/#DB_USERNAME=/' .env \
    && sed -i 's/^DB_PASSWORD=/#DB_PASSWORD=/' .env \
    && touch database/database.sqlite \
    && php artisan key:generate --force \
    && chmod -R 775 storage bootstrap/cache database

EXPOSE 10000

CMD sh -c "php artisan migrate --force && php artisan db:seed --class=RestaurantSetupSeeder --force && php artisan serve --host=0.0.0.0 --port=${PORT:-10000}"
