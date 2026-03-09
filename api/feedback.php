<?php
// api/feedback.php
//
// POST /api/feedback.php
// Speichert User-Feedback (Daumen hoch/runter) zu ICF-Zuordnungen in MySQL.

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();
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

// ── Request lesen ─────────────────────────────────────────────────────────────
$rawInput = file_get_contents('php://input');
if (strlen($rawInput) > 10_000) {
    jsonError(413, 'Request zu groß');
}

$body = json_decode($rawInput, true);

// ── Validierung ───────────────────────────────────────────────────────────────
$sentenceUuid = trim($body['sentence_uuid'] ?? '');
$sentence     = trim($body['sentence']      ?? '');
$code         = trim($body['code']          ?? '');
$textstelle   = trim($body['textstelle']    ?? '');
$correct      = $body['correct']            ?? null;

// UUID-Format prüfen (einfacher Regex für UUIDv4)
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $sentenceUuid)) {
    jsonError(400, 'Ungültige sentence_uuid');
}
if (strlen($sentence) < 3 || strlen($sentence) > 2000) {
    jsonError(400, 'Ungültiger sentence-Wert');
}
if (empty($code) || strlen($code) > 20) {
    jsonError(400, 'Ungültiger code-Wert');
}
if (empty($textstelle) || strlen($textstelle) > 500) {
    jsonError(400, 'Ungültiger textstelle-Wert');
}
if (!is_bool($correct)) {
    jsonError(400, 'correct muss ein Boolean sein');
}

// ── MySQL-Verbindung ──────────────────────────────────────────────────────────
$pdo = getDb();

// temporär zur Diagnose, direkt vor dem $stmt->execute():
error_log("INSERT: uuid=$sentenceUuid code=$code textstelle=$textstelle correct=" . (int)$correct);

// ── INSERT ────────────────────────────────────────────────────────────────────
// INSERT IGNORE überspringt den Eintrag wenn (sentence_uuid, code, textstelle)
// bereits existiert – verhindert Duplikate bei Doppelklick ohne Fehler zu werfen.
// Für Update-Semantik (Meinung ändern): stattdessen INSERT ... ON DUPLICATE KEY UPDATE
$stmt = $pdo->prepare('
    INSERT IGNORE INTO feedback
        (`sentence-uuid`, sentence, code, textstelle, correct)
    VALUES
        (:uuid, :sentence, :code, :textstelle, :correct)
    ON DUPLICATE KEY UPDATE
         correct = VALUES(correct),
         ts      = CURRENT_TIMESTAMP
');

$stmt->execute([
    ':uuid'       => $sentenceUuid,
    ':sentence'   => $sentence,
    ':code'       => $code,
    ':textstelle' => $textstelle,
    ':correct'    => (int)$correct,
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

    $host   = $_ENV['DB_HOST']     ?? 'localhost';
    $port   = $_ENV['DB_PORT']     ?? '3306';
    $dbname = $_ENV['DB_NAME']     ?? '';
    $user   = $_ENV['DB_USER']     ?? '';
    $pass   = $_ENV['DB_PASSWORD'] ?? '';

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
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
        $value = trim($value, '"\'');
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
}
