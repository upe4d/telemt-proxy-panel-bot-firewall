#!/usr/bin/env python3
"""
Telegram бот мониторинга Telemt MTProxy
Команды: /start, /stats, /logs, /report, /backup
Утренний дайджест в 9:00
"""

import requests
import json
import os
import time
import subprocess
import threading
from datetime import datetime, timezone

# === НАСТРОЙКИ ===
BOT_TOKEN   = "YOUR_BOT_TOKEN"
CHAT_ID     = "YOUR_CHAT_ID"
DATA_DIR    = "/var/www/stats/data"
LOG_SERVICE = "telemt"
CHECK_INTERVAL  = 300   # проверка каждые 5 минут
DIGEST_HOUR     = 9     # час отправки дайджеста (UTC)
CPU_ALERT       = 80    # % CPU для алерта
RAM_ALERT       = 85    # % RAM для алерта

# === ПУТИ ===
FILE_CURRENT    = f"{DATA_DIR}/current.json"
FILE_HISTORY    = f"{DATA_DIR}/history.json"
FILE_HOURS      = f"{DATA_DIR}/hours.json"

# ============================================================
# УТИЛИТЫ
# ============================================================

def load_json(path, default=None):
    """Загрузка JSON файла"""
    try:
        if os.path.exists(path):
            with open(path) as f:
                return json.load(f)
    except:
        pass
    return default or {}

def fmt_bytes(b):
    """Форматирование байт"""
    b = int(b)
    if b > 1024**3:
        return f"{b/1024**3:.1f} GB"
    elif b > 1024**2:
        return f"{b/1024**2:.1f} MB"
    elif b > 1024:
        return f"{b/1024:.1f} KB"
    return f"{b} B"

def fmt_uptime(secs):
    """Форматирование uptime"""
    secs = int(secs)
    d = secs // 86400
    h = (secs % 86400) // 3600
    m = (secs % 3600) // 60
    if d > 0:
        return f"{d}д {h}ч {m}м"
    elif h > 0:
        return f"{h}ч {m}м"
    return f"{m}м"

def bar_chart(data, max_val, width=10):
    """Текстовый мини-график"""
    if max_val == 0:
        return "░" * width
    filled = int(data / max_val * width)
    return "█" * filled + "░" * (width - filled)

# ============================================================
# TELEGRAM API
# ============================================================

def send_message(text, parse_mode="HTML"):
    """Отправка текстового сообщения"""
    try:
        url = f"https://api.telegram.org/bot{BOT_TOKEN}/sendMessage"
        requests.post(url, json={
            "chat_id": CHAT_ID,
            "text": text,
            "parse_mode": parse_mode
        }, timeout=10)
    except Exception as e:
        print(f"Ошибка отправки: {e}")

def send_document(filepath, caption=""):
    """Отправка файла в Telegram"""
    try:
        url = f"https://api.telegram.org/bot{BOT_TOKEN}/sendDocument"
        with open(filepath, "rb") as f:
            requests.post(url, data={
                "chat_id": CHAT_ID,
                "caption": caption
            }, files={"document": f}, timeout=30)
    except Exception as e:
        print(f"Ошибка отправки файла: {e}")

def get_updates(offset=0):
    """Получение обновлений от Telegram"""
    try:
        url = f"https://api.telegram.org/bot{BOT_TOKEN}/getUpdates"
        r = requests.get(url, params={
            "offset": offset,
            "timeout": 30
        }, timeout=35)
        return r.json().get("result", [])
    except:
        return []

# ============================================================
# КОМАНДЫ БОТА
# ============================================================

def cmd_start():
    """Команда /start"""
    send_message(
        "🤖 <b>Telemt Monitor Bot</b>\n\n"
        "Мониторинг MTProxy прокси <code>tg.yourdomain.com</code>\n\n"
        "📋 <b>Команды:</b>\n"
        "/stats — статистика прямо сейчас\n"
        "/logs — получить файл логов\n"
        "/backup — бэкап конфига telemt\n"
        "/digest — дайджест за сегодня\n\n"
        "📊 <b>Веб-статистика:</b>\n"
        "https://stats.gptru.pro:4443\n\n"
        "🎛️ <b>Панель управления:</b>\n"
        "https://panel.gptru.pro:8443\n\n"
        f"⏰ Автодайджест каждый день в {DIGEST_HOUR}:00 UTC"
    )

