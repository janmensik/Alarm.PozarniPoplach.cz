## 2024-05-25 - Avoid full lookups in custom ORM
**Learning:** The custom `Janmensik\Jmlib\Modul` ORM base class executes the full query (including JOINs) defined in `$sql_base` even for basic ID lookups (`getId()`) or returning random items (`getRandom()`). Passing already-fetched data arrays down to helper methods avoids re-fetching the same data.
**Action:** When working with objects fetched via `get()`, `getRandom()`, etc., reuse the fetched data arrays instead of passing IDs and re-querying the database.
