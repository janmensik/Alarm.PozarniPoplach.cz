## 2024-06-13 - O(n) Header Extraction Performance Fix
**Learning:** The `DeviceAuth::getRequestCredentials` method iteratively loops over the entire `$_SERVER` array and manually reformats all header keys in PHP (doing multiple str_replace/strtolower/ucwords calls) just to extract two specific headers (`X-Device-UUID`, `X-Device-Token`, and `Authorization`). This is O(n) relative to the size of `$_SERVER`.
**Action:** When extracting HTTP headers in PHP, avoid iterating over `$_SERVER` and manipulating keys dynamically if only specific headers are needed. Directly access the precise keys (e.g., `$_SERVER['HTTP_X_DEVICE_UUID']`) to achieve O(1) time complexity, reducing CPU cycles and string manipulation overhead, which has the added benefit of being cleaner and preventing header spoofing via similar keys.

## 2026-06-17 - O(1) Performance Improvement when filtering Superglobals
**Learning:** Merging large superglobals (e.g., `$_ENV + $_SERVER`) runs in O(N) time and wastes memory, specifically when you only intend to extract a small defined list of variables.
**Action:** When extracting a specific list of variables from large superglobals like `$_ENV` and `$_SERVER`, iterate over the allowed keys and look them up directly using `isset()` to achieve O(1) performance.

## 2024-06-21 - Lazy initialization of ICal parser
**Learning:** The `Calendar` class was eagerly instantiating the `ICal` parser in its constructor. The `ICal` library performs synchronous network requests to fetch the `.ics` file during instantiation. This blocked the entire request thread every time `Calendar` was instantiated, even before any events were fetched, severely impacting performance for endpoints polled frequently (like `/api/calendar`).
**Action:** When working with objects that fetch remote data or perform heavy operations upon instantiation (like `ICal`), delay their instantiation (lazy loading) until the specific method requiring the data (e.g., `getCalendar`) is called. This prevents unnecessary blocking during class initialization.
