<?php
/**
 * DocuMind — config.php
 * Global bootstrap: session, environment, PDO connection,
 * auto-table initialisation, and Multi-Key Gemini API configuration.
 */

declare(strict_types=1);

// ── Session ──────────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Environment helpers ───────────────────────────────────────────────────────
function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    return ($value !== false && $value !== '') ? $value : $default;
}

// ── Database credentials ──────────────────────────────────────────────────────
$db_host = env('DB_HOST', '127.0.0.1');
$db_port = env('DB_PORT', '3306');
$db_name = env('DB_NAME', 'pdf_ai_db');
$db_user = env('DB_USER', 'root');
$db_pass = env('DB_PASS', '');

// ── Gemini API Keys (Supports Single Key or 10+ Keys Array) ───────────────────
$apiKeys = [];

// 1. Check environment variables (comma-separated or single key)
$envKey = env('GEMINI_API_KEYS') ?? env('GEMINI_API_KEY')
       ?? ($_ENV['GEMINI_API_KEYS'] ?? $_ENV['GEMINI_API_KEY'] ?? null)
       ?? ($_SERVER['GEMINI_API_KEYS'] ?? $_SERVER['GEMINI_API_KEY'] ?? null);

if (!empty($envKey)) {
    if (is_string($envKey)) {
        $apiKeys = array_merge($apiKeys, explode(',', $envKey));
    } elseif (is_array($envKey)) {
        $apiKeys = array_merge($apiKeys, $envKey);
    }
}

// 2. Load from config.local.php
if (is_file(__DIR__ . '/config.local.php')) {
    $local = require __DIR__ . '/config.local.php';
    if (is_array($local)) {
        if (!empty($local['GEMINI_API_KEYS']) && is_array($local['GEMINI_API_KEYS'])) {
            $apiKeys = array_merge($apiKeys, $local['GEMINI_API_KEYS']);
        }
        if (!empty($local['GEMINI_API_KEY']) && is_string($local['GEMINI_API_KEY'])) {
            $apiKeys = array_merge($apiKeys, explode(',', $local['GEMINI_API_KEY']));
        }
    }
}

// Filter out empty keys and unused placeholders
$apiKeys = array_values(array_unique(array_filter(array_map('trim', $apiKeys), function (string $k): bool {
    return $k !== '' 
        && $k !== 'HERE IS MY API KEY' 
        && !str_starts_with($k, 'YOUR_GEMINI_API_KEY_');
})));

if (!defined('GEMINI_API_KEYS')) {
    define('GEMINI_API_KEYS', $apiKeys);
}
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', $apiKeys[0] ?? '');
}

// ── Valid Text-Out Model IDs (Ordered by High RPD Quota -> Flagship Flash) ────
// 3.5-flash-lite & 3.1-flash-lite give 500 RPD each per key!
if (!defined('GEMINI_MODELS')) {
    $modelList = (string) env(
        'GEMINI_MODELS',
        'gemini-3.5-flash-lite,gemini-3.1-flash-lite,gemini-3.8-flash,gemini-3.6-flash,gemini-3-flash,gemma-4-31b-it,gemma-4-26b-it'
    );
    define(
        'GEMINI_MODELS',
        array_values(array_filter(array_map('trim', explode(',', $modelList))))
    );
}

// ── TiDB / Remote SSL detection ───────────────────────────────────────────────
$is_remote = !in_array($db_host, ['127.0.0.1', 'localhost'], true);

$ssl_ca_path = '/etc/ssl/certs/ca-certificates.crt';

$pdo_options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

if ($is_remote && file_exists($ssl_ca_path)) {
    $pdo_options[PDO::MYSQL_ATTR_SSL_CA]                 = $ssl_ca_path;
    $pdo_options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
}

// ── PDO Connection ────────────────────────────────────────────────────────────
try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $db_host,
        $db_port,
        $db_name
    );

    $pdo = new PDO($dsn, $db_user, $db_pass, $pdo_options);

} catch (PDOException $e) {
    http_response_code(503);
    error_log('[DocuMind] DB connection failed: ' . $e->getMessage());
    die(<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8">
<title>DocuMind — DB Error</title>
<style>
  body{font-family:system-ui,sans-serif;display:flex;align-items:center;
       justify-content:center;min-height:100vh;margin:0;background:#f4f4f5;color:#18181b;}
  .box{background:#fff;border:1px solid #e4e4e7;border-radius:12px;
       padding:2.5rem 3rem;max-width:420px;text-align:center;box-shadow:0 4px 24px rgba(0,0,0,.07);}
  h2{margin:0 0 .75rem;font-size:1.25rem;}
  p{margin:0;color:#71717a;font-size:.9rem;}
</style></head><body>
<div class="box">
  <h2>⚠️ Database Unavailable</h2>
  <p>Could not connect to the database. Check your environment variables and try again.</p>
</div></body></html>
HTML);
}

// ── Auto-initialise tables ────────────────────────────────────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `users` (
      `id`            INT            NOT NULL AUTO_INCREMENT,
      `full_name`     VARCHAR(100)   NOT NULL,
      `email`         VARCHAR(150)   NOT NULL UNIQUE,
      `password_hash` VARCHAR(255)   NOT NULL,
      `role`          VARCHAR(50)    NOT NULL DEFAULT 'Student',
      `created_at`    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX `idx_users_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `pdf_history` (
      `id`             INT          NOT NULL AUTO_INCREMENT,
      `user_id`        INT          NOT NULL,
      `title`          VARCHAR(255) NOT NULL,
      `pdf_name`       VARCHAR(255) NOT NULL,
      `category`       VARCHAR(100) NOT NULL DEFAULT 'Study',
      `extracted_text` LONGTEXT     NOT NULL,
      `summary`        LONGTEXT     NOT NULL,
      `imp_questions`  LONGTEXT     DEFAULT NULL,
      `user_notes`     TEXT         DEFAULT NULL,
      `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      INDEX `idx_ph_user_id`    (`user_id`),
      INDEX `idx_ph_category`   (`category`),
      INDEX `idx_ph_created_at` (`created_at`),
      CONSTRAINT `fk_ph_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ── Utility constants ───────────────────────────────────────────────────────
define('APP_NAME',    'DocuMind');
define('APP_VERSION', '1.0.0');
define('UPLOAD_DIR',  __DIR__ . '/uploads/');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}