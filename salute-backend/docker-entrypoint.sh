#!/bin/sh

# Exportar variables de entorno de Docker al entorno del sistema
# para que el cron las pueda leer
printenv | grep -v "no_proxy" > /etc/environment

cron
php-fpm