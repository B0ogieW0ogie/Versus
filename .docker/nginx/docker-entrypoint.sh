#!/bin/sh
set -eu

DOMAIN="${DOMAIN:-versus-battle.com}"
CERT_DIR="/etc/letsencrypt/live/${DOMAIN}"
CERT_FILE="${CERT_DIR}/fullchain.pem"
KEY_FILE="${CERT_DIR}/privkey.pem"

if [ ! -f "${CERT_FILE}" ] || [ ! -f "${KEY_FILE}" ]; then
  echo "No TLS certificate found for ${DOMAIN}; generating temporary self-signed certificate."
  mkdir -p "${CERT_DIR}"

  openssl req -x509 -nodes -newkey rsa:2048 \
    -keyout "${KEY_FILE}" \
    -out "${CERT_FILE}" \
    -days 1 \
    -subj "/CN=${DOMAIN}"
fi

exec "$@"
