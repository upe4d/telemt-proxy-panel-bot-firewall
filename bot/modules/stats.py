"""
Модуль сбора и сохранения статистики
"""

from datetime import datetime, timezone
import config
from modules.storage import load_json, save_json
from modules.api     import geoip_lookup, get_system_info, get_users
from modules.geo     import enrich_ips_with_coords
from modules.system  import get_cpu, get_ram, get_disk, get_logs_size

def collect():
    """Основной сбор статистики"""
    print(f"[{datetime.now():%H:%M:%S}] Сбор данных...")

    # --- Данные от telemt ---
    system = get_system_info()
    users  = get_users()

    now     = datetime.now(timezone.utc)
    ts      = int(now.timestamp())
    day_key = now.strftime("%Y-%m-%d")
    hour    = str(now.hour)

    # --- Суммарная статистика по пользователям ---
    total_connections = 0
    total_traffic     = 0
    all_active_ips    = []
    users_stats       = []

    for u in users:
        conns   = u.get("current_connections", 0)
        traffic = u.get("total_octets", 0)
        a_ips   = u.get("active_unique_ips_list", [])

        total_connections += conns
        total_traffic     += traffic
        all_active_ips.extend(a_ips)

        users_stats.append({
            "username":        u.get("username"),
            "connections":     conns,
            "active_ips":      u.get("active_unique_ips", 0),
            "recent_ips":      u.get("recent_unique_ips", 0),
            "traffic":         traffic,
            "active_ips_list": a_ips,
        })

    unique_ips = list(set(all_active_ips))

    # --- GeoIP и координаты ---
    geo_results = geoip_lookup(unique_ips)
    ip_geo_raw  = {g["ip"]: {
        "country":      g.get("country", "??"),
        "country_name": g.get("country_name", "Unknown"),
        "city":         g.get("city", ""),
    } for g in geo_results}

    ip_geo = enrich_ips_with_coords(ip_geo_raw)

    # --- Обновляем историю IP ---
    ips_history = load_json(config.FILE_IPS, {})
    for ip in unique_ips:
        geo = ip_geo.get(ip, {})
        if ip not in ips_history:
            ips_history[ip] = {"first_seen": ts, "last_seen": ts, "sessions": 0, "geo": geo}
        ips_history[ip]["last_seen"] = ts
        ips_history[ip]["sessions"] += 1
        ips_history[ip]["geo"]       = geo

    # Ограничиваем размер
    if len(ips_history) > config.MAX_IPS:
        items = sorted(ips_history.items(), key=lambda x: x[1]["last_seen"])
        ips_history = dict(items[-config.MAX_IPS:])
    save_json(config.FILE_IPS, ips_history)

    # --- Страны ---
    countries = load_json(config.FILE_COUNTRIES, {})
    for ip in unique_ips:
        geo          = ip_geo.get(ip, {})
        country      = geo.get("country", "??")
        country_name = geo.get("country_name", "Unknown")
        if country not in countries:
            countries[country] = {"name": country_name, "sessions": 0, "ips": []}
        countries[country]["sessions"] += 1
        if ip not in countries[country]["ips"]:
            countries[country]["ips"].append(ip)
    save_json(config.FILE_COUNTRIES, countries)

    # --- Города ---
    cities = load_json(config.FILE_CITIES, {})
    for ip in unique_ips:
        geo          = ip_geo.get(ip, {})
        country      = geo.get("country", "??")
        country_name = geo.get("country_name", "Unknown")
        city         = geo.get("city", "")
        if not city:
            continue
        key = f"{country}:{city}"
        if key not in cities:
            cities[key] = {"city": city, "country": country, "country_name": country_name, "sessions": 0, "ips": []}
        cities[key]["sessions"] += 1
        if ip not in cities[key]["ips"]:
            cities[key]["ips"].append(ip)
    save_json(config.FILE_CITIES, cities)

    # --- Трафик по странам (косвенный) ---
    traffic_per_ip  = total_traffic / max(len(unique_ips), 1)
    country_traffic = load_json(config.FILE_COUNTRY_TRAFFIC, {})
    for ip in unique_ips:
        geo     = ip_geo.get(ip, {})
        country = geo.get("country", "??")
        name    = geo.get("country_name", "Unknown")
        if country not in country_traffic:
            country_traffic[country] = {"name": name, "bytes": 0, "sessions": 0}
        country_traffic[country]["bytes"]    += traffic_per_ip
        country_traffic[country]["sessions"] += 1
    save_json(config.FILE_COUNTRY_TRAFFIC, country_traffic)

    # --- Новые vs повторные IP ---
    today_start  = int(now.replace(hour=0, minute=0, second=0, microsecond=0).timestamp())
    new_today    = [ip for ip in unique_ips if ips_history.get(ip, {}).get("first_seen", 0) >= today_start]
    repeat_today = [ip for ip in unique_ips if ips_history.get(ip, {}).get("first_seen", 0) < today_start]
    save_json(config.FILE_NVR, {
        "date": day_key, "new": len(new_today),
        "repeat": len(repeat_today), "new_ips": new_today,
    })

    # --- Новые IP по дням ---
    new_by_day = load_json(config.FILE_NEW_BY_DAY, {})
    if day_key not in new_by_day:
        new_by_day[day_key] = 0
    new_by_day[day_key] = max(new_by_day[day_key], len(new_today))
    if len(new_by_day) > config.MAX_DAYS:
        del new_by_day[sorted(new_by_day.keys())[0]]
    save_json(config.FILE_NEW_BY_DAY, new_by_day)

    # --- Активные IP с гео для дашборда ---
    active_ips_list = []
    for ip in unique_ips:
        geo   = ip_geo.get(ip, {})
        entry = {"ip": ip, **geo}
        active_ips_list.append(entry)

    # --- Текущая статистика ---
    current = {
        "timestamp":         ts,
        "datetime":          now.strftime("%Y-%m-%d %H:%M:%S UTC"),
        "online":            system is not None,
        "cpu":               get_cpu(),
        "ram":               get_ram(),
        "disk":              get_disk(),
        "logs_size":         get_logs_size(),
        "total_connections": total_connections,
        "active_ips":        len(unique_ips),
        "total_traffic":     total_traffic,
        "uptime_seconds":    system.get("uptime_seconds", 0) if system else 0,
        "version":           system.get("version", "") if system else "",
        "active_ips_list":   active_ips_list,
    }
    save_json(config.FILE_CURRENT, current)
    save_json(config.FILE_USERS, users_stats)

    # --- История по дням ---
    history = load_json(config.FILE_HISTORY, {})
    if day_key not in history:
        history[day_key] = {"connections": 0, "traffic": 0, "peak_ips": 0, "unique_ips": []}
    d = history[day_key]
    d["connections"] = max(d["connections"], total_connections)
    d["traffic"]     = max(d["traffic"], total_traffic)
    d["peak_ips"]    = max(d["peak_ips"], len(unique_ips))
    for ip in unique_ips:
        if ip not in d["unique_ips"]:
            d["unique_ips"].append(ip)
    if len(history) > config.MAX_DAYS:
        del history[sorted(history.keys())[0]]
    save_json(config.FILE_HISTORY, history)

    # --- Активность по часам ---
    hours = load_json(config.FILE_HOURS, {str(i): 0 for i in range(24)})
    hours[hour] = max(int(hours.get(hour, 0)), len(unique_ips))
    save_json(config.FILE_HOURS, hours)

    print(f"[{datetime.now():%H:%M:%S}] Готово. "
          f"Подключений:{total_connections} IP:{len(unique_ips)} "
          f"CPU:{current['cpu']}% Стран:{len(countries)} Новых:{len(new_today)}")
