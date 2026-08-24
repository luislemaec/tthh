FROM ubuntu:24.04

ENV DEBIAN_FRONTEND=noninteractive

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    apache2 \
    php8.4 \
    php8.4-fpm \
    php8.4-pgsql \
    php8.4-mbstring \
    php8.4-xml \
    php8.4-curl \
    php8.4-zip \
    php8.4-bcmath \
    php8.4-tokenizer \
    libapache2-mod-proxy-html \
    curl \
    unzip \
    nodejs \
    npm \
    && apt-get clean

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Habilitar módulos Apache
RUN a2enmod rewrite proxy proxy_http headers

# Copiar código
COPY . /var/www/html/rrhh

WORKDIR /var/www/html/rrhh

# Instalar dependencias backend
RUN cd backend && composer install --no-dev --optimize-autoloader

# Compilar frontend
RUN cd frontend && npm install && npm run build

# Configurar Apache
COPY docker/apache.conf /etc/apache2/sites-available/rrhh.conf
RUN a2ensite rrhh.conf && a2dissite 000-default.conf

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]
