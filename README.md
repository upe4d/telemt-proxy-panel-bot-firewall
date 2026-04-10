# Telemt MTProxy — Panel, Bot & Firewall

Полный стек для управления MTProxy сервером на базе [Telemt](https://github.com/telemt/telemt):
- 📊 Веб-дашборд со статистикой, картой, GeoIP
- 🤖 Telegram бот с уведомлениями и отчётами
- 🛡️ Firewall с белым списком стран и IP
- 🔒 Защищённое управление с паролем
- 🏛️ Блокировка подсетей госорганов РФ (GOVIPS)

## Скриншоты
> Dashboard, карта пользователей, блокировки, белый список


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
    listen 4443;
    server_name stats.yourdomain.com;
    root /var/www/stats;
    index index.php;

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

## Маскировка IP пользователей (опционально)

По умолчанию дашборд показывает полные IP пользователей в таблицах и на карте. Если дашборд публичный — рекомендуется маскировать IP до вида `95.79.xxx.xxx`.

**Вариант 1 — показывать полные IP** (по умолчанию, ничего не менять)

**Вариант 2 — маскировать IP** (рекомендуется для публичных дашбордов)

Добавь функцию в начало `dashboard/index.php` после открывающего `<?php`:

```php
// Маскировка IP — показываем только первые два октета
function mask_ip(string $ip): string {
    if (empty($ip)) return '';
    $parts = explode('.', $ip);
    if (count($parts) === 4) {
        return $parts[0] . '.' . $parts[1] . '.xxx.xxx';
    }
    return 'xxxx:xxxx:…'; // IPv6
}
```

Затем замени вывод IP в трёх местах — вместо:
```php
<?= htmlspecialchars($ip_data['ip']??'') ?>
```
используй:
```php
<?= mask_ip($ip_data['ip']??'') ?>
```

И в `dashboard/modules/data.php` строку формирования точек карты:
```php
// было:
$map_cities[$key]['ips'][] = $ip_data['ip'];
// стало:
$_ip = $ip_data['ip'];
$_p = explode('.', $_ip);
$map_cities[$key]['ips'][] = count($_p)===4 ? $_p[0].'.'.$_p[1].'.xxx.xxx' : 'xxxx:…';
```

## Блокировка подсетей госорганов РФ (GOVIPS)

Дополнительный ipset для блокировки RST от подсетей Роскомнадзора, ФСБ, МВД и других госорганов.  
Источник: [C24Be/AS_Network_List](https://github.com/C24Be/AS_Network_List) — ~1145 подсетей, обновляется ежедневно.

```bash
# Создать ipset и загрузить подсети
ipset create GOVIPS hash:net maxelem 65536
curl -s https://raw.githubusercontent.com/C24Be/AS_Network_List/main/blacklists_iptables/blacklist-v4.ipset \
  | grep "^add blacklist-v4 " \
  | sed 's/add blacklist-v4/add GOVIPS/' \
  | while read line; do ipset $line 2>/dev/null; done

# Добавить правило iptables
iptables -N GOVBLOCK 2>/dev/null
iptables -I INPUT 1 -j GOVBLOCK
iptables -I GOVBLOCK -p tcp --tcp-flags RST RST -m set --match-set GOVIPS src -j DROP

# Сохранить
ipset save > /etc/ipset.conf && netfilter-persistent save

# Скрипт автообновления (4:00 ежедневно)
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
(crontab -l 2>/dev/null; echo "0 4 * * * /opt/update_govips.sh >> /var/log/govips.log 2>&1") | crontab -
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
- 🔒 Маскировка IP пользователей (опционально)

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

## Связанные проекты

- [tspublock](https://github.com/upe4d/tspublock) — RST Block List, GOVIPS блокировка
- [telemt](https://github.com/telemt/telemt) — MTProxy сервер
- [telemt_panel](https://github.com/amirotin/telemt_panel) — панель управления
- [C24Be/AS_Network_List](https://github.com/C24Be/AS_Network_List) — подсети госорганов РФ
