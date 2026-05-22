FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git curl zip unzip libpq-dev libicu-dev libonig-dev libzip-dev cron tzdata \
    && docker-php-ext-install pdo pdo_mysql intl mbstring zip opcache \
    && ln -sf /usr/share/zoneinfo/Europe/Madrid /etc/localtime \
    && echo "Europe/Madrid" > /etc/timezone \
    && echo "date.timezone = Europe/Madrid" > /usr/local/etc/php/conf.d/timezone.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Cron cada hora
RUN printf "0 * * * * root . /etc/environment; /usr/local/bin/php /var/www/html/bin/console app:send-reminders --env=dev >> /var/log/reminders.log 2>&1\n0 * * * * root . /etc/environment; /usr/local/bin/php /var/www/html/bin/console app:expire-appointments --env=dev >> /var/log/expire.log 2>&1\n" > /etc/cron.d/salute \
    && chmod 0644 /etc/cron.d/salute

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

ENTRYPOINT ["docker-entrypoint.sh"]