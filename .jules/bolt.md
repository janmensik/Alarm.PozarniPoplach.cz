## 2026-05-30 - Reuse fetched row data with Modul abstraction
**Learning:** The `Janmensik\Jmlib\Modul` ORM/base class executes the full query defined in `$sql_base` (including all JOINs) even for basic lookups like `getId()` or `getRandom()`.
**Action:** To optimize performance and avoid N+1 queries, pass already-fetched row data directly to helper methods (like `getAdData`) instead of just passing the ID, which forces the method to unnecessarily re-fetch the data using `getId()` or `get()`.
