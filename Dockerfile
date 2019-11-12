FROM library/php:5-apache

RUN apt update && \
    apt install -y apt-transport-https gpg libpng-dev libjpeg-dev libldap2-dev libxml2-dev freetds-dev && \
    apt clean

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN echo "LimitRequestFieldSize 12392" >> /etc/apache2/apache2.conf

RUN docker-php-ext-configure mssql --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install gd soap mssql ldap mbstring

# set TDS version to avoid error "Unicode data in a Unicode-only collation or ntext data cannot be sent to clients"
RUN perl -i -pe 's/;\s*tds version.*$/\ttds version = 8.0/' /etc/freetds/freetds.conf
RUN perl -i -pe 's/;\s*date.timezone*$/\tdate.timezone = 8.0/' /etc/freetds/freetds.conf

RUN curl https://packages.microsoft.com/keys/microsoft.asc | apt-key add - && \
    curl https://packages.microsoft.com/config/debian/9/prod.list > /etc/apt/sources.list.d/mssql-release.list && \
    apt update && \
    ACCEPT_EULA=Y apt install -y msodbcsql17 unixodbc-dev && \
    apt clean

ARG VERSION=1.9.14

# ADD . /var/www/html

RUN mkdir -p /var/testlink/logs/ && \
    mkdir -p /var/testlink/upload_area && \
    chown www-data /var/testlink/logs/ /var/testlink/upload_area/
# RUN chown www-data /var/www/html/gui/templates_c 

# ADD images/ingenico_ps_116.png images/ingenico_ps_231.png /var/www/html/gui/themes/default/images/
# ADD cfg/config_db.inc.php cfg/custom_config.inc.php /var/www/html/

# ADD infra.pem /etc/ssl/certs/ca-certificates.crt