#!/bin/sh
# Fills in the generated half of a self-hosted instance's .env: anything listed below that is
# still empty gets a fresh value, and anything already set is left exactly as it was. Safe to
# run again — re-running never rotates a secret, because rotating JWT_PRIVATE_KEY signs
# everyone out and rotating GITLAB_TOKEN_ENCRYPTION_KEY orphans every stored GitLab token.
set -eu

env_file=${1:-.env}

[ -f "$env_file" ] || { echo "No $env_file — copy .env.selfhost.dist to it first." >&2; exit 1; }

# `openssl base64 -A` and `openssl rand` behave the same on macOS and Linux; `base64` does not.
random() { openssl rand -hex 32; }

set_if_empty() {
    key=$1
    value=$2

    if grep -qE "^${key}=.+" "$env_file"; then
        return 0
    fi

    if grep -qE "^${key}=" "$env_file"; then
        # A dotenv value can contain slashes and pluses (base64), so sed's delimiter must not.
        sed -i.bak "s|^${key}=.*|${key}=${value}|" "$env_file" && rm -f "$env_file.bak"
    else
        printf '%s=%s\n' "$key" "$value" >> "$env_file"
    fi

    echo "  generated $key"
}

echo "🔐 Filling in generated secrets in $env_file"

set_if_empty APP_SECRET "$(random)"
set_if_empty GITLAB_TOKEN_ENCRYPTION_KEY "$(random)"
set_if_empty DB_USER_PASSWORD "$(random)"

if ! grep -qE '^JWT_PRIVATE_KEY=.+' "$env_file"; then
    pair=$(./ci/scripts/jwt-keypair.sh)
    set_if_empty JWT_PRIVATE_KEY "$(printf '%s\n' "$pair" | sed -n 's/^JWT_PRIVATE_KEY=//p')"
    set_if_empty JWT_PUBLIC_KEY "$(printf '%s\n' "$pair" | sed -n 's/^JWT_PUBLIC_KEY=//p')"
fi

echo "✅ Secrets in place."
