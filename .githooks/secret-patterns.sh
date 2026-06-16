#!/usr/bin/env bash
# Shared blocklists for the pre-commit / pre-push secret-scanning git hooks.
# Sourced by .githooks/pre-commit and .githooks/pre-push — not a hook itself.

# Returns 0 (true) if $1 is a filename that should never be committed.
# Explicit *.example / *.sample templates are always allowed.
is_blocked_filename() {
  local f="$1"
  case "$f" in
    *.example|*.sample|*.dist) return 1 ;;
  esac
  case "$f" in
    .env|.env.*|*/.env|*/.env.*|*.env) return 0 ;;
    private|private/*|*/private/*) return 0 ;;
    *.pem|*.key|*.pfx|*.p12|*.pgpass|.npmrc|*/.npmrc|.netrc|*/.netrc) return 0 ;;
    *id_rsa*|*id_dsa*|*id_ecdsa*|*id_ed25519*) return 0 ;;
  esac
  return 1
}

# Well-known credential signatures + raw KEY=VALUE lines for known infra
# secrets (the exact shape of the STAGING_TODO.md / INFINITYFREE_STATUS.md
# leak — a pasted .env block). Intentionally narrow: matching real Laravel
# env() calls or validation rules like 'password' => 'required' must NOT
# trigger this, or the hook becomes noise everyone disables.
SECRET_CONTENT_REGEX='AKIA[0-9A-Z]{16}'
SECRET_CONTENT_REGEX="$SECRET_CONTENT_REGEX|-----BEGIN (RSA |EC |OPENSSH |DSA )?PRIVATE KEY-----"
SECRET_CONTENT_REGEX="$SECRET_CONTENT_REGEX|AIza[0-9A-Za-z_-]{35}"
SECRET_CONTENT_REGEX="$SECRET_CONTENT_REGEX|xox[baprs]-[0-9A-Za-z-]+"
SECRET_CONTENT_REGEX="$SECRET_CONTENT_REGEX|sk_live_[0-9A-Za-z]{10,}"
SECRET_CONTENT_REGEX="$SECRET_CONTENT_REGEX|ghp_[0-9A-Za-z]{30,}"
SECRET_CONTENT_REGEX="$SECRET_CONTENT_REGEX|github_pat_[0-9A-Za-z_]{20,}"
SECRET_CONTENT_REGEX="$SECRET_CONTENT_REGEX|^[[:space:]]*(DB_PASSWORD|DB_LEGACY_PASSWORD|DB_OLD_CRM_PASSWORD|DB_EXHIBITIONS_PASSWORD|MAIL_PASSWORD|APP_KEY|AWS_SECRET_ACCESS_KEY|AWS_ACCESS_KEY_ID|WHATSAPP_APP_SECRET|WHATSAPP_ACCESS_TOKEN|WHATSAPP_VERIFY_TOKEN|REDIS_PASSWORD|BREVO_API_KEY|SLACK_BOT_USER_OAUTH_TOKEN|DEV_SUPER_ADMIN_PASSWORD)[[:space:]]*=[[:space:]]*[^[:space:]\"]{3,}"
export SECRET_CONTENT_REGEX
