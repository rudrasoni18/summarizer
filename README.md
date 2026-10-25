# DocuMind (fixed)

## Setup
1. Get a NEW key from https://aistudio.google.com/apikey (revoke the old exposed one).
2. Either set env var GEMINI_API_KEY, or copy config.local.php.example -> config.local.php and paste the key.
3. DB env vars: DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS (tables are auto-created).
4. Optional: GEMINI_MODELS="gemini-2.5-flash,gemini-2.5-flash-lite,gemini-2.0-flash" (comma-separated fallback order).

## What changed
- api_handler.php: retry with backoff on 503/429/5xx, automatic model fallback, SSL verification on, no hardcoded key, clean JSON errors.
- config.php: key from env/config.local.php, model list configurable.
- dashboard.php: safe JSON parsing (no cryptic errors on timeouts).
- navbar.php: logged-out "Log in" link fixed (login.php -> index.php).
