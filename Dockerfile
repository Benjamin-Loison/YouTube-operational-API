FROM php:apache AS builder
RUN apt-get update && apt-get install -y git protobuf-compiler curl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
COPY . /app
WORKDIR /app
RUN composer require google/protobuf \
    && protoc --php_out=proto/php/ --proto_path=proto/prototypes/ $(find proto/prototypes/ -type f)

# Final image
FROM php:apache
RUN a2enmod rewrite
COPY --from=builder /app /var/www/html/
RUN sed -ri -e 'N;N;N;s/(<Directory \/var\/www\/>\n)(.*\n)(.*)AllowOverride None/\1\2\3AllowOverride All/;p;d;' /etc/apache2/apache2.conf
ENTRYPOINT ["apachectl", "-D", "FOREGROUND"]
