# ICF Code Mapper – PHP + Vue 3

Dieses Tool erlaubt die Analyse von Freitexten unter Verwendung von Codes der [International Classification of Functioning, Disability and Health (ICF)](https://www.who.int/standards/classifications/international-classification-of-functioning-disability-and-health).
Die Analyse erfolgt durch ein LLM, die requests nutzen das OpenAI-Format.
Um die App zum laufen zu bringen, benötigst du also Zugang zu einer gehosteten API. Ausserdem benötigst du noch Zugangsdaten für das Cloudflare Turnstile-Widget.

Die Python-Version dieses Tools ist hier zu finden:
https://gitlab.hrz.tu-chemnitz.de/scads-ai-llm/scads-ai-llm-api-examples/-/tree/main/text2icf?ref_type=heads

## Setup

### 1. .env Dateien bearbeiten

.env.local - bleibt im Deployment-Verzeichnis für das Frontend
```
VITE_TURNSTILE_SITE_KEY=YOUR_TURNSTILE_KEY
VITE_IMAGE_SERVER = https://storage.googleapis.com/icfx_imageserver/icfx-image-server/
```

.env.server - Speicherort steht im Kopf der php-Dateien
```
# ── LLM ──────────────────────────────────────────────────────────────────────
LLM_API_KEY=
LLM_BASE_URL=
LLM_MODEL=

# Produktion
# ALLOWED_ORIGIN=YOUR_DEPLOYMENT_HOST
# ICF_TXT_PATH=PATH_TO_ICF_CODES3.TXT

# Entwicklung:
ALLOWED_ORIGIN=http://localhost:5173
ICF_TXT_PATH=...ABSOLUTE_PATH.../data/icf_codes3.txt

# ── Cloudflare Turnstile ──────────────────────────────────────────────────────
TURNSTILE_SECRET_KEY=YOUR_TURNSTILE_SECRET_KEY

# DB
DB_HOST=MY SQL HOST
DB_NAME=SQL_DB_NAME
DB_USER=SQL_USER
DB_PASSWORD=SQL_PASSWORD
```

### 2. Entwicklung starten

Terminal 1 – PHP-Server:
```bash
php -S localhost:8080
# oder: php -S localhost:8080 -t .
```

Terminal 2 – Vite Dev-Server:
```bash
npm run dev
# → http://localhost:5173
# Vite leitet /api/* automatisch an :8080 weiter (siehe vite.config.ts)
```

Für lokales Testen ohne echten Turnstile-Account:
```
# .env.local
VITE_TURNSTILE_SITE_KEY=1x00000000000000000000AA

# .env.server
TURNSTILE_SECRET_KEY=1x0000000000000000000000000000000AA
```

### 3. Produktion bauen

```bash
npm run build
# → dist/ enthält die statischen Dateien
```

Dann auf dem Server:
- `dist/` → Webroot (z.B. `/var/www/html/`)
- `api/` → neben dem Webroot (z.B. `/var/www/html/api/`)
- `data/icf_codes3.txt` → Pfad in `.env.server` eintragen
- `.env.server` → **außerhalb** des Webroots ablegen und Pfad in `analyze.php`  und `feedback.php` anpassen

## Cloudflare Turnstile

1. https://dash.cloudflare.com → Turnstile → Site hinzufügen
2. **Site Key** → `VITE_TURNSTILE_SITE_KEY` (Frontend `.env.local`)
3. **Secret Key** → `TURNSTILE_SECRET_KEY` (PHP `.env.server`)

## Sicherheitsschichten

```
Browser
  → Turnstile Widget löst Challenge (unsichtbar für echte User)
  → POST /api/analyze.php  { sentence, turnstileToken }
        │
PHP
  ├── CORS: nur erlaubte Origin
  ├── Method: nur POST
  ├── Size: max 10 KB
  ├── Rate Limit: 10 req/min/IP (Sliding Window, dateibasiert)
  ├── Turnstile: Token bei Cloudflare verifizieren
  ├── Validierung: Länge + Typ prüfen
  └── LLM-Call: Key aus .env.server (nie im Bundle)
```
