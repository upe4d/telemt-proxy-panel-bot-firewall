"""
Модуль геолокации — координаты городов через Nominatim
"""

import requests
import time
from datetime import datetime

import sys
sys.path.insert(0, '/opt/telemt-bot')
import config
from modules.storage import load_json, save_json

def get_city_coords(city, country_name):
    """
    Получение координат города через Nominatim (OpenStreetMap).
    Результаты кэшируются в файл чтобы не делать лишних запросов.
    """
    coords = load_json(config.FILE_COORDS, {})
    key    = f"{city},{country_name}"

    # Уже есть в кэше
    if key in coords:
        return coords[key]

    try:
        r = requests.get(
            "https://nominatim.openstreetmap.org/search",
            params={"q": f"{city}, {country_name}", "format": "json", "limit": 1},
            headers={"User-Agent": "TelemetStats/3.0"},
            timeout=5
        )
        data = r.json()
        if data:
            result = {
                "lat":     float(data[0]["lat"]),
                "lon":     float(data[0]["lon"]),
                "city":    city,
                "country": country_name
            }
            coords[key] = result
            save_json(config.FILE_COORDS, coords)
            print(f"[{datetime.now():%H:%M:%S}] Координаты: {city} → {result['lat']:.2f},{result['lon']:.2f}")
            return result
    except Exception as e:
        print(f"[{datetime.now():%H:%M:%S}] Координаты ошибка {city}: {e}")

    # Сохраняем None чтобы не запрашивать повторно
    coords[key] = None
    save_json(config.FILE_COORDS, coords)
    return None

def enrich_ips_with_coords(ip_geo_map):
    """
    Обогащает словарь ip→гео координатами.
    Возвращает словарь ip→{country, country_name, city, lat, lon}
    """
    coords_cache = load_json(config.FILE_COORDS, {})

    # Собираем уникальные города которых нет в кэше
    cities_to_fetch = set()
    for geo in ip_geo_map.values():
        city         = geo.get("city", "")
        country_name = geo.get("country_name", "")
        if city and country_name:
            key = f"{city},{country_name}"
            if key not in coords_cache:
                cities_to_fetch.add((city, country_name))

    # Получаем координаты для новых городов
    for city, country_name in cities_to_fetch:
        get_city_coords(city, country_name)
        time.sleep(1)  # Уважаем лимиты Nominatim (1 запрос/сек)

    # Обновляем кэш после новых запросов
    coords_cache = load_json(config.FILE_COORDS, {})

    # Обогащаем данные координатами
    result = {}
    for ip, geo in ip_geo_map.items():
        city         = geo.get("city", "")
        country_name = geo.get("country_name", "")
        entry        = dict(geo)
        key          = f"{city},{country_name}"
        coords       = coords_cache.get(key)
        if coords:
            entry["lat"] = coords["lat"]
            entry["lon"] = coords["lon"]
        result[ip] = entry

    return result
