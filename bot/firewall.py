#!/usr/bin/env python3
"""
Модуль управления firewall для Telemt MTProxy
Поддержка IPv4/IPv6, логирование, GeoIP обогащение

@version 4.0.0
@updated 2026-03-24

CHANGELOG:
v4.0.0 - GeoIP обогащение заблокированных IP
v3.0.0 - Логирование заблокированных IP
v2.0.0 - Поддержка IPv6
v1.0.0 - Базовая версия
"""

import subprocess
import json
import re
import os
import sys
import time
import requests
import urllib3
from datetime import datetime

urllib3.disable_warnings()
sys.path.insert(0, '/opt/telemt-bot')

DATA_DIR      = "/var/www/stats/data"
FILE_WL       = f"{DATA_DIR}/whitelist.json"
FILE_BLOCKED  = f"{DATA_DIR}/blocked.json"
PROXY_PORT    = "443"
CHAIN4        = "TELEMT_WL"
CHAIN6        = "TELEMT_WL6"
LOG_PREFIX4   = "TELEMT_BLOCK4: "
LOG_PREFIX6   = "TELEMT_BLOCK6: "

# Настройки панели для GeoIP
PANEL_URL  = "https://panel.yourdomain.com:8443"
PANEL_USER = "admin"
PANEL_PASS = "YOUR_PANEL_PASSWORD"

_panel_session    = requests.Session()
_panel_session.verify = False
_panel_logged_in  = False

def panel_login():
    global _panel_logged_in
    try:
        r = _panel_session.post(
            f"{PANEL_URL}/api/auth/login",
            json={"username": PANEL_USER, "password": PANEL_PASS},
            timeout=10
        )
        if r.json().get("ok"):
            _panel_logged_in = True
            return True
    except:
        pass
    return False

