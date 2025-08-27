FROM php:apache AS builder
RUN apt-get update && apt-get install -y git protobuf-compiler
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
COPY . /app
WORKDIR /app
RUN composer require google/protobuf \
    && protoc --php_out=proto/php/ --proto_path=proto/prototypes/ $(find proto/prototypes/ -type f)

# Final image
FROM php:apache
RUN a2enmod rewrite
COPY --from=builder /app /var/www/html/
# Replace `AllowOverride None` with `AllowOverride All` in `<Directory /var/www/>` in `/etc/apache2/apache2.conf`.
RUN sed -ri -e 'N;N;N;s/(<Directory \/var\/www\/>\n)(.*\n)(.*)AllowOverride None/\1\2\3AllowOverride All/;p;d;' /etc/apache2/apache2.conf
EXPOSE 80
ENTRYPOINT ["apachectl", "-D", "FOREGROUND"]
