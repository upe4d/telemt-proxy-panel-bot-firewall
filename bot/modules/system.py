"""
Модуль системных метрик — CPU, RAM, диск, логи
"""

import time
import subprocess

def get_cpu():
    """Загрузка CPU в процентах"""
    try:
        with open("/proc/stat") as f:
            l1 = f.readline()
        f1 = list(map(int, l1.split()[1:]))
        time.sleep(0.2)
        with open("/proc/stat") as f:
            l2 = f.readline()
        f2 = list(map(int, l2.split()[1:]))
        idle  = f2[3] - f1[3]
        total = sum(f2) - sum(f1)
        return round(100 * (1 - idle / total), 1) if total else 0
    except:
        return 0

def get_ram():
    """Использование RAM в процентах"""
    try:
        mem = {}
        with open("/proc/meminfo") as f:
            for line in f:
                parts = line.split()
                if len(parts) >= 2:
                    mem[parts[0].rstrip(":")] = int(parts[1])
        total = mem.get("MemTotal", 1)
        free  = mem.get("MemFree", 0) + mem.get("Buffers", 0) + mem.get("Cached", 0)
        return round((total - free) / total * 100, 1)
    except:
        return 0

def get_disk():
    """Использование диска"""
    try:
        result = subprocess.run(["df", "-h", "/"], capture_output=True, text=True)
        parts  = result.stdout.split("\n")[1].split()
        return {
            "total": parts[1],
            "used":  parts[2],
            "free":  parts[3],
            "pct":   parts[4]
        }
    except:
        return {}

def get_logs_size():
    """Размер логов journald"""
    try:
        result = subprocess.run(
            ["journalctl", "--disk-usage"],
            capture_output=True, text=True
        )
        parts = result.stdout.strip().split("take up ")
        return parts[1].split(" ")[0] if len(parts) > 1 else "N/A"
    except:
        return "N/A"
