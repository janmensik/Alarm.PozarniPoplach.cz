## 2024-05-19 - Jmlib ORM getId Re-fetching Anti-pattern
**Learning:** In the `Janmensik\Jmlib\Modul` ORM, methods like `get()` and `getRandom()` fetch full rows. However, when these methods are combined with subsequent calls to `getId()` (or methods using it internally like `getAdData` did), it creates a redundant query, re-fetching the exact same row by its ID.
**Action:** When row data has already been fetched via `get` or `getRandom`, directly reuse that array for any local processing instead of passing its `id` to another internal fetch method.
