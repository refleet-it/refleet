#!/bin/sh
# Prints a fresh RSA keypair for JWT signing as two dotenv lines, base64-encoded PEM:
#   JWT_PRIVATE_KEY=...
#   JWT_PUBLIC_KEY=...
# Nothing in the repository holds a private key: `make init` writes a pair into the gitignored
# .env files, and CI exports one for the lifetime of a job.
set -eu

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

openssl genrsa -out "$tmp/private.pem" 2048 2>/dev/null
openssl rsa -in "$tmp/private.pem" -pubout -out "$tmp/public.pem" 2>/dev/null

# `openssl base64 -A` is the same on macOS and Linux; `base64` itself is not.
printf 'JWT_PRIVATE_KEY=%s\n' "$(openssl base64 -A -in "$tmp/private.pem")"
printf 'JWT_PUBLIC_KEY=%s\n' "$(openssl base64 -A -in "$tmp/public.pem")"
