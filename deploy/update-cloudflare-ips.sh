#!/usr/bin/env bash
# Gera a config do Apache (mod_remoteip) com as faixas ATUAIS do Cloudflare,
# para o PHP enxergar o IP real do visitante em $_SERVER['REMOTE_ADDR'].
#
# Uso (no droplet):  sudo bash deploy/update-cloudflare-ips.sh
# Opcional: rode de tempos em tempos (cron mensal) para manter a lista atualizada.
set -euo pipefail

OUT=/etc/apache2/conf-available/remoteip-cloudflare.conf

a2enmod remoteip >/dev/null

{
  echo "# Gerado por deploy/update-cloudflare-ips.sh em $(date -u +%F)"
  echo "RemoteIPHeader CF-Connecting-IP"
  for ip in $(curl -fsS https://www.cloudflare.com/ips-v4) $(curl -fsS https://www.cloudflare.com/ips-v6); do
    echo "RemoteIPTrustedProxy $ip"
  done
} > "$OUT"

a2enconf remoteip-cloudflare >/dev/null
apache2ctl configtest
systemctl reload apache2
echo "OK: $OUT atualizado."
