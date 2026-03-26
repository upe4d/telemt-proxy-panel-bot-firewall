#!/usr/bin/env python3
"""
Telemt MTProxy — Коллектор статистики v3
Запускает модули сбора данных каждые N секунд
"""

import sys
import os
import time

# Добавляем путь к модулям
sys.path.insert(0, '/opt/telemt-bot')

import config
from modules.api     import panel_login
from modules.stats   import collect
from modules.storage import load_json

def main():
    print("=" * 50)
    print("  Telemt Collector v3")
    print("=" * 50)

    os.makedirs(config.DATA_DIR, exist_ok=True)

    # Логинимся в панель при старте
    panel_login()

    while True:
        try:
            collect()
        except Exception as e:
            print(f"[ОШИБКА] {e}")
            import traceback
            traceback.print_exc()
        time.sleep(config.INTERVAL)

if __name__ == "__main__":
    main()
