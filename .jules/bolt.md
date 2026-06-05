## 2026-06-05 - Optimize Modul ORM queries by bypassing getId()
**Learning:** The custom `Janmensik\Jmlib\Modul` ORM executes the full query defined in `$sql_base` (including all JOINs) even for basic lookups like `getId()` or `getRandom()`.
**Action:** To optimize performance, reuse already-fetched row data instead of passing IDs to methods. Update receiving methods to accept `int|array` to gracefully handle and use the pre-fetched array, bypassing redundant `getId()` calls.
