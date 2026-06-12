## 2026-06-12 - Inefficient Superglobal Iteration

**Learning:** Iterating over massive superglobals like `$_SERVER` to parse headers dynamically using string functions (`str_replace`, `ucwords`, etc.) introduces a hidden O(N) performance bottleneck.
**Action:** When extracting specific known HTTP headers (e.g., `X-Device-Uuid`), bypass loops entirely and use direct O(1) array access via their normalized `HTTP_` keys (e.g., `$_SERVER['HTTP_X_DEVICE_UUID']`).
