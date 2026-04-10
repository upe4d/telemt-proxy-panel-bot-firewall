#!/usr/bin/env python3
"""
Анализ логов iptables TSPUBLOCK и GOVBLOCK
Собирает топ IP, провайдеров, динамику по часам
"""
import re, json, subprocess
from datetime import datetime, timezone
from collections import defaultdict
from modules.storage import save_json

DATA_DIR = "/var/www/stats/data"
FILE_FW = f"{DATA_DIR}/fw_stats.json"
LOG_FILE = "/var/log/kern.log"

def parse_logs():
    """Парсим kern.log — собираем данные по блокировкам"""
    tspublock = defaultdict(int)
    govblock   = defaultdict(int)
    hours_tsp  = defaultdict(int)
    hours_gov  = defaultdict(int)

    try:
        with open(LOG_FILE) as f:
            for line in f:
                if "TSPUBLOCK:" in line:
                    m = re.search(r'SRC=(\d+\.\d+\.\d+\.\d+)', line)
                    h = re.search(r'T(\d{2}):', line)
                    if m: tspublock[m.group(1)] += 1
                    if h: hours_tsp[h.group(1)] += 1
                elif "GOVBLOCK:" in line:
                    m = re.search(r'SRC=(\d+\.\d+\.\d+\.\d+)', line)
                    h = re.search(r'T(\d{2}):', line)
                    if m: govblock[m.group(1)] += 1
                    if h: hours_gov[h.group(1)] += 1
    except Exception as e:
        print(f"Ошибка чтения лога: {e}")
        return None

    # Топ 20 IP
    top_tsp = sorted(tspublock.items(), key=lambda x: x[1], reverse=True)[:20]
    top_gov = sorted(govblock.items(),  key=lambda x: x[1], reverse=True)[:20]

    # Счётчики iptables (точные)
    def get_iptables_counters(chain):
        try:
            r = subprocess.check_output(
                ['iptables', '-L', chain, '-v', '-n', '-x'],
                stderr=subprocess.DEVNULL
            ).decode()
            for line in r.splitlines():
                if 'DROP' in line:
                    parts = line.split()
                    return {'pkts': int(parts[0]), 'bytes': int(parts[1])}
        except:
            pass
        return {'pkts': 0, 'bytes': 0}

    tsp_cnt = get_iptables_counters('TSPUBLOCK')
    gov_cnt = get_iptables_counters('GOVBLOCK')

    return {
        'updated_at': datetime.now(timezone.utc).strftime('%Y-%m-%d %H:%M:%S UTC'),
        'tspublock': {
            'pkts':     tsp_cnt['pkts'],
            'bytes':    tsp_cnt['bytes'],
            'top_ips':  top_tsp,
            'by_hour':  dict(hours_tsp),
        },
        'govblock': {
            'pkts':     gov_cnt['pkts'],
            'bytes':    gov_cnt['bytes'],
            'top_ips':  top_gov,
            'by_hour':  dict(hours_gov),
        },
        'total_pkts':  tsp_cnt['pkts'] + gov_cnt['pkts'],
        'total_bytes': tsp_cnt['bytes'] + gov_cnt['bytes'],
    }

if __name__ == '__main__':
    data = parse_logs()
    if data:
        save_json(FILE_FW, data)
        print(f"[{datetime.now():%H:%M:%S}] TSPUBLOCK: {data['tspublock']['pkts']} pkts, GOVBLOCK: {data['govblock']['pkts']} pkts")
