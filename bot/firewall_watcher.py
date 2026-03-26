#!/usr/bin/env python3
"""
Следит за изменениями whitelist.json и применяет правила firewall
Каждые 5 минут парсит логи заблокированных IP
"""

import time
import os
import sys
sys.path.insert(0, '/opt/telemt-bot')
import firewall

DATA_DIR = "/var/www/stats/data"
FILE_WL  = f"{DATA_DIR}/whitelist.json"
LOG_INTERVAL = 300  # парсим логи каждые 5 минут

def get_mtime():
    try:
        return os.path.getmtime(FILE_WL)
    except:
        return 0

def main():
    print("=== Telemt Firewall Watcher v2 запущен ===")
    firewall.sync()
    last_mtime    = get_mtime()
    last_log_time = time.time()

    while True:
        time.sleep(5)
        now = time.time()

        # Проверяем изменения белого списка
        mtime = get_mtime()
        if mtime != last_mtime:
            print(f"[watcher] Белый список изменён — обновляем правила...")
            firewall.sync()
            last_mtime = mtime

        # Парсим логи каждые 5 минут
        if now - last_log_time >= LOG_INTERVAL:
            firewall.parse_blocked_logs()
            last_log_time = now

if __name__ == "__main__":
    main()
