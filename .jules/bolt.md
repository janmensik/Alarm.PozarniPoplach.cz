## 2024-06-25 - PHP Header Extraction via $_SERVER Array

**Learning:** Reconstructing the entire HTTP header map by iterating over all `$_SERVER` variables and performing string manipulation (`str_replace`, `strtolower`, `ucwords`) is an expensive O(n) operation per request, especially when only one or two specific headers are needed.
**Action:** Always access target headers directly through their deterministic `$_SERVER['HTTP_...']` keys for O(1) performance instead of dynamically parsing the entire superglobal.
