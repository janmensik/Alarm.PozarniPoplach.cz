
## 2025-02-12 - Reusing fetched rows in Jmlib Modul prevents heavy re-queries
**Learning:** The custom `Janmensik\Jmlib\Modul` base class tends to execute the full query defined in `$sql_base` (including all `LEFT JOIN`s) even when fetching by ID using `getId()`. If a method first fetches a row (e.g., using `getRandom()` or `get()`) and then passes the extracted ID to another method (e.g. `getDispatch()`) that calls `getId()`, it results in two heavy identical queries.
**Action:** When a row is already fetched, pass the entire row array instead of just its ID to subsequent methods. Ensure the receiving method is typed to accept both `int|array`, and can gracefully handle the array to bypass re-querying via `getId()`.
