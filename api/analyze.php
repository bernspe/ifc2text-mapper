<?php
// api/analyze.php
//
// POST /api/analyze.php
// Sicherheitskette: CORS → Method → Size → Turnstile → RateLimit → Validate → LLM

declare(strict_types=1);
ob_start();  // allen Output puffern

// Fehler niemals in den HTTP-Response schreiben
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Stattdessen alles ins PHP-Errorlog
ini_set('log_errors', '1');
error_reporting(E_ALL);  // alles loggen, aber nichts ausgeben

// ── .env laden (manuell – kein Composer nötig) ───────────────────────────────
$env  = getenv('APP_ENV') ?: 'local';
if ($env === 'local') {
    loadEnv(__DIR__ . '/../.env.server');
} else {
    loadEnv('/home/.env.server');
}

// ── CORS ──────────────────────────────────────────────────────────────────────
$allowedOrigin = $_ENV['ALLOWED_ORIGIN'] ?? 'http://localhost:5173';
header("Access-Control-Allow-Origin: $allowedOrigin");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Preflight-Request (Browser CORS-Check)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ── Nur POST erlaubt ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError(405, 'Method not allowed');
}

// ── Request-Größe prüfen (10 KB) ─────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
if (strlen($rawInput) > 10_000) {
    jsonError(413, 'Request zu groß');
}

// ── JSON parsen & validieren ──────────────────────────────────────────────────
$body           = json_decode($rawInput, true);
$sentence       = trim($body['sentence']       ?? '');
$turnstileToken = trim($body['turnstileToken'] ?? '');

if (strlen($sentence) < 3 || strlen($sentence) > 2000) {
    jsonError(400, 'Ungültige Eingabe: Satz muss 3–2000 Zeichen lang sein');
}
if (empty($turnstileToken)) {
    jsonError(400, 'Turnstile-Token fehlt');
}

// ── Rate Limiting ─────────────────────────────────────────────────────────────
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip = trim(explode(',', $ip)[0]); // Erstes Element bei mehreren Proxies
if (!checkRateLimit($ip, limitPerMinute: 10)) {
    jsonError(429, 'Zu viele Anfragen – bitte warte eine Minute');
}

// ── Turnstile verifizieren ────────────────────────────────────────────────────
$tsSecretKey = $_ENV['TURNSTILE_SECRET_KEY'] ?? '';
if (!verifyTurnstile($turnstileToken, $tsSecretKey)) {
    jsonError(403, 'Bot-Verifikation fehlgeschlagen');
}

// ── ICF-System-Prompt aufbauen (gecacht) ─────────────────────────────────────
$icfTxtPath   = $_ENV['ICF_TXT_PATH'] ?? '';
$systemPrompt = buildSystemPrompt($icfTxtPath);

// ── LLM aufrufen ─────────────────────────────────────────────────────────────
$matches = callLlm(
    sentence:     $sentence,
    systemPrompt: $systemPrompt,
    baseUrl:      rtrim($_ENV['LLM_BASE_URL'] ?? '', '/'),
    apiKey:       $_ENV['LLM_API_KEY']        ?? '',
    model:        $_ENV['LLM_MODEL']          ?? '',
);

ob_end_clean();

echo json_encode(['matches' => $matches], JSON_UNESCAPED_UNICODE);


// ════════════════════════════════════════════════════════════════════════════
// Hilfsfunktionen
// ════════════════════════════════════════════════════════════════════════════

function verifyTurnstile(string $token, string $secretKey): bool
{
    $response = @file_get_contents(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query(['secret' => $secretKey, 'response' => $token]),
            'timeout' => 5,
        ]])
    );
    if ($response === false) return false;
    $data = json_decode($response, true);
    return (bool)($data['success'] ?? false);
}

function checkRateLimit(string $ip, int $limitPerMinute): bool
{
    // Dateibasiertes Sliding-Window Rate Limiting
    // Für höhere Last durch APCu oder Redis ersetzen
    $cacheDir = sys_get_temp_dir() . '/icf_rl/';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0700, true);
    }

    $file = $cacheDir . md5($ip) . '.json';
    $now  = time();

    $data = ['timestamps' => []];
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true) ?: $data;
    }

    // Alle Einträge außerhalb des 60s-Fensters entfernen
    $data['timestamps'] = array_filter(
        $data['timestamps'],
        fn(int $ts) => $now - $ts < 60
    );
    $data['timestamps'][] = $now;

    // Atomic write
    file_put_contents($file, json_encode($data), LOCK_EX);

    return count($data['timestamps']) <= $limitPerMinute;
}

