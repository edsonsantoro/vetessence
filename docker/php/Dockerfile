# ---------------------------------------------------------------------------
# AgroVerde — Imagem PHP customizada
#
# Estende serversideup/php:8.4-fpm-nginx com as extensões que o VetEssence
# requer e que não vêm na imagem base.
#
# Build: docker compose build
# ---------------------------------------------------------------------------
FROM serversideup/php:8.4-fpm-nginx

USER root

# Extensões requeridas pelo VetEssence (composer.lock)
# - gd:       simple-qrcode, intervention/image
# - bcmath:   cálculos financeiros
# - intl:     formatação i18n
# - exif:     metadados de imagens
RUN install-php-extensions \
    gd \
    bcmath \
    intl \
    exif

USER www-data
