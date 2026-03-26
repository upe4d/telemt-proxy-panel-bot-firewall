"""
Конфигурация коллектора Telemt
"""

# === API Telemt ===
TELEMT_API  = "http://127.0.0.1:9091"
TELEMT_AUTH = "Bearer YOUR_API_TOKEN"

# === Панель управления ===
PANEL_URL  = "https://panel.yourdomain.com:8443"
PANEL_USER = "admin"
PANEL_PASS = "YOUR_PANEL_PASSWORD"

# === Пути к данным ===
DATA_DIR = "/var/www/stats/data"

# === Файлы данных ===
FILE_CURRENT         = f"{DATA_DIR}/current.json"
FILE_HISTORY         = f"{DATA_DIR}/history.json"
FILE_HOURS           = f"{DATA_DIR}/hours.json"
FILE_COUNTRIES       = f"{DATA_DIR}/countries.json"
FILE_CITIES          = f"{DATA_DIR}/cities.json"
FILE_IPS             = f"{DATA_DIR}/ips.json"
FILE_USERS           = f"{DATA_DIR}/users.json"
FILE_NVR             = f"{DATA_DIR}/new_vs_repeat.json"
FILE_COORDS          = f"{DATA_DIR}/city_coords.json"
FILE_NEW_BY_DAY      = f"{DATA_DIR}/new_by_day.json"
FILE_COUNTRY_TRAFFIC = f"{DATA_DIR}/country_traffic.json"

# === Параметры сбора ===
INTERVAL = 300  # секунд между сборами (5 минут)
MAX_IPS  = 5000 # максимум IP в истории
MAX_DAYS = 30   # максимум дней в истории