function buildSystemPrompt(string $txtPath): string
{
    // APCu: bleibt für die gesamte PHP-FPM-Worker-Lifetime gecacht
    $cacheKey = 'icf_prompt_v1';
    if (function_exists('apcu_fetch')) {
        $cached = apcu_fetch($cacheKey, $success);
        if ($success) return (string)$cached;
    }

    if (!file_exists($txtPath)) {
        throw new RuntimeException("ICF-TXT nicht gefunden: $txtPath");
    }

    $codeList = file_get_contents($txtPath);

    $prompt = <<<PROMPT
Du bist ein medizinischer Klassifikations-Assistent für das ICF-System (Internationale Klassifikation der Funktionsfähigkeit).

Deine Aufgabe:
1. Analysiere den Satz des Nutzers.
2. Identifiziere relevante Textstellen (Wörter oder Phrasen).
3. Weise jeder Textstelle den am besten passenden ICF-Code zu.

Antworte AUSSCHLIESSLICH mit einem JSON-Array. Kein Text davor oder danach:
[
  {
    "textstelle": "exaktes Wort/Phrase aus dem Eingabesatz",
    "code": "ICF-Code z.B. b230",
    "beschreibung": "kurze Begründung (1 Satz)"
  }
]

Regeln:
- "textstelle" muss exakt so im Eingabesatz vorkommen, einschließlich aller
  Wörter dazwischen, Groß- und Kleinschreibung muß beachtet werden. Wenn die relevante Phrase Füllwörter enthält
  (wie "nicht mehr"), müssen diese ebenfalls in "textstelle" enthalten sein.
- Versuche nicht, zusammengesetzte Verben zu trennen (z.B. "sich bewegen" → "bewegen"), wenn im Satz die Füllwörter dazwischen stehen ("sich ... bewegen") oder "aufwache" → "aufwache" statt "wache ... auf". Liefere immer die exakte Textstelle zurück.
- Prüfe vor der Antwort: ist "textstelle" mit indexOf() im Originalsatz findbar?
- Nur Codes aus der folgenden Liste verwenden.
- Mehrere Codes pro Textstelle → mehrere Einträge mit gleicher Textstelle.
- Kein Treffer → leeres Array [].

Verfügbare ICF-Codes:
$codeList
PROMPT;

    if (function_exists('apcu_store')) {
        apcu_store($cacheKey, $prompt, 3600);
    }

    return $prompt;
}

function callLlm(
    string $sentence,
    string $systemPrompt,
    string $baseUrl,
    string $apiKey,
    string $model,
): array {
    $payload = json_encode([
        'model'       => $model,
        'temperature' => 0.1,
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user',   'content' => "Analysiere diesen Satz: \"$sentence\""],
        ],
    ], JSON_UNESCAPED_UNICODE);

    $response = @file_get_contents(
        "$baseUrl/chat/completions",
        false,
        stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", [
                'Content-Type: application/json',
                "Authorization: Bearer $apiKey",
            ]),
            'content'       => $payload,
            'timeout'       => 300,
            'ignore_errors' => true, // HTTP-Fehler als String statt false
        ]])
    );

    if ($response === false) {
        jsonError(502, 'LLM nicht erreichbar');
    }

    $data = json_decode($response, true);

    // HTTP-Fehler des LLM weiterreichen
    // $http_response_header=http_get_last_response_headers();
    $httpCode = 200;
    if (isset($http_response_header)) {
        preg_match('/HTTP\/\S+\s+(\d+)/', $http_response_header[0], $m);
        $httpCode = (int)($m[1] ?? 200);
    }
    if ($httpCode >= 400) {
        jsonError(502, "LLM-Fehler $httpCode");
    }

    $raw = $data['choices'][0]['message']['content'] ?? '[]';
    return parseLlmResponse($raw);
}

function parseLlmResponse(string $raw): array
{
    // Markdown-Codeblöcke entfernen
    print_r($raw);
    $cleaned = preg_replace('/```(?:json)?/', '', $raw);
    print_r($cleaned);
    $start   = strpos($cleaned, '[');
    $end     = strrpos($cleaned, ']');
    if ($start === false || $end === false) return [];

    $parsed = json_decode(substr($cleaned, $start, $end - $start + 1), true);
    print_r($parsed);
    if (!is_array($parsed)) return [];

    return array_values(array_filter($parsed, fn($item) =>
        is_array($item)
        && isset($item['textstelle'], $item['code'], $item['beschreibung'])
        && is_string($item['textstelle'])
        && is_string($item['code'])
        && is_string($item['beschreibung'])
        && strlen($item['textstelle']) > 0
        && strlen($item['code']) > 0
    ));
}

function loadEnv(string $path): void
{
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2)) + ['', ''];
        // Anführungszeichen entfernen
        $value = trim($value, '"\'');
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}

function jsonError(int $code, string $message): never
{
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}
