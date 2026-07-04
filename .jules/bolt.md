## 2024-06-13 - O(n) Header Extraction Performance Fix
**Learning:** The `DeviceAuth::getRequestCredentials` method iteratively loops over the entire `$_SERVER` array and manually reformats all header keys in PHP (doing multiple str_replace/strtolower/ucwords calls) just to extract two specific headers (`X-Device-UUID`, `X-Device-Token`, and `Authorization`). This is O(n) relative to the size of `$_SERVER`.
**Action:** When extracting HTTP headers in PHP, avoid iterating over `$_SERVER` and manipulating keys dynamically if only specific headers are needed. Directly access the precise keys (e.g., `$_SERVER['HTTP_X_DEVICE_UUID']`) to achieve O(1) time complexity, reducing CPU cycles and string manipulation overhead, which has the added benefit of being cleaner and preventing header spoofing via similar keys.

## 2026-06-17 - O(1) Performance Improvement when filtering Superglobals
**Learning:** Merging large superglobals (e.g., `$_ENV + $_SERVER`) runs in O(N) time and wastes memory, specifically when you only intend to extract a small defined list of variables.
**Action:** When extracting a specific list of variables from large superglobals like `$_ENV` and `$_SERVER`, iterate over the allowed keys and look them up directly using `isset()` to achieve O(1) performance.

## 2024-06-21 - Lazy initialization of ICal parser
**Learning:** The `Calendar` class was eagerly instantiating the `ICal` parser in its constructor. The `ICal` library performs synchronous network requests to fetch the `.ics` file during instantiation. This blocked the entire request thread every time `Calendar` was instantiated, even before any events were fetched, severely impacting performance for endpoints polled frequently (like `/api/calendar`).
**Action:** When working with objects that fetch remote data or perform heavy operations upon instantiation (like `ICal`), delay their instantiation (lazy loading) until the specific method requiring the data (e.g., `getCalendar`) is called. This prevents unnecessary blocking during class initialization.

## 2024-06-21 - Prevent frequent remote API calls via frontend cache
**Learning:** Alpine.js component was fetching the `calendarUrl` every 30 seconds (`DISPATCH_POLL_INTERVAL_MS`) during peacetime, which performs an ICal feed parse over the network each time. This creates unnecessary backend load and risks upstream API rate limits for data that rarely changes.
**Action:** Implement client-side time-based caching logic directly within the JavaScript components that fetch data repeatedly, ensuring data is only refetched when sufficient time has passed (e.g. 1 hour for calendars).

## 2024-07-01 - Throttle UPDATE statements on high-frequency API endpoints
**Learning:** For endpoints polled frequently (like `/api/dispatch` every 30 seconds), unconditionally executing an `UPDATE` query on every request (e.g., `UPDATE alarm_device_authorized SET last_seen = NOW()`) creates a significant database write load and lock contention bottleneck.
**Action:** When tracking `last_seen` or similar metadata on polled endpoints, read the existing timestamp first and apply a time-based debounce (e.g., only update if 5 minutes have passed) to convert O(N) database writes per request into O(1) writes per time interval.

## 2024-07-04 - Skip full relational data fetching on inactive polled records
**Learning:** In frequently polled endpoints (like `/api/dispatch` every 30 seconds), the system was calling `getLastDispatch()` which internally called `getDispatch()` to fetch full relational data (vehicles, units) *before* determining if that data was even needed (e.g. if the dispatch was outside the active alarm window). This resulted in unnecessary `N+1` style queries on every poll during peacetime.
**Action:** When fetching relational records for endpoints that discard inactive data, modify the getter method (e.g., adding a `$full_data = false` flag) to fetch only the base table columns initially. Only trigger the subsequent, heavier relational queries if the base record is confirmed to be active (e.g., within the alarm window). This prevents wasted database execution.
