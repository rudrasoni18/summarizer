<?php
/**
 * DocuMind — api_handler.php
 * Backend AJAX Controller & Multi-Key / Multi-Model Gemini AI Integration
 */
declare(strict_types=1);

// Never leak PHP warnings into the JSON response
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

// ── Auth guard ────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorised. Please log in.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Release the session lock so long AI calls don't block other page loads
session_write_close();

// ── Load all configured Gemini API keys ───────────────────
$geminiApiKeys = defined('GEMINI_API_KEYS') && is_array(GEMINI_API_KEYS) ? GEMINI_API_KEYS : [];
if (empty($geminiApiKeys) && defined('GEMINI_API_KEY') && GEMINI_API_KEY !== '') {
    $geminiApiKeys = [(string)GEMINI_API_KEY];
}

// ── Route ─────────────────────────────────────────────────
$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? ''));

match ($action) {
    'summarize'          => actionSummarize($pdo, $userId, $geminiApiKeys),
    'generate_questions' => actionGenerateQuestions($pdo, $userId, $geminiApiKeys),
    'get_history'        => actionGetHistory($pdo, $userId),
    default              => jsonError('Unknown action: ' . $action),
};

/* ══════════════════════════════════════════════════════════
   ACTION: summarize
   ══════════════════════════════════════════════════════════ */