def cmd_stats():
    """Команда /stats — текущая статистика"""
    current = load_json(FILE_CURRENT)
    if not current:
        send_message("❌ Данные недоступны — коллектор не запущен?")
        return

    # Мини-график по часам
    hours = load_json(FILE_HOURS, {str(i): 0 for i in range(24)})
    max_h = max(int(v) for v in hours.values()) or 1
    now_h = datetime.now(timezone.utc).hour

    chart = ""
    for i in range(24):
        val = int(hours.get(str(i), 0))
        marker = "◆" if i == now_h else " "
        chart += f"{i:02d}{marker}{bar_chart(val, max_h, 8)} {val}\n"

    status = "🟢 Онлайн" if current.get("online") else "🔴 Офлайн"

    msg = (
        f"📊 <b>Статистика прокси</b>\n"
        f"<code>{current.get('datetime','')}</code>\n\n"
        f"<b>Статус:</b> {status}\n"
        f"<b>Версия:</b> {current.get('version','')}\n"
        f"<b>Uptime:</b> {fmt_uptime(current.get('uptime_seconds',0))}\n\n"
        f"👥 <b>Подключений:</b> {current.get('total_connections',0)}\n"
        f"🌍 <b>Активных IP:</b> {current.get('active_ips',0)}\n"
        f"📦 <b>Трафик:</b> {fmt_bytes(current.get('traffic_bytes',0))}\n\n"
        f"💻 <b>CPU:</b> {current.get('cpu',0)}% {bar_chart(current.get('cpu',0), 100, 8)}\n"
        f"🧠 <b>RAM:</b> {current.get('ram',0)}% {bar_chart(current.get('ram',0), 100, 8)}\n"
        f"📁 <b>Логи:</b> {current.get('logs',{}).get('size','N/A')}\n\n"
        f"📈 <b>Активность по часам (UTC):</b>\n<code>{chart}</code>"
    )
    send_message(msg)

def cmd_logs():
    """Команда /logs — отправка файла логов"""
    send_message("⏳ Собираю логи...")
    try:
        log_path = "/tmp/telemt_logs.txt"
        result = subprocess.run(
            ["journalctl", "-u", LOG_SERVICE, "-n", "500", "--no-pager"],
            capture_output=True, text=True
        )
        with open(log_path, "w") as f:
            f.write(result.stdout)
        size = os.path.getsize(log_path)
        send_document(log_path, f"📋 Последние 500 строк логов telemt ({fmt_bytes(size)})")
        os.remove(log_path)
    except Exception as e:
        send_message(f"❌ Ошибка: {e}")

def cmd_backup():
    """Команда /backup — бэкап конфига"""
    try:
        config_path = "/etc/telemt/telemt.toml"
        ts = datetime.now().strftime("%Y%m%d_%H%M%S")
        backup_path = f"/tmp/telemt_backup_{ts}.toml"
        with open(config_path) as f:
            content = f.read()
        with open(backup_path, "w") as f:
            f.write(content)
        send_document(backup_path, f"💾 Бэкап конфига telemt от {ts}")
        os.remove(backup_path)
    except Exception as e:
        send_message(f"❌ Ошибка: {e}")

