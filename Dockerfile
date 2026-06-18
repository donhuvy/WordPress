FROM wordpress:latest

# Copy WordPress files into the Apache document root
COPY --chown=www-data:www-data . /var/www/html