function actionSummarize(PDO $pdo, int $userId, array $apiKeys): void
{
    $title         = trim((string)($_POST['title']          ?? ''));
    $category      = trim((string)($_POST['category']       ?? 'Study'));
    $pdfName       = trim((string)($_POST['pdf_name']       ?? 'Direct Text Input'));
    $extractedText = trim((string)($_POST['extracted_text'] ?? ''));

    if ($extractedText === '') {
        jsonError('No text content provided.');
        return;
    }

    // Auto-fill title
    if ($title === '') {
        $title = $pdfName !== 'Direct Text Input'
            ? (string)preg_replace('/\.pdf$/i', '', $pdfName)
            : mb_substr((string)strtok($extractedText, "\n"), 0, 80);
        $title = $title !== '' ? $title : 'Untitled Document';
    }
    $title = mb_substr($title, 0, 255);

    // Truncate to 80 000 chars to avoid oversized payloads
    if (mb_strlen($extractedText) > 80000) {
        $extractedText = mb_substr($extractedText, 0, 80000);
    }

    if (empty($apiKeys)) {
        jsonError('Gemini API key is not configured. Add your keys in config.local.php.');
        return;
    }

    // ── Build prompt ──────────────────────────────────────
    $isStudy = stripos($category, 'study') !== false || stripos($category, 'research') !== false;
    $toneHint = $isStudy
        ? 'This is academic/study material. Use clear educational language with structured headings.'
        : 'This is general or business content. Use concise, professional language.';

    $prompt = <<<PROMPT
You are DocuMind, an expert AI document analyst. Produce a comprehensive, well-structured Markdown summary of the document below.

{$toneHint}

**Document Title:** {$title}
**Category:** {$category}

**Required Output Structure (use these exact Markdown headings):**

## Executive Overview
A 3–5 sentence high-level summary of what this document is about and its main purpose.

## Core Concepts / Key Takeaways
A bullet-point list of the most important ideas, facts, or insights (8–15 points).

## Detailed Section-wise Breakdown
A thorough breakdown of all major sections, topics, or chapters found in the document. Use sub-headings (###) for each distinct section. Include specifics, data, and named entities.

---

**Document Content:**
{$extractedText}
PROMPT;

    $apiError = '';
    $summary  = callGeminiApi($apiKeys, $prompt, 90, $apiError);

    if ($summary === null) {
        jsonError($apiError !== '' ? $apiError : 'AI request failed. Please try again.');
        return;
    }

    // ── Insert into DB ────────────────────────────────────
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO pdf_history (user_id, title, pdf_name, category, extracted_text, summary)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $title, mb_substr($pdfName, 0, 255), mb_substr($category, 0, 100), $extractedText, $summary]);
        $lastId = (int)$pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log('[DocuMind] DB insert error: ' . $e->getMessage());
        jsonError('Database error while saving summary.');
        return;
    }

    echo json_encode([
        'success'    => true,
        'history_id' => $lastId,
        'title'      => $title,
        'category'   => $category,
        'pdf_name'   => $pdfName,
        'summary'    => $summary,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

/* ══════════════════════════════════════════════════════════
   ACTION: generate_questions
   ══════════════════════════════════════════════════════════ */
function actionGenerateQuestions(PDO $pdo, int $userId, array $apiKeys): void
{
    $historyId = (int)($_POST['history_id'] ?? 0);

    if ($historyId <= 0) {
        jsonError('Invalid history ID.');
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, title, extracted_text FROM pdf_history WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$historyId, $userId]);
        $record = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[DocuMind] DB fetch error: ' . $e->getMessage());
        jsonError('Database error.');
        return;
    }

    if (!$record) {
        jsonError('Record not found or access denied.');
        return;
    }

    if (empty($apiKeys)) {
        jsonError('Gemini API key is not configured. Add your keys in config.local.php.');
        return;
    }

    $extractedText = mb_substr((string)$record['extracted_text'], 0, 80000);
    $title         = (string)$record['title'];

    $prompt = <<<PROMPT
You are DocuMind, an expert educator and examiner. Based on the document below, generate a high-value set of **Important Questions** in clean Markdown format.

**Document Title:** {$title}

**Required Output Structure (use these exact Markdown headings):**

## Section 1: High-Priority Short Answer / Viva Questions
15–20 concise questions that test direct knowledge recall. Each question on its own line, numbered.

## Section 2: Long-Form / Analytical / Exam Questions
8–12 essay-style or analytical questions that require critical thinking and detailed answers.

## Section 3: Key Objective / Conceptual Checkpoints
10–15 conceptual questions with a *brief answer hint* in parentheses after each question.

---

**Document Content:**
{$extractedText}
PROMPT;

    $apiError     = '';
    $impQuestions = callGeminiApi($apiKeys, $prompt, 90, $apiError);

    if ($impQuestions === null) {
        jsonError($apiError !== '' ? $apiError : 'AI request failed. Please try again.');
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'UPDATE pdf_history SET imp_questions = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$impQuestions, $historyId, $userId]);
    } catch (PDOException $e) {
        error_log('[DocuMind] DB update error: ' . $e->getMessage());
        jsonError('Database error while saving questions.');
        return;
    }

    echo json_encode([
        'success'       => true,
        'imp_questions' => $impQuestions,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

/* ══════════════════════════════════════════════════════════
   ACTION: get_history
   ══════════════════════════════════════════════════════════ */
function actionGetHistory(PDO $pdo, int $userId): void
{
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

    if ($id <= 0) {
        jsonError('Invalid ID.');
        return;
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT id, title, pdf_name, category, summary, imp_questions, user_notes, created_at
             FROM pdf_history WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$id, $userId]);
        $record = $stmt->fetch();
    } catch (PDOException $e) {
        error_log('[DocuMind] DB fetch error: ' . $e->getMessage());
        jsonError('Database error.');
        return;
    }

    if (!$record) {
        jsonError('Record not found or access denied.');
        return;
    }

    echo json_encode([
        'success' => true,
        'record'  => $record,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

/* ══════════════════════════════════════════════════════════
   Gemini API Helper — Multi-Key Rotation + Multi-Model Fallback
   ══════════════════════════════════════════════════════════ */
function callGeminiApi(array|string $apiKeysInput, string $prompt, int $timeoutSeconds = 90, ?string &$errorMessage = null): ?string
{
    $apiKeys = is_array($apiKeysInput) ? array_values(array_filter($apiKeysInput)) : [$apiKeysInput];
    if (empty($apiKeys)) {
        $errorMessage = 'Gemini API key is not configured.';
        return null;
    }

    @set_time_limit(300);

    $models = defined('GEMINI_MODELS') && !empty(GEMINI_MODELS)
        ? GEMINI_MODELS
        : [
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.8-flash',
            'gemini-3.6-flash',
            'gemini-3-flash',
            'gemma-4-31b-it',
            'gemma-4-26b-it',
        ];

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'temperature'     => 0.4,
            'topP'            => 0.95,
            'maxOutputTokens' => 8192,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

    $errors     = [];
    $caBundle   = '/etc/ssl/certs/ca-certificates.crt';
    $numKeys    = count($apiKeys);

    // Random starting key index so concurrent users distribute load evenly across all 10 keys
    $startKeyIdx = random_int(0, $numKeys - 1);
    $deadKeys    = []; // Keys that return 400/401/403 (invalid/revoked) are skipped for remaining models

    foreach ($models as $model) {
        $model = trim((string)$model);
        if ($model === '') {
            continue;
        }

        // URL-encode model ID just in case
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($model) . ":generateContent";

        for ($k = 0; $k < $numKeys; $k++) {
            $keyIndex = ($startKeyIdx + $k) % $numKeys;

            if (isset($deadKeys[$keyIndex])) {
                continue;
            }

            $currentKey = trim((string)$apiKeys[$keyIndex]);
            $keyLabel   = 'Key#' . ($keyIndex + 1);

            $headers = [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $currentKey,
            ];
            if (str_starts_with($currentKey, 'ya29.')) {
                $headers[] = 'Authorization: Bearer ' . $currentKey;
            }

            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => min($timeoutSeconds, 45),
                CURLOPT_CONNECTTIMEOUT => 12,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ];
            if (is_file($caBundle)) {
                $opts[CURLOPT_CAINFO] = $caBundle;
            }
            curl_setopt_array($ch, $opts);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            // 1. Network error -> try next key
            if ($response === false || $curlErr !== '') {
                $errors["{$model} ({$keyLabel})"] = 'network error: ' . $curlErr;
                error_log("[DocuMind] {$model} ({$keyLabel}): network error: {$curlErr}");
                continue;
            }

            $data = json_decode((string)$response, true);

            // 2. HTTP 200 Success!
            if ($httpCode === 200) {
                $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return trim($text);
                }
                $reason = $data['candidates'][0]['finishReason']
                    ?? ($data['promptFeedback']['blockReason'] ?? 'empty response');
                $errors["{$model}"] = 'no text returned (' . $reason . ')';
                error_log("[DocuMind] {$model} ({$keyLabel}): no text returned ({$reason})");
                // Content blocked by safety filter -> switching keys won't change prompt block, try next model
                break;
            }

            $apiErr = $data['error']['message'] ?? ('HTTP ' . $httpCode);
            $errors["{$model} ({$keyLabel})"] = "HTTP {$httpCode}: {$apiErr}";
            error_log("[DocuMind] {$model} ({$keyLabel}): HTTP {$httpCode} - {$apiErr}");

            // 3. HTTP 404 -> Model doesn't exist or is deprecated; skip remaining keys and move to next model immediately!
            if ($httpCode === 404) {
                break;
            }

            // 4. HTTP 400 / 401 / 403 -> Invalid API Key; mark key as dead so we don't waste time on it
            if ($httpCode === 401 || $httpCode === 403 || ($httpCode === 400 && stripos($apiErr, 'API key') !== false)) {
                $deadKeys[$keyIndex] = true;
                continue;
            }

            // 5. HTTP 429 (Quota/Rate limit) or 5xx (Server overload) -> Immediately try next key in the loop!
            continue;
        }
    }

    // Build a helpful final error message if all keys & models failed
    $busy = false;
    $auth = false;
    foreach ($errors as $e) {
        if (str_contains($e, 'HTTP 429') || str_contains($e, 'HTTP 500') || str_contains($e, 'HTTP 502') || str_contains($e, 'HTTP 503') || str_contains($e, 'HTTP 504')) {
            $busy = true;
        }
        if (str_contains($e, 'HTTP 401') || str_contains($e, 'HTTP 403') || str_contains($e, 'API key')) {
            $auth = true;
        }
    }

    if ($auth && !$busy && count($deadKeys) === $numKeys) {
        $errorMessage = 'All configured Gemini API keys were rejected. Please check your keys in config.local.php.';
    } elseif ($busy) {
        $errorMessage = 'All API keys and fallback models hit rate limits (HTTP 429) or temporary server overload. Please wait 1 minute and try again.';
    } else {
        $sampleErrors = array_slice($errors, 0, 4, true);
        $parts = [];
        foreach ($sampleErrors as $m => $e) {
            $parts[] = "{$m}: {$e}";
        }
        $errorMessage = 'AI request failed — ' . implode(' | ', $parts);
    }

    return null;
}

/* ══════════════════════════════════════════════════════════
   Helpers
   ══════════════════════════════════════════════════════════ */
function jsonError(string $message): void
{
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}