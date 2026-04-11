# Telemt MTProxy — Dashboard, Bot & Firewall

Полный стек для управления MTProxy сервером на базе [Telemt](https://github.com/telemt/telemt):

- 📊 Веб-дашборд v2.0 — вкладочная навигация, тёмно-синяя гамма, 7 разделов
- 🛡️ Защита от ТСПУ — TSPUBLOCK (CyberOK) + GOVIPS (госорганы РФ) с аналитикой
- 🤖 Telegram бот с уведомлениями и отчётами
- 🔒 GeoIP фильтрация с белым списком стран и IP
- 🗺️ Карта активных пользователей в реальном времени

## Скриншоты

> Обзор, Карта, Защита, Страны, Пользователи, История, Фильтрация

<img width="3081" height="1436" alt="image" src="https://github.com/user-attachments/assets/c44c4824-399a-4882-9b87-c43451e40a1e" />
<img width="3093" height="1452" alt="image" src="https://github.com/user-attachments/assets/d4d4b4d7-f2f5-47b1-9261-eec3b50cbd75" />
<img width="3055" height="1712" alt="image" src="https://github.com/user-attachments/assets/3fb47046-8215-4660-a2ea-0e741ddd892b" />
<img width="3072" height="1761" alt="image" src="https://github.com/user-attachments/assets/d7e4f066-d7c6-4677-8399-86cf46fe1102" />
<img width="3067" height="1741" alt="image" src="https://github.com/user-attachments/assets/ceb6d467-5087-4aec-9c1d-7722e55780f5" />
<img width="3064" height="1690" alt="image" src="https://github.com/user-attachments/assets/94f46f7c-71f8-4a47-8754-c1b9169cbaf4" />
<img width="3110" height="1075" alt="image" src="https://github.com/user-attachments/assets/d4ffabdf-fa35-4981-aa18-279d035a04fa" />

## Структура проекта

```
telemt-proxy-panel-bot-firewall/
├── index.php                  # Главная страница — шапка, навигация
├── config.php                 # Настройки сайта и пути к файлам данных
├── assets/
│   ├── style.css              # Тёмно-синяя гамма, CSS переменные, кастомный скролл
│   └── app.js                 # AJAX навигация по вкладкам без перезагрузки
├── tabs/
│   ├── overview.php           # Обзор — метрики, мини-карта, защита, топ стран
│   ├── map.php                # Карта — полноэкранная с фильтром по странам
│   ├── protection.php         # Защита — TSPUBLOCK + GOVIPS аналитика
│   ├── countries.php          # Страны — таблица, топ городов, трафик
│   ├── users.php              # Пользователи — история IP, новые vs повторные
│   ├── history.php            # История — графики по дням и часам
│   └── whitelist.php          # Фильтрация — GeoIP белый список
├── modules/
│   └── whitelist.php          # Модуль управления белым списком
└── bot/
    ├── collector.py           # Сбор статистики каждые 10 минут
    ├── bot.py                 # Telegram бот
    ├── firewall.py            # Управление iptables GeoIP блокировками
    ├── firewall_watcher.py    # Следит за whitelist.json и применяет правила
    ├── fw_counters.sh         # Счётчики TSPUBLOCK/GOVBLOCK для дашборда
    ├── fw_analyze.py          # Парсинг kern.log — аналитика блокировок
    └── modules/
        ├── api.py             # GeoIP через ip-api.com (батч 100 IP)
        ├── stats.py           # Сбор и сохранение статистики
        ├── storage.py         # Работа с JSON файлами
        ├── geo.py             # Обогащение IP координатами
        └── system.py         # CPU, RAM, диск, логи
```

## Требования

- Ubuntu 20.04+ / Debian 11+
- Python 3.10+ с пакетами: `requests`, `urllib3`
- PHP 8.1+ с расширением `mbstring`
- nginx + php-fpm
- iptables + ipset
- Установленный и работающий [Telemt](https://github.com/telemt/telemt)

## Установка

### 1. Клонируй репозиторий

```bash
git clone https://github.com/upe4d/telemt-proxy-panel-bot-firewall.git
cd telemt-proxy-panel-bot-firewall
```

### 2. Установи зависимости Python

```bash
pip install requests urllib3 --break-system-packages
```

### 3. Скопируй файлы бота

```bash
mkdir -p /opt/telemt-bot/modules
cp -r bot/* /opt/telemt-bot/
```

### 4. Настрой config.py

Отредактируй `/opt/telemt-bot/config.py` — вставь токен Telemt API, домен, интервал сбора:

```python
TELEMT_API    = "http://127.0.0.1:9091"
TELEMT_TOKEN  = ""  # токен не нужен если в telemt.toml нет auth_header
SITE_DOMAIN   = "stats.yourdomain.com"
INTERVAL      = 600   # секунд между сборами
MAX_IPS       = 10000 # максимум IP в истории
```

### 5. Установи systemd сервисы

```bash
cp systemd/*.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now telemt-collector telemt-firewall
```

### 6. Настрой веб-дашборд

```bash
mkdir -p /var/www/stats/data /var/www/stats/assets /var/www/stats/tabs /var/www/stats/modules
cp index.php config.php /var/www/stats/
cp assets/* /var/www/stats/assets/
cp tabs/* /var/www/stats/tabs/
cp modules/* /var/www/stats/modules/
chown -R www-data:www-data /var/www/stats/data
chmod 755 /var/www/stats/data
```

### 7. Настрой nginx

```nginx
server {
    listen 4443 ssl;
    server_name stats.yourdomain.com;
    root /var/www/stats;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
    }

    location /data/ {
        location ~* \.json$ { allow all; }
        deny all;
    }
}
```

### 8. Крон задачи

```bash
crontab -e
```

```cron
# Сбор статистики GeoIP коллектором (systemd)
# Аналитика блокировок
*/10 * * * * python3 /opt/telemt-bot/fw_analyze.py >> /var/log/fw_analyze.log 2>&1
# Обновление CyberOK Skipa
0 3 * * * curl -fsSL https://stats.gptru.pro:4443/rst/update_cyberok.sh | bash >> /var/log/cyberok_update.log 2>&1
# Обновление GOVIPS
0 4 * * * /opt/update_govips.sh >> /var/log/govips.log 2>&1
# Счётчики firewall
* * * * * /opt/telemt-bot/fw_counters.sh
```

## Защита от ТСПУ

### TSPUBLOCK — CyberOK Skipa

Задокументированный список IP сканеров ТСПУ от независимых исследователей (ГРЧЦ, НКЦКИ).
Источник: [tread-lightly/CyberOK_Skipa_ips](https://github.com/tread-lightly/CyberOK_Skipa_ips) — ~145 IP, обновляется ежедневно.

```bash
# Создать ipset
ipset create TSPUIPS hash:net maxelem 65536

# Добавить цепочку iptables
iptables -N TSPUBLOCK
iptables -I INPUT 1 -j TSPUBLOCK
iptables -I TSPUBLOCK -p tcp --tcp-flags RST RST -m set --match-set TSPUIPS src -j DROP

# Включить логирование для аналитики
iptables -I TSPUBLOCK 1 -p tcp --tcp-flags RST RST \
  -m set --match-set TSPUIPS src \
  -j LOG --log-prefix "TSPUBLOCK: " --log-level 4

# Автообновление (скрипт update_cyberok.sh)
0 3 * * * curl -fsSL https://stats.gptru.pro:4443/rst/update_cyberok.sh | bash
```

### GOVIPS — Подсети госорганов РФ

Подсети Роскомнадзора, ФСБ, МВД, ГРЧЦ и других госорганов.
Источник: [C24Be/AS_Network_List](https://github.com/C24Be/AS_Network_List) — ~1145 подсетей, обновляется ежедневно.

```bash
# Создать ipset
ipset create GOVIPS hash:net maxelem 65536

# Добавить цепочку iptables
iptables -N GOVBLOCK
iptables -I INPUT 1 -j GOVBLOCK
iptables -I GOVBLOCK -p tcp --tcp-flags RST RST -m set --match-set GOVIPS src -j DROP

# Включить логирование для аналитики
iptables -I GOVBLOCK 1 -p tcp --tcp-flags RST RST \
  -m set --match-set GOVIPS src \
  -j LOG --log-prefix "GOVBLOCK: " --log-level 4

# Скрипт автообновления
cat > /opt/update_govips.sh << 'EOF'
#!/bin/bash
URL="https://raw.githubusercontent.com/C24Be/AS_Network_List/main/blacklists_iptables/blacklist-v4.ipset"
curl -s "$URL" | grep "^add blacklist-v4 " | sed 's/add blacklist-v4/add GOVIPS/' | while read line; do
    ipset $line 2>/dev/null
done
ipset save > /etc/ipset.conf
echo "[$(date '+%Y-%m-%d %H:%M:%S')] GOVIPS: $(ipset list GOVIPS | grep 'Number of entries' | awk '{print $NF}') записей"
EOF
chmod +x /opt/update_govips.sh
```

### Аналитика блокировок (fw_analyze.py)

Парсит kern.log и собирает в `data/fw_stats.json`:
- Количество задропанных пакетов и байт по каждой цепочке
- Топ IP источников RST
- Активность по часам суток (спарклайн)

```bash
# Запуск вручную
python3 /opt/telemt-bot/fw_analyze.py

# Крон каждые 10 минут
*/10 * * * * python3 /opt/telemt-bot/fw_analyze.py >> /var/log/fw_analyze.log 2>&1
```

## Маскировка IP пользователей

Для публичных дашбордов IP маскируются до вида `95.79.xxx.xxx` — реализовано в `app.js` и `tabs/*.php`.

## Сохранение правил iptables

```bash
# Сохранить ipset
ipset save > /etc/ipset.conf

# Сохранить iptables
netfilter-persistent save
# или
iptables-save > /etc/iptables/rules.v4
```

## Структура данных

```
/var/www/stats/data/
├── current.json         # Текущая статистика (подключения, IP, трафик, CPU/RAM)
├── history.json         # История по дням (до 30 дней)
├── hours.json           # Активность по часам суток
├── countries.json       # Статистика по странам
├── cities.json          # Статистика по городам
├── city_coords.json     # Координаты городов (кэш GeoIP)
├── ips.json             # История IP адресов с геолокацией
├── users.json           # Статистика пользователей telemt
├── new_vs_repeat.json   # Новые vs повторные IP за сегодня
├── new_by_day.json      # Новые IP по дням
├── country_traffic.json # Косвенный трафик по странам
├── whitelist.json       # Белый список стран и IP для GeoIP фильтра
└── fw_stats.json        # Статистика TSPUBLOCK/GOVBLOCK (fw_analyze.py)
```

## Возможности дашборда v2.0

### Обзор
- Ключевые метрики: подключения, активные IP, трафик, RST задропано, uptime
- Мини-карта активных пользователей
- Блок защиты TSPUBLOCK + GOVIPS с реальными счётчиками
- Топ стран с прогресс-барами
- Состояние системы: CPU, RAM, диск, логи

### Карта
- Полноэкранная интерактивная карта (Leaflet.js)
- Фильтр по стране — автозум на выбранную страну
- Маскировка IP в попапах

### Защита
- TSPUBLOCK и GOVIPS — пакеты, трафик, IP в списке
- Спарклайн активности по 24 часам
- Топ источников RST (из kern.log логирования)
- Информация об источниках списков

### Страны
- Таблица всех стран с поиском и скроллом
- Топ городов с прогресс-барами
- Косвенный трафик по странам
- Пояснение методологии расчёта

### Пользователи
- Таблица пользователей telemt с трафиком
- Новые vs повторные IP сегодня
- Топ IP по сессиям с геолокацией и датами

### История
- Графики подключений и трафика по дням (Chart.js)
- График активности по часам суток
- Таблица истории за все дни

### Фильтрация
- Включение/выключение GeoIP фильтра
- Добавление/удаление стран из белого списка
- Добавление персональных IP
- Защита паролем

## Telegram бот — команды

| Команда | Описание |
|---------|----------|
| `/start` | Список команд и ссылки |
| `/stats` | Статистика с графиком по часам |
| `/logs` | Файл логов telemt |
| `/backup` | Бэкап конфига telemt |
| `/digest` | Дайджест за сегодня |

## TCP Keepalive (рекомендуется)

Уменьшает время жизни залипших соединений до ~13 минут:

```bash
sysctl -w net.ipv4.tcp_keepalive_time=600
sysctl -w net.ipv4.tcp_keepalive_intvl=60
sysctl -w net.ipv4.tcp_keepalive_probes=3
echo "net.ipv4.tcp_keepalive_time=600" >> /etc/sysctl.conf
echo "net.ipv4.tcp_keepalive_intvl=60" >> /etc/sysctl.conf
echo "net.ipv4.tcp_keepalive_probes=3" >> /etc/sysctl.conf
```

## Стек технологий

**Backend:** Python 3, PHP 8.3  
**Frontend:** HTML, CSS (тёмно-синяя гамма), vanilla JS, Chart.js, Leaflet.js  
**GeoIP:** ip-api.com (батч до 100 IP за запрос)  
**Firewall:** iptables, ipset  
**Сервер:** nginx, php-fpm, systemd

## Лицензия

MIT

## Автор

[@upe4d](https://github.com/upe4d) — [t.me/u_pre](https://t.me/u_pre)  
Канал поддержки: [t.me/telemtrs](https://t.me/telemtrs)

## Связанные проекты

- [tspublock](https://github.com/upe4d/tspublock) — RST Block List, исследование ТСПУ
- [telemt](https://github.com/telemt/telemt) — MTProxy сервер на Rust
- [telemt_panel](https://github.com/amirotin/telemt_panel) — официальная панель управления
- [CyberOK_Skipa_ips](https://github.com/tread-lightly/CyberOK_Skipa_ips) — список сканеров ТСПУ
- [C24Be/AS_Network_List](https://github.com/C24Be/AS_Network_List) — подсети госорганов РФ
