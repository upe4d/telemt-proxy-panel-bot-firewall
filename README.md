# Telemt MTProxy — Panel, Bot & Firewall

Полный стек для управления MTProxy сервером на базе [Telemt](https://github.com/telemt/telemt):
- 📊 Веб-дашборд со статистикой, картой, GeoIP
- 🤖 Telegram бот с уведомлениями и отчётами
- 🛡️ Firewall с белым списком стран и IP
- 🔒 Защищённое управление с паролем

## Скриншоты

> Dashboard, карта пользователей, блокировки, белый список

## Компоненты

| Компонент | Описание |
|-----------|----------|
| `bot/collector.py` | Сбор статистики каждые 5 минут |
| `bot/bot.py` | Telegram бот — дайджест, логи, бэкап |
| `bot/firewall.py` | Управление iptables GeoIP блокировками |
| `bot/firewall_watcher.py` | Следит за whitelist.json и применяет правила |
| `bot/fw_counters.sh` | Счётчики блокировок для дашборда |
| `dashboard/` | PHP дашборд на Bootstrap 5 + Chart.js + Leaflet |

## Требования

- Ubuntu 20.04+ / Debian 11+
- Python 3.10+
- PHP 8.1+ с mbstring
- nginx + php-fpm
- iptables + xtables-addons (для GeoIP блокировок)
- Установленный и работающий [Telemt](https://github.com/telemt/telemt)
- [Telemt Panel](https://github.com/amirotin/telemt_panel)

## Установка

### 1. Клонируй репозиторий
```bash
git clone https://github.com/upe4d/telemt-proxy-panel-bot-firewall.git
cd telemt-proxy-panel-bot-firewall
```

### 2. Настрой конфигурацию
```bash
cp .env.example .env
nano .env
```

Заполни все переменные в `.env`.

### 3. Установи зависимости Python
```bash
pip install requests urllib3 --break-system-packages
```

### 4. Скопируй файлы бота
```bash
mkdir -p /opt/telemt-bot
cp -r bot/* /opt/telemt-bot/
cp -r bot/modules /opt/telemt-bot/
```

### 5. Настрой конфиг бота

Отредактируй `/opt/telemt-bot/config.py` — вставь свои значения из `.env`.

### 6. Установи systemd сервисы
```bash
cp systemd/*.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now telemt-collector telemt-bot telemt-firewall
```

### 7. Настрой веб-дашборд
```bash
mkdir -p /var/www/stats
cp -r dashboard/* /var/www/stats/
chown -R www-data:www-data /var/www/stats/data
```

Настрой nginx — пример конфига:
```nginx
server {
    listen 443 ssl;
    server_name stats.yourdomain.com;
    root /var/www/stats;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/stats.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/stats.yourdomain.com/privkey.pem;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }

    location /data/ { deny all; }
}
```

### 8. GeoIP блокировки (опционально)
```bash
apt install xtables-addons-common libtext-csv-xs-perl -y
modprobe xt_geoip
cd /tmp
wget "https://download.db-ip.com/free/dbip-country-lite-$(date +%Y-%m).csv.gz"
gunzip dbip-country-lite-*.csv.gz
mv dbip-country-lite-*.csv dbip-country-lite.csv
/usr/libexec/xtables-addons/xt_geoip_build -D /usr/share/xt_geoip dbip-country-lite.csv
```

### 9. Счётчики firewall (cron)
```bash
chmod +x /opt/telemt-bot/fw_counters.sh
crontab -e
# Добавь строку:
* * * * * /opt/telemt-bot/fw_counters.sh
```

## Структура данных
```
/var/www/stats/data/
├── current.json        # Текущая статистика
├── history.json        # История по дням (30 дней)
├── hours.json          # Активность по часам
├── countries.json      # Статистика по странам
├── cities.json         # Статистика по городам
├── ips.json            # История IP адресов
├── users.json          # Статистика пользователей
├── blocked.json        # Заблокированные IP с анализом
├── whitelist.json      # Белый список стран и IP
├── fw_counters.json    # Счётчики firewall
├── city_coords.json    # Координаты городов (кэш)
├── country_traffic.json # Трафик по странам
└── new_vs_repeat.json  # Новые vs повторные IP
```

## Возможности дашборда

- 📈 Графики подключений, трафика, активных IP по дням
- 🗺️ Карта активных пользователей (Leaflet.js)
- 🌍 Статистика по странам и городам с флагами
- 👥 Новые vs повторные пользователи
- 📦 Трафик по странам (косвенный)
- 🚫 Анализ заблокированных IP (бот/пользователь)
- 🛡️ Управление белым списком с паролем
- 🔄 Авто-обновление каждые 5 минут

## Telegram бот — команды

| Команда | Описание |
|---------|----------|
| `/start` | Список команд и ссылки |
| `/stats` | Статистика с графиком по часам |
| `/logs` | Файл логов telemt |
| `/backup` | Бэкап конфига telemt |
| `/digest` | Дайджест за сегодня |

## Firewall

Блокировка работает через `iptables` + `xt_geoip`.
Управление через веб-интерфейс дашборда (требует пароль).

Поддерживаемые действия:
- Включить/выключить фильтрацию
- Добавить/удалить разрешённые страны
- Добавить/удалить разрешённые IP (IPv4 и IPv6)
- Просмотр заблокированных IP с GeoIP и анализом

## Стек технологий

**Backend:** Python 3, PHP 8.3
**Frontend:** Bootstrap 5, Chart.js, Leaflet.js
**Firewall:** iptables, ip6tables, xt_geoip
**Сервер:** nginx, php-fpm, systemd

## Лицензия

MIT

## Автор

[@upe4d](https://github.com/upe4d) — [t.me/u_pre](https://t.me/u_pre)
