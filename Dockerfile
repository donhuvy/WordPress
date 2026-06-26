FROM wordpress:latest

# Copy custom PHP configuration
COPY custom-php.ini /usr/local/etc/php/conf.d/custom-php.ini

# Copy WordPress files into the Apache document root
COPY --chown=www-data:www-data . /var/www/html

# Also copy to /usr/src/wordpress so the entrypoint script updates the persistent volume
COPY --chown=www-data:www-data . /usr/src/wordpress
