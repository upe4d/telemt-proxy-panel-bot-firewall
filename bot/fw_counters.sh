#!/bin/bash
# Обновляет счётчики firewall в JSON файл каждую минуту

DATA_DIR="/var/www/stats/data"

# Получаем счётчики
DROP4=$(iptables -L TELEMT_WL -n -v 2>/dev/null | grep ' DROP ' | awk '{print $1}' | head -1)
DROP6=$(ip6tables -L TELEMT_WL6 -n -v 2>/dev/null | grep ' DROP ' | awk '{print $1}' | head -1)
ACCEPT4=$(iptables -L TELEMT_WL -n -v 2>/dev/null | grep 'geoip' | awk '{sum+=$1} END {print sum}')

# Записываем в JSON
cat > "$DATA_DIR/fw_counters.json" << EOF
{
  "blocked_ipv4": ${DROP4:-0},
  "blocked_ipv6": ${DROP6:-0},
  "accepted_ipv4": ${ACCEPT4:-0},
  "updated": "$(date '+%Y-%m-%d %H:%M:%S')"
}
EOF

chown www-data:www-data "$DATA_DIR/fw_counters.json"
