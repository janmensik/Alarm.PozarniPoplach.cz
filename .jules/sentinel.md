## 2024-06-04 - APP Variable Leakage via Blocklist
**Vulnerability:** `$_SERVER` and `$_ENV` were being filtered using a non-exhaustive blocklist (`['PASSWORD', 'API_KEY', 'SECRET']`) and exposed to Smarty templates. This allowed sensitive credentials (like `SQL_HOST`, `GOOGLE_MAPS_API_KEY`) to be leaked.
**Learning:** Blocklists are fundamentally insecure for filtering credentials because it's impossible to predict all sensitive variable names.
**Prevention:** Always use an explicit allowlist (positive list) of known safe variables when exposing environment or server data to the frontend or templates.
