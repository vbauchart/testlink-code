FROM php:7.2-apache

RUN apt update && \
    apt install -y gpg libpng-dev libjpeg-dev libldap2-dev && \
    apt clean

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN docker-php-ext-install gd ldap

RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - && \
    curl https://packages.microsoft.com/config/debian/10/prod.list > /etc/apt/sources.list.d/mssql-release.list && \
    apt update && \
    ACCEPT_EULA=Y apt install -y msodbcsql17 unixodbc-dev && \
    apt clean


RUN pecl install sqlsrv && \
    pecl install pdo_sqlsrv && \
    docker-php-ext-enable sqlsrv pdo_sqlsrv

ADD . /var/www/html

RUN mkdir -p /var/testlink/logs/ && \
    mkdir -p /var/testlink/upload_area && \
    chown www-data /var/www/html/gui/templates_c /var/testlink/logs/ /var/testlink/upload_area/



#ADD tests/config_db.inc.php /var/www/html