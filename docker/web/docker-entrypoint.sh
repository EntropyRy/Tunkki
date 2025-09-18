#!/usr/bin/env sh
#
# docker-entrypoint.sh
#
# Renders /etc/nginx/nginx.conf from a template (nginx.conf.template) using
# environment variables to control real client IP handling for:
#   - Cloudflare proxied production (REAL_IP_MODE=cf)
#   - Direct / internal dev behind Traefik only (REAL_IP_MODE=xff)
#   - Disabled real-ip rewriting (REAL_IP_MODE=off)
#
# After rendering, it execs the provided command (default: nginx).
#
# ENV VARIABLES:
#   REAL_IP_MODE                 cf | xff | off   (default: xff)
#   EXTRA_SET_REAL_IP_FROM       Space-separated list of additional CIDRs to trust
#   ACCESS_LOG                   Path to access log (default: /var/log/nginx/access.log)
#   ERROR_LOG                    Path to error log  (default: /var/log/nginx/error.log)
#   NGINX_TEMPLATE               Path to template (default: /etc/nginx/nginx.conf.template)
#   NGINX_CONF                   Output path (default: /etc/nginx/nginx.conf)
#
# USAGE:
#   (Build image including nginx.conf.template)
#   docker run -e REAL_IP_MODE=cf image
#
set -eu

#############################
# Helpers
#############################

log() {
  printf '[entrypoint] %s\n' "$*" >&2
}

fail() {
  log "ERROR: $*"
  exit 1
}

#############################
# Defaults
#############################
: "${REAL_IP_MODE:=xff}"
: "${ACCESS_LOG:=/var/log/nginx/access.log}"
: "${ERROR_LOG:=/var/log/nginx/error.log}"
: "${NGINX_TEMPLATE:=/etc/nginx/nginx.conf.template}"
: "${NGINX_CONF:=/etc/nginx/nginx.conf}"

if [ ! -f "$NGINX_TEMPLATE" ]; then
  fail "Template not found: $NGINX_TEMPLATE"
fi

#############################
# Build REAL_IP directives (corrected newline handling)
#############################

# We build REAL_IP_DIRECTIVES as a newline-delimited string (no literal \n escapes)
REAL_IP_DIRECTIVES=""

append_line() {
  if [ -z "$REAL_IP_DIRECTIVES" ]; then
    REAL_IP_DIRECTIVES="$1"
  else
    REAL_IP_DIRECTIVES="${REAL_IP_DIRECTIVES}
$1"
  fi
}

add_internal_trust() {
  for cidr in "$@"; do
    # Only add if not already present
    printf '%s\n' "$REAL_IP_DIRECTIVES" | grep -q "set_real_ip_from ${cidr};" || append_line "set_real_ip_from ${cidr};"
  done
}

# Internal defaults (adjust if you know the exact network)
add_internal_trust 172.16.0.0/12 10.0.0.0/8

# User provided extra networks
if [ -n "${EXTRA_SET_REAL_IP_FROM:-}" ]; then
  for cidr in $EXTRA_SET_REAL_IP_FROM; do
    add_internal_trust "$cidr"
  done
fi

case "$REAL_IP_MODE" in
  cf)
    CF_RANGES="173.245.48.0/20
103.21.244.0/22
103.22.200.0/22
103.31.4.0/22
141.101.64.0/18
108.162.192.0/18
190.93.240.0/20
188.114.96.0/20
197.234.240.0/22
198.41.128.0/17
162.158.0.0/15
104.16.0.0/13
104.24.0.0/14
172.64.0.0/13
131.0.72.0/22"
    for cfnet in $CF_RANGES; do
      add_internal_trust "$cfnet"
    done
    append_line "real_ip_header CF-Connecting-IP;"
    append_line "real_ip_recursive on;"
    MODE_DESC="Cloudflare mode (CF-Connecting-IP)"
    ;;
  xff)
    append_line "real_ip_header X-Forwarded-For;"
    append_line "real_ip_recursive on;"
    MODE_DESC="X-Forwarded-For mode (no Cloudflare ranges added)"
    ;;
  off)
    REAL_IP_DIRECTIVES="# REAL_IP_MODE=off (no real IP restoration)"
    MODE_DESC="Disabled real IP rewrite"
    ;;
  *)
    fail "Unknown REAL_IP_MODE='$REAL_IP_MODE' (expected cf|xff|off)"
    ;;
esac

log "Selected REAL_IP_MODE=$REAL_IP_MODE => $MODE_DESC"

#############################
# Render template
#############################

# Write real_ip directives inclusion file (used by nginx.conf template include)
: "${REAL_IP_FILE:=/etc/nginx/real_ip.conf}"
{
  printf '# Auto-generated file. Do NOT edit manually.\n'
  printf '# Generated at: %s\n' "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
  printf '# REAL_IP_MODE=%s\n' "$REAL_IP_MODE"
  printf '# Additional trusted ranges (if any): %s\n' "${EXTRA_SET_REAL_IP_FROM:-<none>}"
  printf '%s\n' "$REAL_IP_DIRECTIVES"
} > "$REAL_IP_FILE"

# Only ACCESS_LOG / ERROR_LOG need substitution now; REAL_IP_DIRECTIVES is in external file
export ACCESS_LOG ERROR_LOG

render() {
  if command -v envsubst >/dev/null 2>&1; then
    # Restrict envsubst to only the variables we need (REAL_IP_DIRECTIVES already written to file)
    envsubst '${ACCESS_LOG} ${ERROR_LOG}' < "$NGINX_TEMPLATE" > "$NGINX_CONF"
  else
    log "envsubst not found; installing (Alpine assumed)..."
    if command -v apk >/dev/null 2>&1; then
      apk add --no-cache gettext >/dev/null
      envsubst '${ACCESS_LOG} ${ERROR_LOG}' < "$NGINX_TEMPLATE" > "$NGINX_CONF"
    else
      log "apk not available. Falling back to sed replacement (limited)."
      sed -e "s|\${ACCESS_LOG:-/var/log/nginx/access.log}|$ACCESS_LOG|g" \
          -e "s|\${ERROR_LOG:-/var/log/nginx/error.log}|$ERROR_LOG|g" \
        "$NGINX_TEMPLATE" > "$NGINX_CONF"
    fi
  fi
}

render

# Basic sanity check: ensure output contains at least one log_format line
if ! grep -q 'log_format main' "$NGINX_CONF"; then
  log "WARNING: Rendered nginx.conf missing expected log_format; dump follows:"
  sed -n '1,120p' "$NGINX_CONF" >&2
fi

log "Rendered nginx.conf:"
grep -E '^( *set_real_ip_from| *real_ip_header| *real_ip_recursive| *log_format)' "$NGINX_CONF" || true

#############################
# Execute CMD
#############################
if [ "$#" -eq 0 ]; then
  # Default command: run nginx in foreground
  exec nginx -g 'daemon off;'
else
  exec "$@"
fi
