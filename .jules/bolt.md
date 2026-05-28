## 2024-05-28 - Avoid re-fetching full entities in `getAdData` when ID is already known
**Learning:** The custom ORM `Janmensik\Jmlib\Modul` executes the full query defined in `$sql_base` (including all JOINs) even for basic lookups like `getId()` or `getRandom()`. Calling methods with just an ID that then re-fetches the same data causes severe N+1-like performance issues.
**Action:** When a method needs to process an entity (like `getAdData`), allow it to accept the already-fetched data array instead of just the ID to avoid expensive DB re-fetching.
