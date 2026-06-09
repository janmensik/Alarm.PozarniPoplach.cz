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

## 2026-06-09 - [Prevent Information Leakage in Cron Scripts]
**Vulnerability:** In `cron.email_import.php`, exceptions were handled by calling `die($ex->getMessage())`. This exposed sensitive stack trace and connection data to STDOUT/STDERR.
**Learning:** Cron managers often capture STDOUT/STDERR and email them. Using `die()` or `echo` for unhandled exceptions in these scripts causes an unintended information disclosure.
**Prevention:** In cron or CLI scripts, always log error messages securely using `error_log()` and terminate execution silently with a proper error code using `exit(1)`.

## 2026-06-09 - [Prevent Cookie Injection via $_REQUEST]
**Vulnerability:** In `include/class.DeviceAuth.php`, variables were retrieved using `$_REQUEST[uuid]`. This implicitly trusts user inputs coming from `$_COOKIE` by default in PHP.
**Learning:** `$_REQUEST` aggregates `$_GET`, `$_POST`, and potentially `$_COOKIE`. If an attacker can inject a cookie, they might be able to override parameters that the script thought were coming safely from a GET or POST payload, known as parameter pollution.
**Prevention:** Avoid `$_REQUEST`. Always explicitly use `$_GET[key] ?? $_POST[key]` to fetch variables from HTTP requests, keeping strict control over where the inputs come from.
