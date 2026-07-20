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

## 2026-06-08 - [Fix Potential IMAP Credential Leak in Cron]
**Vulnerability:** The `cron.email_import.php` script used `die($ex->getMessage())` to handle IMAP connection failures.
**Learning:** Using `die()` or `echo` for errors in CLI scripts prints directly to stdout. In cron environments, these outputs are often captured by hosting providers and sent via email or logged in plain text unmasked, which can leak sensitive data like IMAP credentials present in the exception message.
**Prevention:** Use `error_log()` combined with `exit(1)` for fatal errors in CLI scripts to ensure errors are written to the configured PHP error log (which is usually secured) rather than stdout.

## 2024-06-12 - Replacing $_REQUEST with $_GET and $_POST
**Vulnerability:** Use of `$_REQUEST` in `include/class.DeviceAuth.php` to fetch the device UUID.
**Learning:** `$_REQUEST` merges `$_GET`, `$_POST`, and `$_COOKIE`. This creates a vulnerability where an attacker could spoof the device UUID by injecting a malicious value via a cookie. This happens because depending on the PHP configuration (e.g. `variables_order`), cookies can override GET/POST parameters in `$_REQUEST`.
**Prevention:** Always explicitly use `$_GET` or `$_POST` (or a combination using the null coalescing operator `??`) when reading expected parameters to avoid untrusted data sources like cookies from polluting the input.

## 2024-06-27 - [Fix Open Redirect in Redirect Service]
**Vulnerability:** The redirect service (`view/page/goto.php`) blindly trusted the `target_link` fetched from the database, allowing potential open redirects or execution of `javascript:` URIs if an attacker could inject malicious data into the database.
**Learning:** Even when data originates from a trusted source like a database, it should be validated before use in sensitive operations like `header('Location: ...')` to uphold defense in depth and prevent stored injection vulnerabilities.
**Prevention:** Always validate the scheme of URLs fetched from the database before redirecting to them. Only allow `http` and `https` schemes.

## 2024-10-27 - [Fix SSRF/LFI in Calendar Parsing]
**Vulnerability:** The `$calendar_url` was passed directly to the `ICal` constructor in `include/class.Calendar.php` without validating the scheme, allowing an attacker to parse local files (e.g., via `file://`) or internal endpoints if they could control the calendar URL.
**Learning:** External parsing libraries often support multiple input methods (remote URLs, local files, raw strings). When accepting URLs from potentially untrusted sources or databases, you must explicitly restrict the input to safe web schemes (`http`, `https`) or explicitly check for valid raw content (e.g., checking if it starts with `BEGIN:VCALENDAR`) before passing it to the library. Relying on the library's default behavior can inadvertently enable LFI or SSRF.
**Prevention:** Always validate the scheme of a URL (allow-listing only `http` and `https`) or explicitly check for raw content before passing it to external libraries or functions like `file_get_contents` that process URLs.
