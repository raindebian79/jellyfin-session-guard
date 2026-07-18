FROM php:8.3-cli-alpine

# Instalar dependencias del sistema esenciales
RUN apk add --no-cache \
    git \
    unzip \
    bash

# Copiar Composer de forma oficial desde su imagen
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copiar configuración de dependencias primero para optimizar la caché de Docker
COPY composer.json ./

# Instalar los paquetes de Composer
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Copiar el resto del código del servicio
COPY . .

# Asegurar que el directorio de logs tenga permisos adecuados
RUN mkdir -p logs && chmod -R 775 logs

CMD ["php", "index.php"]
