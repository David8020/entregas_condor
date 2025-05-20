# Usar una imagen oficial de PHP con Apache
FROM php:8.2-apache

# Establecer el directorio de trabajo en el contenedor
WORKDIR /var/www/html

# Copiar todos los archivos del proyecto al directorio de trabajo del contenedor
# Asumiendo que tu Dockerfile está en la raíz del repo, y 'entregas_condor' es una subcarpeta.
# Si 'entregas_condor' es la raíz de lo que quieres servir, ajusta la copia.
# Si tu repo solo contiene la carpeta 'entregas_condor' y quieres que esa sea la raíz:
COPY . /var/www/html/

# (Opcional) Si necesitas extensiones PHP específicas, las instalarías aquí.
# Ejemplo: RUN docker-php-ext-install pdo pdo_mysql mysqli

# (Opcional) Configurar Apache si es necesario, ej. habilitar mod_rewrite
# RUN a2enmod rewrite

# El DocumentRoot de Apache en esta imagen php:apache ya es /var/www/html
# Así que si copias tus archivos allí, deberían servirse.

# Exponer el puerto 80 (Apache por defecto)
EXPOSE 80
