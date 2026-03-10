<?php
// api/stats.php
//
// POST /api/stats.php
// Wird von der Vue-Komponente nach jedem analyze()-Aufruf getriggert.
// Speichert Aufruf-Metadaten (success/error, match_count, duration) in MySQL.

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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError(405, 'Method not allowed');
}

// ── Request lesen & validieren ────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
if (strlen($rawInput) > 10_000) {
    jsonError(413, 'Request zu groß');
}

$body = json_decode($rawInput, true);

$sentenceUuid = trim($body['sentence_uuid'] ?? '');
$sentence     = trim($body['sentence']      ?? '');
$success      = $body['success']            ?? null;
$errorMsg     = trim($body['error_msg']     ?? '');
$matchCount   = (int)($body['match_count']  ?? 0);
$durationMs   = (int)($body['duration_ms']  ?? 0);

// UUID validieren
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $sentenceUuid)) {
    jsonError(400, 'Ungültige sentence_uuid');
}
if (strlen($sentence) < 1 || strlen($sentence) > 2000) {
    jsonError(400, 'Ungültiger sentence-Wert');
}
if (!is_bool($success)) {
    jsonError(400, 'success muss ein Boolean sein');
}

// Werte begrenzen
$matchCount = max(0, min(255, $matchCount));
$durationMs = max(0, min(65535, $durationMs));
$errorMsg   = $success ? null : substr($errorMsg, 0, 500);

// ── INSERT ────────────────────────────────────────────────────────────────────
$pdo  = getDb();
$stmt = $pdo->prepare('
    INSERT INTO call_stats
        (`sentence-uuid`, sentence, success, error_msg, match_count, duration_ms)
    VALUES
        (:uuid, :sentence, :success, :error_msg, :match_count, :duration_ms)
');

$stmt->execute([
    ':uuid'        => $sentenceUuid,
    ':sentence'    => $sentence,
    ':success'     => (int)$success,
    ':error_msg'   => $errorMsg,
    ':match_count' => $matchCount,
    ':duration_ms' => $durationMs,
]);

ob_end_clean();
echo json_encode(['ok' => true]);


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