FROM php:8.2-apache

# mysql-client para mysqladmin no entrypoint
RUN apt-get update && apt-get install -y default-mysql-client netcat-openbsd locales && \
    locale-gen pt_BR.UTF-8 && \
    rm -rf /var/lib/apt/lists/*

ENV LANG=pt_BR.UTF-8 \
    LANGUAGE=pt_BR:pt \
    LC_ALL=pt_BR.UTF-8 \
    TZ=America/Sao_Paulo

# Extensões necessárias
RUN docker-php-ext-install mysqli pdo pdo_mysql

# mod_rewrite para .htaccess
RUN a2enmod rewrite

# Copiar configurações customizadas
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# Copiar código da aplicação
COPY . /var/www/html/

# Permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/backups \
    && chown www-data:www-data /var/www/html/backups

# Entrypoint
COPY scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]