def cmd_report():
    """Команда /report — HTML отчёт"""
    send_message("⏳ Генерирую HTML отчёт...")
    try:
        current = load_json(FILE_CURRENT, {})
        history = load_json(FILE_HISTORY, {})
        hours   = load_json(FILE_HOURS, {str(i): 0 for i in range(24)})

        # Данные для графиков
        days  = sorted(history.keys())[-14:]  # последние 14 дней
        day_labels     = json.dumps(days)
        day_conn       = json.dumps([history[d]["connections"] for d in days])
        day_traffic    = json.dumps([round(history[d]["traffic_bytes"]/1024/1024, 1) for d in days])
        day_ips        = json.dumps([history[d]["peak_ips"] for d in days])
        hour_labels    = json.dumps([f"{i:02d}:00" for i in range(24)])
        hour_data      = json.dumps([int(hours.get(str(i), 0)) for i in range(24)])

        ts = datetime.now().strftime("%Y-%m-%d %H:%M UTC")

        html = f"""<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Telemt Stats — {ts}</title>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
  body {{ font-family: -apple-system, sans-serif; background: #0f1117; color: #e0e0e0; margin: 0; padding: 20px; }}
  h1 {{ color: #4ade80; font-size: 1.5em; }}
  h2 {{ color: #60a5fa; font-size: 1.1em; margin-top: 30px; }}
  .cards {{ display: flex; flex-wrap: wrap; gap: 12px; margin: 20px 0; }}
  .card {{ background: #1e2130; border-radius: 10px; padding: 16px 20px; min-width: 140px; flex: 1; }}
  .card .val {{ font-size: 1.8em; font-weight: bold; color: #4ade80; }}
  .card .lbl {{ font-size: 0.8em; color: #888; margin-top: 4px; }}
  .chart-box {{ background: #1e2130; border-radius: 10px; padding: 16px; margin: 12px 0; }}
  .footer {{ color: #555; font-size: 0.8em; margin-top: 30px; }}
</style>
</head>
<body>
<h1>📊 Telemt MTProxy — Статистика</h1>
<p style="color:#888">Сгенерировано: {ts} | Сервер: tg.yourdomain.com | v{current.get('version','')}</p>

<div class="cards">
  <div class="card"><div class="val">{current.get('total_connections',0)}</div><div class="lbl">Подключений сейчас</div></div>
  <div class="card"><div class="val">{current.get('active_ips',0)}</div><div class="lbl">Активных IP</div></div>
  <div class="card"><div class="val">{fmt_bytes(current.get('traffic_bytes',0))}</div><div class="lbl">Трафик всего</div></div>
  <div class="card"><div class="val">{current.get('cpu',0)}%</div><div class="lbl">CPU</div></div>
  <div class="card"><div class="val">{current.get('ram',0)}%</div><div class="lbl">RAM</div></div>
  <div class="card"><div class="val">{fmt_uptime(current.get('uptime_seconds',0))}</div><div class="lbl">Uptime</div></div>
  <div class="card"><div class="val">{current.get('logs',{}).get('size','N/A')}</div><div class="lbl">Размер логов</div></div>
</div>

<h2>📈 Подключения по дням</h2>
<div class="chart-box"><canvas id="connChart"></canvas></div>

<h2>📦 Трафик по дням (MB)</h2>
<div class="chart-box"><canvas id="trafficChart"></canvas></div>

<h2>👥 Пик активных IP по дням</h2>
<div class="chart-box"><canvas id="ipsChart"></canvas></div>

<h2>⏰ Активность по часам (UTC)</h2>
<div class="chart-box"><canvas id="hoursChart"></canvas></div>

<div class="footer">tg.yourdomain.com | @u_pre | Telemt v{current.get('version','')}</div>

<script>
const cfg = (id, label, labels, data, color) => {{
  new Chart(document.getElementById(id), {{
    type: 'bar',
    data: {{ labels, datasets: [{{ label, data, backgroundColor: color, borderRadius: 4 }}] }},
    options: {{ plugins: {{ legend: {{ display: false }} }}, scales: {{
      x: {{ ticks: {{ color: '#888' }}, grid: {{ color: '#2a2a3a' }} }},
      y: {{ ticks: {{ color: '#888' }}, grid: {{ color: '#2a2a3a' }} }}
    }} }}
  }});
}};
cfg('connChart',    'Подключения',  {day_labels},  {day_conn},    '#4ade80');
cfg('trafficChart', 'Трафик MB',    {day_labels},  {day_traffic}, '#60a5fa');
cfg('ipsChart',     'Активных IP',  {day_labels},  {day_ips},     '#f59e0b');
cfg('hoursChart',   'Активных IP',  {hour_labels}, {hour_data},   '#a78bfa');
</script>
</body>
</html>"""

        report_path = f"/tmp/telemt_report_{datetime.now().strftime('%Y%m%d_%H%M%S')}.html"
        with open(report_path, "w") as f:
            f.write(html)
        send_document(report_path, f"📊 HTML отчёт Telemt — {ts}")
        os.remove(report_path)
    except Exception as e:
        send_message(f"❌ Ошибка генерации отчёта: {e}")

