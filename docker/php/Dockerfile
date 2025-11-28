FROM php:8.4-fpm


RUN apt-get update && apt-get install -y \
    git unzip libzip-dev libonig-dev \
    libicu-dev libpq-dev libxml2-dev zlib1g-dev \
    libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-install pdo_mysql zip bcmath intl opcache gd


RUN groupadd -g 1000 dev && \
    useradd -u 1000 -g 1000 -ms /bin/bash dev

WORKDIR /var/www/html


COPY . .


RUN chown -R 1000:1000 /var/www \
    && mkdir -p /var/www/var/cache /var/www/var/log \
    && chown -R 1000:1000 /var/www/var \
    && chmod -R 775 /var/www/var


USER dev


COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

CMD ["php-fpm"]
