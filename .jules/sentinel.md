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

## 2024-06-02 - [Fix Environment Data Leakage in APPD]
**Vulnerability:** The application was merging `$_ENV` and `$_SERVER` data and passing it to Smarty templates (`{$APPD}`), relying on a block list (`['PASSWORD', 'API_KEY', 'SECRET']`) to filter sensitive data. This is insecure as it leaks variables like `SQL_HOST`, `IMAP_USERNAME`, and arbitrary server paths that do not match the block list pattern.
**Learning:** A block list (negative filtering) is never exhaustive and is an anti-pattern for handling sensitive environment variables.
**Prevention:** Always use an explicit allow list (positive filtering) to explicitly permit only known, safe variables when exposing backend data to the frontend or templates.
