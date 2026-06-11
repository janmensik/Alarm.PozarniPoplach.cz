## 2026-06-11 - O(1) Header Parsing
**Learning:** PHP's `$_SERVER` array is global and accessing exact keys is O(1). Iterating through the entire `$_SERVER` array, running string replacements (e.g., `str_replace`, `ucwords`, `strtolower`) on every key to format headers is very expensive compared to checking explicitly known keys. This is particularly wasteful in an API endpoint processing every request.
**Action:** Direct property access using explicit keys like `$_SERVER['HTTP_X_DEVICE_UUID']` should be preferred over array iteration to extract specific headers.