def cmd_digest():
    """Ежедневный дайджест"""
    current = load_json(FILE_CURRENT, {})
    history = load_json(FILE_HISTORY, {})

    today = datetime.now(timezone.utc).strftime("%Y-%m-%d")
    today_data = history.get(today, {})

    msg = (
        f"🌅 <b>Дайджест за {today}</b>\n\n"
        f"👥 Пик подключений: {today_data.get('connections', 0)}\n"
        f"🌍 Пик активных IP: {today_data.get('peak_ips', 0)}\n"
        f"📦 Трафик: {fmt_bytes(today_data.get('traffic_bytes', 0))}\n\n"
        f"💻 CPU сейчас: {current.get('cpu', 0)}%\n"
        f"🧠 RAM сейчас: {current.get('ram', 0)}%\n"
        f"📁 Логи: {current.get('logs', {}).get('size', 'N/A')}\n"
        f"⏱ Uptime: {fmt_uptime(current.get('uptime_seconds', 0))}\n\n"
        f"🔗 Панель: https://panel.gptru.pro:8443"
    )
    send_message(msg)

# ============================================================
# МОНИТОРИНГ
# ============================================================

def monitor_loop():
    """Фоновый мониторинг — алерты и дайджест"""
    proxy_was_down = False
    last_digest_day = None

    while True:
        try:
            current = load_json(FILE_CURRENT, {})
            now = datetime.now(timezone.utc)

            # Проверка что прокси живой
            if current:
                if not current.get("online"):
                    if not proxy_was_down:
                        send_message("🔴 <b>АЛЕРТ!</b> Прокси не отвечает!")
                        proxy_was_down = True
                else:
                    if proxy_was_down:
                        send_message("🟢 <b>Прокси восстановлен!</b>")
                        proxy_was_down = False

                    # Алерт CPU
                    if current.get("cpu", 0) > CPU_ALERT:
                        send_message(f"⚠️ <b>АЛЕРТ!</b> CPU: {current['cpu']}%")

                    # Алерт RAM
                    if current.get("ram", 0) > RAM_ALERT:
                        send_message(f"⚠️ <b>АЛЕРТ!</b> RAM: {current['ram']}%")

            # Утренний дайджест
            if now.hour == DIGEST_HOUR and last_digest_day != now.date():
                cmd_digest()
                last_digest_day = now.date()

        except Exception as e:
            print(f"Ошибка мониторинга: {e}")

        time.sleep(CHECK_INTERVAL)

# ============================================================
# ОСНОВНОЙ ЦИКЛ
# ============================================================

def main():
    print("=== Telemt Bot запущен ===")
    send_message("🚀 <b>Бот мониторинга запущен!</b>\nНапиши /start для списка команд.")

    # Запуск мониторинга в фоне
    t = threading.Thread(target=monitor_loop, daemon=True)
    t.start()

    offset = 0
    while True:
        updates = get_updates(offset)
        for upd in updates:
            offset = upd["update_id"] + 1
            msg = upd.get("message", {})
            text = msg.get("text", "")
            chat = str(msg.get("chat", {}).get("id", ""))

            # Принимаем только от своего chat_id
            if chat != CHAT_ID:
                continue

            if text.startswith("/start"):
                cmd_start()
            elif text.startswith("/stats"):
                cmd_stats()
            elif text.startswith("/logs"):
                cmd_logs()
            elif text.startswith("/backup"):
                cmd_backup()
            elif text.startswith("/report"):
                cmd_report()
            elif text.startswith("/digest"):
                cmd_digest()

if __name__ == "__main__":
    main()
