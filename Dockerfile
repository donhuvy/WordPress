FROM wordpress:latest

# Copy WordPress files into the Apache document root
COPY --chown=www-data:www-data . /var/www/html

# Also copy to /usr/src/wordpress so the entrypoint script updates the persistent volume
COPY --chown=www-data:www-data . /usr/src/wordpress
