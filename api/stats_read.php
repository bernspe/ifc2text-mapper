<?php
// api/stats_read.php
//
// GET /api/stats_read.php
// Gibt aggregierte Analysedaten aus call_stats als JSON zurück.

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();

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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError(405, 'Method not allowed');
}

// ── Datenbank ─────────────────────────────────────────────────────────────────
$pdo = getDb();

// ── Gesamtübersicht ───────────────────────────────────────────────────────────
$overview = $pdo->query("
    SELECT
        COUNT(*)                                AS total_requests,
        COUNT(DISTINCT `sentence-uuid`)         AS unique_sessions,
        SUM(success)                            AS total_success,
        COUNT(*) - SUM(success)                 AS total_errors,
        ROUND(AVG(success) * 100, 1)            AS success_rate_pct,
        ROUND(AVG(duration_ms))                 AS avg_duration_ms,
        ROUND(MIN(duration_ms))                 AS min_duration_ms,
        ROUND(MAX(duration_ms))                 AS max_duration_ms,
        ROUND(AVG(CASE WHEN success = 1
            THEN match_count END), 1)           AS avg_match_count,
        MIN(ts)                                 AS first_request,
        MAX(ts)                                 AS last_request
    FROM call_stats
")->fetch(PDO::FETCH_ASSOC);

// ── Letzte 30 Tage (täglich) ──────────────────────────────────────────────────
$daily = $pdo->query("
    SELECT
        DATE(ts)                                AS date,
        COUNT(*)                                AS requests,
        COUNT(DISTINCT `sentence-uuid`)         AS unique_sessions,
        SUM(success)                            AS success,
        COUNT(*) - SUM(success)                 AS errors,
        ROUND(AVG(duration_ms))                 AS avg_duration_ms,
        ROUND(AVG(CASE WHEN success = 1
            THEN match_count END), 1)           AS avg_match_count
    FROM call_stats
    WHERE ts >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(ts)
    ORDER BY date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Letzte 7 Tage (stündlich) – für Tages-Heatmap ────────────────────────────
$hourly = $pdo->query("
    SELECT
        HOUR(ts)        AS hour,
        COUNT(*)        AS requests
    FROM call_stats
    WHERE ts >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY HOUR(ts)
    ORDER BY hour ASC
")->fetchAll(PDO::FETCH_ASSOC);

// ── Häufigste Fehler ──────────────────────────────────────────────────────────
$errors = $pdo->query("
    SELECT
        error_msg,
        COUNT(*)        AS count,
        MAX(ts)         AS last_seen
    FROM call_stats
    WHERE success = 0
      AND error_msg IS NOT NULL
    GROUP BY error_msg
    ORDER BY count DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

// ── Antwort zusammenbauen ─────────────────────────────────────────────────────
$response = [
    'overview' => [
        'total_requests'   => (int)$overview['total_requests'],
        'unique_sessions'  => (int)$overview['unique_sessions'],
        'total_success'    => (int)$overview['total_success'],
        'total_errors'     => (int)$overview['total_errors'],
        'success_rate_pct' => (float)$overview['success_rate_pct'],
        'avg_duration_ms'  => (int)$overview['avg_duration_ms'],
        'min_duration_ms'  => (int)$overview['min_duration_ms'],
        'max_duration_ms'  => (int)$overview['max_duration_ms'],
        'avg_match_count'  => (float)$overview['avg_match_count'],
        'first_request'    => $overview['first_request'],
        'last_request'     => $overview['last_request'],
    ],
    'daily'    => array_map(fn($r) => [
        'date'             => $r['date'],
        'requests'         => (int)$r['requests'],
        'unique_sessions'  => (int)$r['unique_sessions'],
        'success'          => (int)$r['success'],
        'errors'           => (int)$r['errors'],
        'avg_duration_ms'  => (int)$r['avg_duration_ms'],
        'avg_match_count'  => (float)$r['avg_match_count'],
    ], $daily),
    'hourly'   => array_map(fn($r) => [
        'hour'     => (int)$r['hour'],
        'requests' => (int)$r['requests'],
    ], $hourly),
    'errors'   => array_map(fn($r) => [
        'error_msg' => $r['error_msg'],
        'count'     => (int)$r['count'],
        'last_seen' => $r['last_seen'],
    ], $errors),
];

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);


// ════════════════════════════════════════════════════════════════════════════
// Hilfsfunktionen
// ════════════════════════════════════════════════════════════════════════════

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $host = $_ENV['DB_HOST']     ?? 'localhost';
    $port = $_ENV['DB_PORT']     ?? '3306';
    $name = $_ENV['DB_NAME']     ?? '';
    $user = $_ENV['DB_USER']     ?? '';
    $pass = $_ENV['DB_PASSWORD'] ?? '';

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    return $pdo;
}

function jsonError(int $code, string $message): never
{
    ob_end_clean();
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
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
