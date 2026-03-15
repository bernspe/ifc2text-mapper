#!/usr/bin/env python3
"""
icf_analytics.py
Auswertung der ICF Code Mapper Datenbank (call_stats + feedback).
Liest Zugangsdaten aus .env.server (oder .env.local je nach APP_ENV).

Abhängigkeiten:
    pip install mysql-connector-python python-dotenv tabulate
"""

import os
import sys
from pathlib import Path
from datetime import datetime
import mysql.connector
from dotenv import dotenv_values
from tabulate import tabulate


# ── .env.server laden ─────────────────────────────────────────────────────────

def load_env() -> dict:

    env_path = Path(__file__).parent.parent / ".env.server"

    if not env_path.exists():
        print(f"Env-Datei nicht gefunden: {env_path}")
        sys.exit(1)

    print(f"Lade Konfiguration aus: {env_path.name}")
    return dotenv_values(env_path)


# ── Datenbankverbindung ───────────────────────────────────────────────────────

def get_connection(env: dict):
    return mysql.connector.connect(
        host     = env.get("DB_HOST",     "localhost"),
        port     = int(env.get("DB_PORT", "3306")),
        database = env.get("DB_NAME",     ""),
        user     = env.get("DB_USER",     ""),
        password = env.get("DB_PASSWORD", ""),
        charset  = "utf8mb4",
    )


# ── Auswertungsfunktionen ─────────────────────────────────────────────────────

def report_overview(cursor):
    """Gesamtübersicht: Aufrufe, Erfolgsrate, Durchschnittswerte"""
    print("\n" + "═" * 60)
    print("  ÜBERSICHT")
    print("═" * 60)

    cursor.execute("""
        SELECT
            COUNT(*)                          AS gesamt,
            SUM(success)                      AS erfolgreich,
            COUNT(*) - SUM(success)           AS fehler,
            ROUND(AVG(success) * 100, 1)      AS erfolgsrate_pct,
            ROUND(AVG(duration_ms))           AS avg_dauer_ms,
            ROUND(AVG(CASE WHEN success = 1
                THEN match_count END), 1)     AS avg_matches,
            MIN(ts)                           AS erster_aufruf,
            MAX(ts)                           AS letzter_aufruf
        FROM call_stats
    """)
    row = cursor.fetchone()
    if not row or row["gesamt"] == 0:
        print("  Keine Daten vorhanden.")
        return

    print(f"  Gesamtaufrufe:    {row['gesamt']}")
    print(f"  Erfolgreich:      {row['erfolgreich']}  ({row['erfolgsrate_pct']}%)")
    print(f"  Fehler:           {row['fehler']}")
    print(f"  Ø Antwortzeit:    {row['avg_dauer_ms']} ms")
    print(f"  Ø Matches/Aufruf: {row['avg_matches']}")
    print(f"  Erster Aufruf:    {row['erster_aufruf']}")
    print(f"  Letzter Aufruf:   {row['letzter_aufruf']}")


def report_daily(cursor):
    """Tägliche Aufrufzahlen der letzten 30 Tage"""
    print("\n" + "═" * 60)
    print("  TÄGLICHE AUFRUFE (letzte 30 Tage)")
    print("═" * 60)

    cursor.execute("""
        SELECT
            DATE(ts)                        AS tag,
            COUNT(*)                        AS aufrufe,
            SUM(success)                    AS ok,
            COUNT(*) - SUM(success)         AS fehler,
            ROUND(AVG(duration_ms))         AS avg_ms,
            ROUND(AVG(CASE WHEN success = 1
                THEN match_count END), 1)   AS avg_matches
        FROM call_stats
        WHERE ts >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(ts)
        ORDER BY tag DESC
    """)
    rows = cursor.fetchall()

    if not rows:
        print("  Keine Daten vorhanden.")
        return

    print(tabulate(
        [[r["tag"], r["aufrufe"], r["ok"], r["fehler"],
          f"{r['avg_ms']} ms", r["avg_matches"]] for r in rows],
        headers=["Datum", "Gesamt", "OK", "Fehler", "Ø Zeit", "Ø Matches"],
        tablefmt="simple"
    ))


def report_errors(cursor):
    """Häufigste Fehlermeldungen"""
    print("\n" + "═" * 60)
    print("  HÄUFIGSTE FEHLER")
    print("═" * 60)

    cursor.execute("""
        SELECT
            error_msg,
            COUNT(*) AS n,
            MAX(ts)  AS zuletzt
        FROM call_stats
        WHERE success = 0
          AND error_msg IS NOT NULL
        GROUP BY error_msg
        ORDER BY n DESC
        LIMIT 10
    """)
    rows = cursor.fetchall()

    if not rows:
        print("  Keine Fehler vorhanden.")
        return

    print(tabulate(
        [[r["n"], r["zuletzt"], r["error_msg"][:80]] for r in rows],
        headers=["Anzahl", "Zuletzt", "Fehlermeldung"],
        tablefmt="simple"
    ))