def geoip_lookup(ips):
    global _panel_logged_in
    if not ips:
        return []
    if not _panel_logged_in:
        panel_login()
    try:
        r = _panel_session.post(
            f"{PANEL_URL}/api/geoip/lookup",
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
    except:
        pass
    return []

def run(cmd, check=True):
    result = subprocess.run(cmd, shell=True, capture_output=True, text=True)
    if check and result.returncode != 0:
        print(f"[ОШИБКА] {cmd}\n{result.stderr.strip()}")
    return result

def load_json(path, default=None):
    try:
        if os.path.exists(path):
            with open(path) as f:
                return json.load(f)
    except:
        pass
    return default if default is not None else {}

def save_json(path, data):
    with open(path, 'w') as f:
        json.dump(data, f, ensure_ascii=False, indent=2)

def load_whitelist():
    return load_json(FILE_WL)

def is_ipv6(ip):
    return ':' in ip

def chain4_exists():
    return run(f"iptables -L {CHAIN4} -n", check=False).returncode == 0

def chain6_exists():
    return run(f"ip6tables -L {CHAIN6} -n", check=False).returncode == 0

def flush_chain4():
    if chain4_exists():
        run(f"iptables -D INPUT -p tcp --dport {PROXY_PORT} -j {CHAIN4}", check=False)
        run(f"iptables -F {CHAIN4}", check=False)
        run(f"iptables -X {CHAIN4}", check=False)
    print("[firewall] IPv4 цепочка очищена")

def flush_chain6():
    if chain6_exists():
        run(f"ip6tables -D INPUT -p tcp --dport {PROXY_PORT} -j {CHAIN6}", check=False)
        run(f"ip6tables -F {CHAIN6}", check=False)
        run(f"ip6tables -X {CHAIN6}", check=False)
    print("[firewall] IPv6 цепочка очищена")

def apply_rules(wl):
    countries = wl.get('allowed_countries', [])
    ips       = wl.get('allowed_ips', {})
    ips4      = {ip: n for ip, n in ips.items() if not is_ipv6(ip)}
    ips6      = {ip: n for ip, n in ips.items() if is_ipv6(ip)}

    # === IPv4 ===
    flush_chain4()
    run(f"iptables -N {CHAIN4}")
    run(f"iptables -A {CHAIN4} -s 127.0.0.0/8 -j ACCEPT")
    for ip in ips4:
        run(f"iptables -A {CHAIN4} -s {ip} -j ACCEPT", check=False)
        print(f"[firewall] IPv4 разрешён: {ip}")
    for country in countries:
        r = run(f"iptables -A {CHAIN4} -m geoip --src-cc {country} -j ACCEPT", check=False)
        if r.returncode == 0:
            print(f"[firewall] IPv4 страна: {country}")
        else:
            print(f"[firewall] IPv4 GeoIP ошибка {country}: {r.stderr.strip()}")
    run(f'iptables -A {CHAIN4} -m limit --limit 5/min -j LOG --log-prefix "{LOG_PREFIX4}" --log-level 4')
    run(f"iptables -A {CHAIN4} -j DROP")
    run(f"iptables -I INPUT -p tcp --dport {PROXY_PORT} -j {CHAIN4}")
    print(f"[firewall] IPv4 применено: {len(countries)} стран, {len(ips4)} IP")

    # === IPv6 ===
    flush_chain6()
    run(f"ip6tables -N {CHAIN6}")
    run(f"ip6tables -A {CHAIN6} -s ::1/128 -j ACCEPT")
    for ip in ips6:
        run(f"ip6tables -A {CHAIN6} -s {ip} -j ACCEPT", check=False)
        print(f"[firewall] IPv6 разрешён: {ip}")
    for country in countries:
        r = run(f"ip6tables -A {CHAIN6} -m geoip --src-cc {country} -j ACCEPT", check=False)
        if r.returncode == 0:
            print(f"[firewall] IPv6 страна: {country}")
        else:
            print(f"[firewall] IPv6 GeoIP ошибка {country}: {r.stderr.strip()}")
    run(f'ip6tables -A {CHAIN6} -m limit --limit 5/min -j LOG --log-prefix "{LOG_PREFIX6}" --log-level 4')
    run(f"ip6tables -A {CHAIN6} -j DROP")
    run(f"ip6tables -I INPUT -p tcp --dport {PROXY_PORT} -j {CHAIN6}")
    print(f"[firewall] IPv6 применено: {len(countries)} стран, {len(ips6)} IP")

def disable_rules():
    flush_chain4()
    flush_chain6()
    print("[firewall] Фильтрация отключена")

def is_bot_like(ip_data):
    """
    Определяет похоже ли на бота по признакам:
    - Много попыток подключения
    - IP из датацентра (DE, NL, US, FR, GB, SG)
    - Короткий промежуток между первым и последним визитом
    """
    score = 0
    reasons = []
    count   = ip_data.get('count', 0)
    country = ip_data.get('geo', {}).get('country', '')
    city    = ip_data.get('geo', {}).get('city', '')

    # Много попыток — явный бот
    if count >= 20:
        score += 3
        reasons.append(f"много попыток ({count})")
    elif count >= 10:
        score += 2
        reasons.append(f"повторные попытки ({count})")
    elif count >= 3:
        score += 1
        reasons.append(f"несколько попыток ({count})")

    # Датацентровые страны
    dc_countries = ['DE', 'NL', 'US', 'FR', 'GB', 'SG', 'FI', 'SE', 'CA', 'JP', 'AU']
    if country in dc_countries:
        score += 2
        reasons.append(f"датацентровая страна ({country})")

    # Датацентровые города
    dc_cities = ['Frankfurt', 'Amsterdam', 'London', 'Paris', 'Singapore',
                 'New York', 'Chicago', 'Seattle', 'Tokyo', 'Stockholm']
    for dc_city in dc_cities:
        if dc_city.lower() in city.lower():
            score += 1
            reasons.append(f"датацентровый город ({city})")
            break

    # Определяем вердикт
    if score >= 4:
        verdict = "🤖 Бот"
        recommendation = "Блокировать"
    elif score >= 2:
        verdict = "⚠️ Подозрительно"
        recommendation = "Возможно бот"
    else:
        verdict = "👤 Возможно пользователь"
        recommendation = "Добавить в белый список"

    return {
        "score":          score,
        "verdict":        verdict,
        "recommendation": recommendation,
        "reasons":        reasons
    }

def parse_blocked_logs():
    """Парсит системные логи и обогащает данные через GeoIP"""
    blocked = load_json(FILE_BLOCKED, {
        "total4": 0, "total6": 0,
        "ips": {}, "last_updated": ""
    })

    result = run("journalctl -k --since '1 hour ago' --no-pager", check=False)
    if result.returncode != 0:
        return blocked

    lines     = result.stdout.split('\n')
    new_ips   = set()
    new_count = 0

    for line in lines:
        # IPv4
        if LOG_PREFIX4 in line:
            ip_match = re.search(r'SRC=(\d+\.\d+\.\d+\.\d+)', line)
            if ip_match:
                ip = ip_match.group(1)
                if ip not in blocked["ips"]:
                    blocked["ips"][ip] = {
                        "count": 0, "first": "", "last": "",
                        "proto": "ipv4", "geo": {}
                    }
                    new_ips.add(ip)
                blocked["ips"][ip]["count"] += 1
                blocked["ips"][ip]["last"]   = datetime.now().strftime("%Y-%m-%d %H:%M")
                if not blocked["ips"][ip]["first"]:
                    blocked["ips"][ip]["first"] = blocked["ips"][ip]["last"]
                blocked["total4"] += 1
                new_count += 1

        # IPv6
        elif LOG_PREFIX6 in line:
            ip_match = re.search(r'SRC=([0-9a-f:]+)', line)
            if ip_match:
                ip = ip_match.group(1)
                if ip not in blocked["ips"]:
                    blocked["ips"][ip] = {
                        "count": 0, "first": "", "last": "",
                        "proto": "ipv6", "geo": {}
                    }
                    new_ips.add(ip)
                blocked["ips"][ip]["count"] += 1
                blocked["ips"][ip]["last"]   = datetime.now().strftime("%Y-%m-%d %H:%M")
                if not blocked["ips"][ip]["first"]:
                    blocked["ips"][ip]["first"] = blocked["ips"][ip]["last"]
                blocked["total6"] += 1
                new_count += 1

    # Обогащаем новые IP через GeoIP
    ips_without_geo = [
        ip for ip, data in blocked["ips"].items()
        if not data.get("geo") or not data["geo"].get("country")
    ]

    if ips_without_geo:
        print(f"[firewall] GeoIP обогащение: {len(ips_without_geo)} IP...")
        panel_login()
        # Обрабатываем батчами по 100
        for i in range(0, len(ips_without_geo), 100):
            batch   = ips_without_geo[i:i+100]
            results = geoip_lookup(batch)
            for g in results:
                ip = g["ip"]
                if ip in blocked["ips"]:
                    blocked["ips"][ip]["geo"] = {
                        "country":      g.get("country", "??"),
                        "country_name": g.get("country_name", "Unknown"),
                        "city":         g.get("city", ""),
                    }
            if len(ips_without_geo) > 100:
                time.sleep(0.5)

    # Добавляем анализ бот/пользователь
    for ip, data in blocked["ips"].items():
        data["analysis"] = is_bot_like(data)

    # Ограничиваем до 1000 IP — оставляем с наибольшим количеством попыток
    if len(blocked["ips"]) > 1000:
        sorted_ips    = sorted(blocked["ips"].items(), key=lambda x: x[1]["count"], reverse=True)
        blocked["ips"] = dict(sorted_ips[:1000])

    blocked["last_updated"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    save_json(FILE_BLOCKED, blocked)

    if new_count > 0:
        print(f"[firewall] Новых блокировок: {new_count}, уникальных IP: {len(blocked['ips'])}")

    return blocked

def get_counters():
    result4 = run(f"iptables -L {CHAIN4} -n -v", check=False)
    result6 = run(f"ip6tables -L {CHAIN6} -n -v", check=False)
    drop4 = drop6 = 0
    for line in result4.stdout.split('\n'):
        if ' DROP ' in line:
            parts = line.split()
            try: drop4 = int(parts[0])
            except: pass
    for line in result6.stdout.split('\n'):
        if ' DROP ' in line:
            parts = line.split()
            try: drop6 = int(parts[0])
            except: pass
    return {"blocked_ipv4": drop4, "blocked_ipv6": drop6}

def status():
    print("=== IPv4 ===")
    r4 = run(f"iptables -L {CHAIN4} -n -v", check=False)
    print(r4.stdout if r4.returncode == 0 else "IPv4 цепочка не активна")
    print("\n=== IPv6 ===")
    r6 = run(f"ip6tables -L {CHAIN6} -n -v", check=False)
    print(r6.stdout if r6.returncode == 0 else "IPv6 цепочка не активна")
    c = get_counters()
    print(f"\nЗаблокировано IPv4: {c['blocked_ipv4']}")
    print(f"Заблокировано IPv6: {c['blocked_ipv6']}")

def sync():
    wl = load_whitelist()
    if not wl:
        print("[firewall] Белый список не найден")
        return
    if wl.get('enabled'):
        print("[firewall] Фильтр ВКЛЮЧЁН — применяем правила...")
        apply_rules(wl)
    else:
        print("[firewall] Фильтр ВЫКЛЮЧЕН — снимаем правила...")
        disable_rules()

if __name__ == "__main__":
    cmd = sys.argv[1] if len(sys.argv) > 1 else "sync"
    if cmd == "sync":      sync()
    elif cmd == "status":  status()
    elif cmd == "disable": disable_rules()
    elif cmd == "logs":    parse_blocked_logs()
    else: print(f"Использование: {sys.argv[0]} [sync|status|disable|logs]")
