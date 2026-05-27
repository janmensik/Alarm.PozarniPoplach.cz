# Deep Analysis — Alarm.PozarniPoplach.cz (Kiosk Dispatch Monitor)

Scope: PHP backend (Bramus router + Smarty + Jmlib) + Alpine.js kiosk frontend, deployed on Raspberry Pi 4 / old PCs running a browser in kiosk mode. The screen must run for weeks unattended, must survive flaky internet, must use minimal RAM/CPU, and must never silently fail when a real alarm is dispatched.

This document lists concrete issues found in the code, ordered by severity, with reproduction notes and recommended fixes. It is grouped into: **Reliability / Fail‑safety**, **Performance & Resource Use**, **Security**, **Kiosk-specific concerns**, and **Code quality / maintainability**.

---

## 1. Reliability & Fail‑Safety (highest priority for a kiosk)

### 1.1 Polling intervals are too long for a life‑critical alert
[ui/alpine.js](ui/alpine.js#L1-L2)
```js
const AUTH_POLL_INTERVAL_MS = 5 * 1000;
const DISPATCH_POLL_INTERVAL_MS = 30 * 1000;
```
A firefighter alarm is time-critical. **30 s** worst-case latency between dispatch being stored in DB and being shown on screen is a long time when seconds matter.

Recommendation:
- Lower `DISPATCH_POLL_INTERVAL_MS` to 5–10 s.
- Better: implement **Server-Sent Events** (`text/event-stream`) on `/api/dispatch/stream`. SSE keeps a single HTTP connection open, uses far less CPU than per‑poll request and reconnects automatically.
- Or use **conditional polling**: send `If-None-Match` / `If-Modified-Since` and have the API return `304 Not Modified` when nothing changed. Drops payload from kilobytes to zero.

### 1.2 `setInterval` for clock and timer drift on long uptimes
[ui/alpine.js](ui/alpine.js#L31-L33)
```js
setInterval(() => this.updateClock(), 1000);
...
setInterval(() => this.updateTimer(), 1000);
```
On a Raspberry Pi running for weeks, `setInterval` drifts. The timer "fires alert at exactly diff===600" check will be **missed** if the tab is throttled (Chromium throttles background timers) or skipped during GC.

Fix in [ui/alpine.js](ui/alpine.js#L246-L253):
```js
if (diff === 600) { this.playAlert("limit"); }
```
This is fragile — change to:
```js
if (diff >= 600 && !this.limitAlerted) { this.playAlert("limit"); this.limitAlerted = true; }
```
Reset `limitAlerted` whenever a new event is detected.

### 1.3 Page never recovers from a broken auth state automatically
[ui/alpine.js](ui/alpine.js#L113-L116)
```js
} catch (e) {
    console.error("Auth init failed", e);
    this.authStatus = 'error';
    setTimeout(() => this.startAuthFlow(), 10000);
}
```
But on `validateAndStart` failure ([ui/alpine.js](ui/alpine.js#L100-L105)) it retries every 10 s. Good. However the `validateAndStart` retry uses `setTimeout`, not capped, no exponential backoff — fine on a kiosk, but you must also watch for **wedged JS runtimes**. The HTML meta refresh every 24 h ([tpl/page.alarm.html](tpl/page.alarm.html#L24)) is the only watchdog.

Recommendation:
- Add a JavaScript watchdog: if no successful `fetchData()` for 5 minutes, call `location.reload()`.
- Add a "service worker" or a tiny shell that pings `/api/version` every 60 s; if the page itself stops calling it, the SW can force reload via `clients.matchAll().then(c => c.forEach(x => x.navigate(x.url)))`. (Even simpler: a hidden iframe with its own meta-refresh.)

### 1.4 No offline cache / last‑known dispatch
If internet drops while an alarm is in progress, the next failed fetch leaves `this.data` intact (good), but a hard refresh (`meta refresh` or watchdog) during the outage will load the page and immediately go to `authStatus='error'` because `/api/auth/device/validate` cannot be reached. The previous dispatch will not be visible.

Fix:
- Persist last successful `data` in `localStorage` and render it immediately on boot while validation runs in background.
- Add a **service worker** that caches `tpl/page.alarm.html`, `ui/alarm.dist.css`, `ui/alpine.js`, `favicon.svg` and the last `dispatch.json`. Then a Pi without internet still boots into "stale data" mode.

### 1.5 The `audioEnabled` autoplay check is wrong
[ui/alpine.js](ui/alpine.js#L172-L181)
```js
const ctx = new AudioContext();
this.audioEnabled = ctx.state === "running";
```
On Chromium kiosk mode without a user gesture, AudioContext is in `suspended` state until first interaction. This silently disables alarm sound. Without sound, the kiosk can be **muted forever** until someone clicks the speaker icon.

Fix:
- Launch Chromium with `--autoplay-policy=no-user-gesture-required`.
- Don't gate `audioEnabled` on AudioContext state; just attempt `el.play()` and re-enable on success.
- On `playAlert` failure, surface a visible badge "Sound blocked – click to enable" instead of silently setting `audioEnabled=false`.

### 1.6 Email-import cron is single-threaded and uses `die()` on failure
[cron.email_import.php](cron.email_import.php#L99-L106)
```php
} catch (ConnectionException $ex) {
    $DB->query("UPDATE import_log ...");
    die("IMAP connection failed: " . $ex->getMessage());
}
```
- `die()` prints the IMAP exception text to the cron output; if a hosting alert is set on stderr/stdout, an IMAP password leak is possible. Use `error_log()` + `exit(1)`.
- A single failed cron leaves the `import_log` row in `running` status if PHP itself crashes (no `register_shutdown_function`). Add a shutdown handler that flips status to `error` if not already finalized.
- The 20-mail-per-run cap is reasonable but combine it with a **lock file** (`flock`) to prevent overlapping cron runs from re-processing the same email twice.

### 1.7 `Dispatch::beautifulLastDispatch` calls Google Maps API synchronously per request
[include/class.Dispatch.php](include/class.Dispatch.php#L171-L191) — there's already a DB cache (`has_streetview`, `directions_polyline`), good. But:
- First request for a brand-new dispatch blocks until both Directions API (≤2 s) **and** Streetview metadata API (≤2 s) finish. The kiosk waits up to 4 s for the alarm payload.
- Move first-time fetch out of the request path: do it in `cron.email_import.php` immediately after `INSERT` so by the time the kiosk polls, the cache is warm.

### 1.8 Dispatch staleness window comes from `getenv('DEFAULT_ALARM_SHOWN')` with no fallback
[view/api/dispatch.php](view/api/dispatch.php#L57)
```php
if (... time() - $data['dispatched_at_ts'] <= (getenv('DEFAULT_ALARM_SHOWN') ?? 60) * 60) {
```
`getenv()` returns `false` (not `null`) when missing, so `?? 60` never triggers — staleness will be `false * 60 = 0`, meaning **no alarm is ever shown**.

Fix:
```php
$shownMinutes = (int) (getenv('DEFAULT_ALARM_SHOWN') ?: 60);
```
This is a real production bug. Audit all `getenv() ?? X` patterns in the codebase.

### 1.9 `version.php` references a file that does not exist
[view/api/version.php](view/api/version.php#L13)
```php
__DIR__ . '/../../tpl/page.active.html',
```
Should be `page.activate.html`. Currently the missing file is silently skipped (`file_exists` returns false), but the version hash never reflects activation page changes.

### 1.10 No HTTP cache headers on `/api/dispatch`
Every poll re-downloads ~10–30 KB JSON. Add `ETag` based on `MAX(received_ts)` for the unit, return `304` when unchanged.

---

## 2. Performance & Resource Use (Raspberry Pi 4)

### 2.1 External CDNs on every kiosk page
[tpl/page.alarm.html](tpl/page.alarm.html#L15-L23):
```html
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:..." rel="stylesheet" />
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script src="https://kit.fontawesome.com/3208d26f35.js" crossorigin="anonymous"></script>
```
Problems:
- Three external origins. Each cold boot pays DNS + TLS handshake. If `kit.fontawesome.com` is unreachable, the page boots without icons.
- `alpinejs@3.x.x` resolves to the latest 3.x at runtime — a **breaking dependency you don't control**. Pin to an exact version (`3.14.1` or whatever you've tested) and self‑host the file in `ui/`.
- Font Awesome Kit is a JS-injected stylesheet (~100 KB of CSS + woff). Replace with a curated subset of SVG icons (or icon font) bundled locally. On a Pi this saves ~150 ms per cold boot and avoids font swap flash.
- Google Fonts: self-host `Public Sans` woff2 (about 30 KB for the weights you use); add `font-display: swap` already implied. Removes a third‑party network dependency entirely.

### 2.2 Tailwind via CDN is referenced for the cron log page
[cron.email_import.php](cron.email_import.php#L168)
```html
<script src="https://cdn.tailwindcss.com"></script>
```
This is a 350 KB JIT compiler that runs in the browser. Fine for a debug page, but if a cron runner with no internet hits this, the page looks broken and may also throw CSP errors if you ever add CSP.

### 2.3 No HTTP caching for static assets
Nothing in the repo sets `Cache-Control`. The Pi will revalidate `ui/alarm.dist.css` and `ui/alpine.js` on every page reload (24h meta refresh + watchdog reloads). Add `Cache-Control: public, max-age=86400, immutable` via `.htaccess` / nginx config and bust the cache using the existing `/api/version` mechanism (append `?v=<hash>` to script and CSS URLs).

### 2.4 `SQL_CALC_FOUND_ROWS` on every list query
[include/class.Dispatch.php](include/class.Dispatch.php#L13) and [include/class.Ad.php](include/class.Ad.php#L9).
`SQL_CALC_FOUND_ROWS` is deprecated in MySQL 8 and significantly slower than a separate `COUNT(*)`. The kiosk path only needs one row (`getLastDispatch`) but still pays the cost. Replace with `LIMIT 1` + no row count.

### 2.5 Per-poll DB cleanup
[include/class.DeviceAuth.php](include/class.DeviceAuth.php#L32)
```php
$this->DB->query('DELETE FROM alarm_device_session WHERE expires_at < NOW()', ...);
```
This runs on every `initSession`, which is fine in practice, but for `checkSessionStatus` (called every 5 s by every kiosk in pending state) the table is scanned on every poll. Index `device_code` (primary key already?) and `expires_at`. Move cleanup to a dedicated cron (`* * * * *`).

### 2.6 `mb_internal_encoding` and `date_default_timezone_set` repeated
Set once in [inc.startup.php](inc.startup.php) instead of per-entrypoint ([index.php](index.php#L75-L77), [cron.email_import.php](cron.email_import.php#L21-L22)).

### 2.7 Smarty `compile_check` is tied to `DEBUGGING`
[inc.smarty.php](inc.smarty.php#L25)
```php
$Smarty->compile_check = getenv('DEBUGGING');
```
In production with `DEBUGGING=0` this is fine, but verify the value isn't truthy. Also ensure `$Smarty->setCaching(Smarty::CACHING_LIFETIME_CURRENT)` is enabled for templates that don't change per-request — the alarm and activate pages are nearly static.

### 2.8 `assets/timer-*.mp3` — `preload="auto"` downloads on every boot
Fine if they are tiny. Verify each is < 20 KB. If larger, switch to `preload="metadata"` and load on first user interaction.

---

## 3. Security

### 3.1 SQL built by string concatenation everywhere
All `Modul`-derived classes use `mysqli_real_escape_string` + string interpolation instead of prepared statements ([include/class.DeviceAuth.php](include/class.DeviceAuth.php), [include/class.Dispatch.php](include/class.Dispatch.php), [include/class.Ad.php](include/class.Ad.php)). This is OK if every value is escaped, but the pattern is **fragile**:
- [include/class.DeviceAuth.php](include/class.DeviceAuth.php#L92-L98) — if `$deviceName` is `null`, PHP 8.1+ emits a deprecation warning from `mysqli_real_escape_string`. Forces a string cast.
- [include/class.DeviceAuth.php](include/class.DeviceAuth.php#L34) `$expiresAt` is inserted **without escaping** (generated by `date()` so safe today, but a future refactor could break it).
- `intval($unitId)` etc. is fine, but mixing styles makes audits hard.

Recommend migrating to **prepared statements** in `Jmlib\Database` (single change, no callsite churn possible since query strings flow through `DB->query`).

### 3.2 `linkSessionToUnit` does not authenticate the firefighter
[view/page/activate.php](view/page/activate.php#L45-L51) and [include/class.DeviceAuth.php](include/class.DeviceAuth.php#L93-L101)

The phone visiting `/activate?code=XXX` is shown a `<select>` of **all units** with no authentication. Anyone on the internet who guesses an 8-char code (32^8 ≈ 1.1 trillion; brute force impractical) can pair a kiosk to any random unit they pick from the dropdown.

**Critical**: A malicious actor watching a station's screen can read the code, race to `/activate?code=…`, and bind the screen to a different unit, blacking it out from real alarms. Or they can scrape the unit list (also leaked to the public).

Fixes:
1. The activate page **must** require firefighter login (the AUTH_FLOW.md mentions "logs in & selects Unit" — but the current `/activate` page has no login).
2. Filter the unit `<select>` to only units the logged-in firefighter belongs to.
3. Add rate-limiting on `/activate` and `/api/auth/device/*` endpoints (e.g. 10 req / min / IP).
4. Until login exists, at minimum: require both the **device_code AND a one-time PIN displayed on the kiosk** that the firefighter must type — defense in depth.

### 3.3 Refresh token is stored in `localStorage`
[ui/alpine.js](ui/alpine.js#L42)
```js
localStorage.setItem("alarm_refresh_token", this.refreshToken);
```
Any XSS on the kiosk page exfiltrates the token. The token is **long‑lived**, hashed in DB but plaintext on the kiosk. Since the kiosk is dedicated hardware this is moderate risk, but combined with the CDNs in §2.1 (any compromised CDN can read the token), it becomes high.

Mitigations:
- Self-host all JS/CSS (eliminates CDN supply-chain).
- Add a strict `Content-Security-Policy` header (already started with `X-Frame-Options`, `X-Content-Type-Options`).
- Store the token in an **HttpOnly cookie** scoped to the device UUID instead of localStorage. The cookie must be `Secure; SameSite=Strict`.

### 3.4 Missing Content-Security-Policy and HSTS
[index.php](index.php#L48-L49) sets only:
```php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
```
Add:
```php
header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://maps.googleapis.com https://api.mapbox.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://kit.fontawesome.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; connect-src 'self';");
```
Tighten once external CDNs are removed (§2.1).

### 3.5 `$APPD->setData('APP', $filtered_app_data)`
[index.php](index.php#L20-L33)
The block list `['PASSWORD', 'API_KEY', 'SECRET']` is **not exhaustive**. Variables like `GOOGLE_MAPS_API_KEY`, `MAPBOX_API_KEY`, `IMAP_USERNAME`, `SQL_HOST`, `SQL_USER`, `SQL_DATABASE`, `JWT_SECRET` will be exposed if assigned to Smarty templates (and indeed `$Smarty->assign('APPD', $APPD->getData())` does just that in [index.php](index.php#L125)).

Look at the Smarty `{$APPD}` use — if any template prints it (e.g. for debug), all `$_SERVER` plus all `$_ENV` (including `SQL_*` credentials) leaks to the browser.

Fix: use an **allow list** instead of a deny list. Only copy explicitly chosen keys (e.g. `BASE_URL`, `APP_NAME`, `APP_VERSION`).

### 3.6 CSRF token reused across kiosk + activate sessions
[index.php](index.php#L52-L62) starts a session named `pozarnipoplach_alarm` on every request including `/api/*` endpoints. API endpoints don't need sessions — disable `session_start()` for `/api/*` paths to save lock contention and reduce attack surface.

The CSRF token in `$_SESSION['csrf_token']` in [view/page/activate.php](view/page/activate.php#L15-L19) is fine but the session cookie is **not regenerated** after authorization. Add `session_regenerate_id(true)` after successful `linkSessionToUnit`.

### 3.7 `Dispatch::parseDispatchHtml` loads arbitrary HTML with libxml
[include/class.Dispatch.php](include/class.Dispatch.php#L412-L416)
```php
libxml_use_internal_errors(true);
$doc = new DOMDocument();
$doc->loadHTML('<?xml encoding="utf-8" ?>' . $htmlContent);
```
Risk: PHP's libxml has had **XXE** issues. Add:
```php
libxml_disable_entity_loader(true); // PHP < 8; in PHP 8 use LIBXML_NONET
$doc->loadHTML('<?xml encoding="utf-8" ?>' . $htmlContent, LIBXML_NONET | LIBXML_NOENT);
```
Even though emails come from a trusted source today, defense in depth costs nothing.

### 3.8 No expiration on `alarm_device_authorized`
Devices never expire. If a Pi is stolen / decommissioned, the only way to revoke is manual DB delete. Add `expires_at` (e.g. 1 year), and re-issue on `last_seen` updates.

### 3.9 `goto.php` open redirect
[view/page/goto.php](view/page/goto.php#L45-L46)
```php
header('Location: ' . $ad_data['target_link']);
exit;
```
`target_link` is stored in DB by admins, so direct risk is low. Still: validate the scheme is `http`/`https` and the host is not the same site, to prevent reflected open-redirect via DB injection.

### 3.10 `error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED)` only in cron
[cron.email_import.php](cron.email_import.php#L8) silences deprecations, but `index.php` does not. Production should never echo PHP warnings to the kiosk (would break the alarm view). Set `display_errors=Off` in `inc.startup.php` based on `getenv('DEBUGGING')`.

---

## 4. Kiosk‑specific operational concerns

### 4.1 24‑hour meta refresh forces a reload mid-alarm
[tpl/page.alarm.html](tpl/page.alarm.html#L24)
```html
<meta http-equiv="refresh" content="86400">
```
If a real alarm fires at hour 23:59:30, the reload races against it and may briefly show "Inicializace…" while the alarm is active.

Fix: gate the reload behind `data?.dispatch_status !== 'alarm'`. Do it from JS:
```js
setInterval(() => {
  if (this.data?.dispatch_status !== 'alarm') location.reload();
}, 24*60*60*1000);
```

### 4.2 Browser memory leak over weeks
Listeners and intervals are never cleared, image elements from the static map are reassigned via `:src` (Chromium will retain decoded bitmap). Over weeks the renderer process can grow >1 GB on a Pi 4 with 4 GB RAM and get OOM-killed.

Mitigations:
- Periodic `location.reload()` (e.g. every 6 h) at peacetime.
- Or run a small systemd timer on the Pi: `pgrep chromium | xargs -I{} cat /proc/{}/status` → if RSS > N, restart Chromium.

### 4.3 Network failure UX
On `connectionError = 'api_unreachable'` you only update the footer indicator. A firefighter looking at the screen has no idea their alarm system is half-broken. Recommend:
- Big banner overlay after 60 s offline: "POZOR: Spojení se serverem přerušeno, údaje nejsou aktuální od HH:MM".
- Audible heartbeat alarm after 5 min offline.

### 4.4 No watchdog for the underlying browser
Recommend documenting in README the kiosk hardening:
- Run Chromium under `systemd` with `Restart=always`.
- Use `--noerrdialogs --disable-translate --disable-features=Translate --kiosk --incognito` and `--disable-pinch --overscroll-history-navigation=0`.
- Disable Chromium "session restore" prompts on crash: `chromium --disable-session-crashed-bubble --disable-infobars`.
- A second cron `*/5 * * * *` that curl-checks the kiosk URL from the Pi and restarts X if no response.

### 4.5 No telemetry / "is the kiosk alive?" check
The server has `last_seen` updated by `validateDevice` on every API hit. Add an admin dashboard alert: "Kiosk X has not polled for > 5 min." Otherwise a hung kiosk goes unnoticed for weeks.

---

## 5. Code quality / maintainability

- **No type strictness:** no `declare(strict_types=1);` at the top of any file. Forces defensive `intval()` everywhere.
- **`include`‑style routing**: `include('./view/api/dispatch.php')` runs in global scope. A typo in `$unit_id` in one file leaks into the next request's globals if a worker is reused (e.g. PHP-FPM). Wrap each endpoint in a function or class.
- **`getenv()` everywhere** instead of caching in `AppData`. Each call is a syscall on some PHP builds.
- **`$_REQUEST`** in [include/class.DeviceAuth.php](include/class.DeviceAuth.php#L168) merges GET, POST, COOKIE — known footgun. Prefer `$_GET['uuid'] ?? $_POST['uuid']`.
- **Magic numbers**: `600` (10-minute alarm), `5 minutes` session expiry, `20` emails/cron. Move to `tpl/app.conf` or `.env`.
- **No structured logging.** `$DB->messages` is the only trace. Add a tiny PSR-3 logger writing JSON lines to `/var/log/alarm/`.
- **Tests cover the happy paths only.** Add Pest tests for:
  - `view/api/dispatch.php` returning peacetime when `dispatched_at` older than `DEFAULT_ALARM_SHOWN`.
  - `DeviceAuth::checkSessionStatus` returning `null` for expired codes.
  - The "fail to bind device_code to a different unit" anti‑abuse path.

---

## 6. Concrete fix backlog (suggested order)

| # | Severity | Fix | Effort |
|---|----------|-----|--------|
| 1 | Critical | §1.8 `getenv('DEFAULT_ALARM_SHOWN') ?? 60` — alarms may never show | 5 min |
| 2 | Critical | §3.2 `/activate` requires firefighter login + unit allow-list | 1 day |
| 3 | High | §1.5 Audio autoplay always-off bug | 1 h |
| 4 | High | §2.1 self-host Alpine.js / Font Awesome / Public Sans | 2 h |
| 5 | High | §1.4 Service worker + last-known dispatch cache | 0.5 day |
| 6 | High | §3.5 APP allow-list instead of deny-list | 30 min |
| 7 | Medium | §1.1 SSE or shorter `DISPATCH_POLL_INTERVAL_MS` | 0.5 day |
| 8 | Medium | §1.7 warm Maps cache from cron | 2 h |
| 9 | Medium | §3.4 CSP / HSTS / Referrer-Policy headers | 1 h |
| 10 | Medium | §4.1 gate meta-refresh behind peacetime | 15 min |
| 11 | Medium | §1.2 fix timer "limit" alert miss | 15 min |
| 12 | Medium | §2.3 `Cache-Control` for static assets | 30 min |
| 13 | Low | §1.9 `page.activate.html` typo in version.php | 2 min |
| 14 | Low | §2.4 drop `SQL_CALC_FOUND_ROWS` from kiosk path | 30 min |
| 15 | Low | §3.7 `LIBXML_NONET` on dispatch parser | 5 min |
| 16 | Low | §4.5 admin "kiosk alive" telemetry | 0.5 day |

---

## 7. Recommended Pi 4 kiosk launcher snippet

For documentation in README — a hardened Chromium kiosk command:

```bash
chromium-browser \
  --kiosk \
  --incognito \
  --noerrdialogs \
  --disable-infobars \
  --disable-session-crashed-bubble \
  --disable-features=Translate,TranslateUI,InfiniteSessionRestore \
  --autoplay-policy=no-user-gesture-required \
  --check-for-update-interval=604800 \
  --overscroll-history-navigation=0 \
  --disable-pinch \
  --password-store=basic \
  --no-first-run \
  --user-data-dir=/home/pi/.kiosk-profile \
  https://alarm.pozarnipoplach.cz/
```

Wrap in a `systemd` user unit with `Restart=always` and an `ExecStartPre=/bin/sleep 5` to wait for network.

---

*Generated by automated source review. Numbers / behaviour assertions in §1.8 and §1.9 should be verified by a quick grep before patching.*
