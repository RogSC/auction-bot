#!/bin/sh

set -eu

: "${DOMAIN:?DOMAIN must be set}"

self_signed_certificate_directory="/etc/nginx/self-signed/${DOMAIN}"
letsencrypt_certificate_directory="/etc/letsencrypt/live/${DOMAIN}"

if [ ! -f "${self_signed_certificate_directory}/fullchain.pem" ]; then
    mkdir -p "${self_signed_certificate_directory}"
    openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
        -keyout "${self_signed_certificate_directory}/privkey.pem" \
        -out "${self_signed_certificate_directory}/fullchain.pem" \
        -subj "/CN=${DOMAIN}"
fi

render_configuration() {
    if [ -f "${letsencrypt_certificate_directory}/fullchain.pem" ]; then
        CERTIFICATE_DIRECTORY="${letsencrypt_certificate_directory}"
    else
        CERTIFICATE_DIRECTORY="${self_signed_certificate_directory}"
    fi

    export CERTIFICATE_DIRECTORY
    envsubst '${DOMAIN} ${CERTIFICATE_DIRECTORY}' < /etc/nginx/templates/production.conf.template > /etc/nginx/conf.d/default.conf
}

render_configuration

nginx -g 'daemon off;' &
nginx_pid="$!"

trap 'kill -TERM "$nginx_pid"; wait "$nginx_pid"' INT TERM

while kill -0 "$nginx_pid" 2>/dev/null; do
    if [ -f /etc/letsencrypt/.reload ]; then
        rm -f /etc/letsencrypt/.reload
        render_configuration
        nginx -s reload
    fi

    sleep 60
done

wait "$nginx_pid"
