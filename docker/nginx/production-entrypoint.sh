#!/bin/sh

set -eu

: "${DOMAIN:?DOMAIN must be set}"

certificate_directory="/etc/letsencrypt/live/${DOMAIN}"

if [ ! -f "${certificate_directory}/fullchain.pem" ]; then
    mkdir -p "${certificate_directory}"
    openssl req -x509 -nodes -newkey rsa:2048 -days 1 \
        -keyout "${certificate_directory}/privkey.pem" \
        -out "${certificate_directory}/fullchain.pem" \
        -subj "/CN=${DOMAIN}"
fi

envsubst '${DOMAIN}' < /etc/nginx/templates/production.conf.template > /etc/nginx/conf.d/default.conf

nginx -g 'daemon off;' &
nginx_pid="$!"

trap 'kill -TERM "$nginx_pid"; wait "$nginx_pid"' INT TERM

while kill -0 "$nginx_pid" 2>/dev/null; do
    if [ -f /etc/letsencrypt/.reload ]; then
        rm -f /etc/letsencrypt/.reload
        nginx -s reload
    fi

    sleep 60
done

wait "$nginx_pid"
