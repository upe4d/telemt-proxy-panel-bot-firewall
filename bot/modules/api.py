"""
Модуль запросов к API Telemt и панели управления
"""

import requests
import urllib3
from datetime import datetime
urllib3.disable_warnings()

import sys
sys.path.insert(0, '/opt/telemt-bot')
import config

# === Сессия панели ===
_panel_session   = requests.Session()
_panel_session.verify = False
_panel_logged_in = False

def panel_login():
    """Логин в панель"""
    global _panel_logged_in
    try:
        r = _panel_session.post(
            f"{config.PANEL_URL}/api/auth/login",
            json={"username": config.PANEL_USER, "password": config.PANEL_PASS},
            timeout=10
        )
        if r.json().get("ok"):
            _panel_logged_in = True
            print(f"[{datetime.now():%H:%M:%S}] Панель: залогинились")
            return True
    except Exception as e:
        print(f"[{datetime.now():%H:%M:%S}] Панель: ошибка логина: {e}")
    return False

def geoip_lookup(ips):
    """GeoIP через панель — страна, город по списку IP"""
    global _panel_logged_in
    if not ips:
        return []
    if not _panel_logged_in:
        panel_login()
    try:
        r = _panel_session.post(
            f"{config.PANEL_URL}/api/geoip/lookup",
            json={"ips": list(ips)},
            timeout=10
        )
        data = r.json()
        if data.get("ok"):
            return data.get("data", [])
        if data.get("error", {}).get("code") == "unauthorized":
            _panel_logged_in = False
            if panel_login():
                return geoip_lookup(ips)
    except Exception as e:
        print(f"[{datetime.now():%H:%M:%S}] GeoIP ошибка: {e}")
    return []

def telemt_get(endpoint):
    """GET запрос к API telemt"""
    try:
        r = requests.get(
            f"{config.TELEMT_API}{endpoint}",
            headers={"Authorization": config.TELEMT_AUTH},
            timeout=5
        )
        data = r.json()
        if data.get("ok"):
            return data.get("data")
    except:
        pass
    return None

def get_system_info():
    """Информация о системе telemt"""
    return telemt_get("/v1/system/info")

def get_users():
    """Список пользователей с статистикой"""
    return telemt_get("/v1/users") or []
