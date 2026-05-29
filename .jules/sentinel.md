## 2024-05-18 - [Fix XSS Vulnerabilities in Activation Page]
**Vulnerability:** User inputs and variables like `{$error}`, `{$device_code}`, and `{$session_data.device_uuid}` were not escaped before being rendered in `tpl/page.activate.html`.
**Learning:** Smarty templates in this project do not automatically escape output. If not explicitly escaped, any unvalidated or untrusted user data rendered to the page can result in Cross-Site Scripting (XSS).
**Prevention:** Always use the `|escape` filter (e.g., `{$variable|escape}`) when outputting user-provided data in Smarty templates.

## 2024-05-25 - [Missing Secure Session Cookie Parameters]
**Vulnerability:** `session_start()` was called without explicitly configuring secure cookie parameters (`httponly`, `samesite`, `secure`).
**Learning:** By default, PHP sessions might not have `httponly` or `samesite` set, making them vulnerable to XSS (session hijacking) and CSRF.
**Prevention:** Always configure session cookie parameters securely via `session_set_cookie_params()` before calling `session_start()`. Ensure `httponly` is true, and `samesite` is set appropriately (e.g., `Lax` or `Strict`).

## 2026-05-28 - [Prevent XXE in Dispatch parser]
**Vulnerability:** The HTML parser (`DOMDocument::loadHTML()`) was used to parse external dispatch emails without disabling external network access, leaving it vulnerable to XML External Entity (XXE) attacks.
**Learning:** `DOMDocument` in PHP can be vulnerable to XXE if untrusted content is loaded without explicit flags that disable network access. Note that `LIBXML_NOENT` actually *enables* entity expansion in libxml, which causes XXE vulnerabilities instead of preventing them. It stands for "Substitute Entities". Only `LIBXML_NONET` should be used.
**Prevention:** Always pass the `LIBXML_NONET` flag when using `loadHTML` or `loadXML` on untrusted input to disable external DTDs and network requests. Never use `LIBXML_NOENT` for security purposes as it enables entity expansion.
## 2024-05-29 - [Fix Sensitive Data Exposure in AppData]
**Vulnerability:** The `$filtered_app_data` logic in `index.php` was using a blocklist (`PASSWORD`, `API_KEY`, `SECRET`) to filter superglobals (`$_ENV` and `$_SERVER`) before exposing them to the application layer. This meant any sensitive variable not explicitly named could be exposed in frontend Smarty templates.
**Learning:** Blocklists are incredibly fragile for filtering sensitive data, as any missed keyword or new environment variable (e.g., `DB_USER`) becomes a leak.
**Prevention:** Always use an explicit allowlist (safelist) for passing backend environment variables or server data to the frontend or template engine. Only explicitly defined keys should be copied.
