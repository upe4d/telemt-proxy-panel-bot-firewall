# Telemt MTProxy — Panel, Bot & Firewall

Полный стек для управления MTProxy сервером на базе [Telemt](https://github.com/telemt/telemt):
- 📊 Веб-дашборд со статистикой, картой, GeoIP
- 🤖 Telegram бот с уведомлениями и отчётами
- 🛡️ Firewall с белым списком стран и IP
- 🔒 Защищённое управление с паролем

## Скриншоты
> Dashboard, карта пользователей, блокировки, белый список

<img width="1859" height="862" alt="image" src="https://github.com/user-attachments/assets/a52ddd7a-7cc6-4b5e-9ff8-e5e1aaa01b81" />
<img width="1840" height="876" alt="image" src="https://github.com/user-attachments/assets/5c7ab4ba-6a2e-4e8d-839a-06c9e061e49f" />
<img width="1840" height="876" alt="image" src="https://github.com/user-attachments/assets/834b238a-786c-4b61-89f6-67601328890e" />
<img width="1856" height="779" alt="image" src="https://github.com/user-attachments/assets/d617f8de-e7b9-4ad4-ba45-01957c04e29a" />
<img width="1284" height="700" alt="image" src="https://github.com/user-attachments/assets/d4272547-7c46-482e-a1e1-d6d1ca1d2f2a" />
<img width="1593" height="530" alt="image" src="https://github.com/user-attachments/assets/91db2cdc-faa4-47c4-892a-81448c55f21d" />
<img width="1842" height="722" alt="image" src="https://github.com/user-attachments/assets/23d84d1e-a42c-4dfc-aba7-20744d1435d4" />
<img width="1660" height="795" alt="image" src="https://github.com/user-attachments/assets/587e4996-1369-427f-be91-9fcf6daff46a" />
<img width="1802" height="788" alt="image" src="https://github.com/user-attachments/assets/af8875ae-6880-401c-8ea8-7000e0fb009a" />



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
