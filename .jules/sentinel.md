## 2024-05-18 - [Fix XSS Vulnerabilities in Activation Page]
**Vulnerability:** User inputs and variables like `{$error}`, `{$device_code}`, and `{$session_data.device_uuid}` were not escaped before being rendered in `tpl/page.activate.html`.
**Learning:** Smarty templates in this project do not automatically escape output. If not explicitly escaped, any unvalidated or untrusted user data rendered to the page can result in Cross-Site Scripting (XSS).
**Prevention:** Always use the `|escape` filter (e.g., `{$variable|escape}`) when outputting user-provided data in Smarty templates.

## 2024-05-20 - [Enhance Session and Response Security]
**Vulnerability:** Session management was insecure (missing `HttpOnly`, `SameSite`, and `Secure` cookie flags), and HTTP responses were missing essential security headers, making the application vulnerable to session hijacking, CSRF, and basic XSS/clickjacking attacks.
**Learning:** In PHP, `session_start()` will use the default `php.ini` cookie parameters unless overridden with `session_set_cookie_params()`. Also, the application did not emit headers like `X-Content-Type-Options` and `X-Frame-Options`.
**Prevention:** Always explicitly set secure session cookie parameters using `session_set_cookie_params` (with `httponly=true`, `samesite='Lax'` or `'Strict'`, and `secure` based on the request proto) before starting the session, and use `header()` to emit basic security headers on every response.