def report_feedback_overview(cursor):
    """Feedback-Gesamtübersicht"""
    print("\n" + "═" * 60)
    print("  FEEDBACK ÜBERSICHT")
    print("═" * 60)

    cursor.execute("""
        SELECT
            COUNT(*)                        AS gesamt,
            SUM(correct)                    AS richtig,
            COUNT(*) - SUM(correct)         AS falsch,
            ROUND(AVG(correct) * 100, 1)    AS trefferquote_pct
        FROM feedback
    """)
    row = cursor.fetchone()
    if not row or row["gesamt"] == 0:
        print("  Keine Feedback-Daten vorhanden.")
        return

    print(f"  Bewertungen gesamt:  {row['gesamt']}")
    print(f"  Richtige Treffer:    {row['richtig']}  ({row['trefferquote_pct']}%)")
    print(f"  Falsche Treffer:     {row['falsch']}")


def report_feedback_by_code(cursor):
    """Trefferquote pro ICF-Code"""
    print("\n" + "═" * 60)
    print("  TREFFERQUOTE PRO ICF-CODE")
    print("═" * 60)

    cursor.execute("""
        SELECT
            code,
            COUNT(*)                        AS bewertungen,
            SUM(correct)                    AS richtig,
            ROUND(AVG(correct) * 100, 1)    AS quote_pct
        FROM feedback
        GROUP BY code
        HAVING bewertungen >= 2
        ORDER BY quote_pct ASC, bewertungen DESC
    """)
    rows = cursor.fetchall()

    if not rows:
        print("  Nicht genug Daten (mind. 2 Bewertungen pro Code).")
        return

    print(tabulate(
        [[r["code"], r["bewertungen"], r["richtig"], f"{r['quote_pct']}%"] for r in rows],
        headers=["Code", "Bewertungen", "Richtig", "Trefferquote"],
        tablefmt="simple"
    ))


def report_feedback_worst(cursor):
    """Codes mit schlechtester Trefferquote – Kandidaten für Prompt-Verbesserung"""
    print("\n" + "═" * 60)
    print("  SCHLECHTESTE CODES (Verbesserungspotential)")
    print("═" * 60)

    cursor.execute("""
        SELECT
            f.code,
            COUNT(*)                        AS bewertungen,
            ROUND(AVG(f.correct) * 100, 1)  AS quote_pct,
            GROUP_CONCAT(DISTINCT f.textstelle
                ORDER BY f.textstelle SEPARATOR ' | ') AS textstellen
        FROM feedback f
        GROUP BY f.code
        HAVING bewertungen >= 3 AND quote_pct < 50
        ORDER BY quote_pct ASC
        LIMIT 10
    """)
    rows = cursor.fetchall()

    if not rows:
        print("  Keine Codes mit Quote < 50% (mind. 3 Bewertungen).")
        return

    for r in rows:
        print(f"\n  {r['code']}  –  {r['quote_pct']}% Trefferquote  ({r['bewertungen']} Bewertungen)")
        print(f"  Textstellen: {r['textstellen'][:120]}")


def report_feedback_sentences(cursor):
    """Sätze mit gemischtem Feedback – zeigt wo die KI inkonsistent ist"""
    print("\n" + "═" * 60)
    print("  SÄTZE MIT GEMISCHTEM FEEDBACK")
    print("═" * 60)

    cursor.execute("""
        SELECT
            `sentence-uuid`,
            sentence,
            COUNT(*)                        AS codes,
            SUM(correct)                    AS richtig,
            COUNT(*) - SUM(correct)         AS falsch
        FROM feedback
        GROUP BY `sentence-uuid`, sentence
        HAVING richtig > 0 AND falsch > 0
        ORDER BY falsch DESC
        LIMIT 10
    """)
    rows = cursor.fetchall()

    if not rows:
        print("  Keine Sätze mit gemischtem Feedback.")
        return

    for r in rows:
        print(f"\n  ✓ {r['richtig']}  ✗ {r['falsch']}  –  {r['sentence']}")


# ── Hauptprogramm ─────────────────────────────────────────────────────────────

def main():
    env  = load_env()
    conn = get_connection(env)
    cur  = conn.cursor(dictionary=True)

    print(f"\n{'═' * 60}")
    print(f"  ICF CODE MAPPER – Datenauswertung")
    print(f"  {datetime.now().strftime('%d.%m.%Y %H:%M:%S')}")
    print(f"{'═' * 60}")

    try:
        report_overview(cur)
        report_daily(cur)
        report_errors(cur)
        report_feedback_overview(cur)
        report_feedback_by_code(cur)
        report_feedback_worst(cur)
        report_feedback_sentences(cur)
    finally:
        cur.close()
        conn.close()

    print("\n")


if __name__ == "__main__":
    main()
