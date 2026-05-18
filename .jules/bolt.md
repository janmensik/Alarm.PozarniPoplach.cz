## 2026-05-18 - [N+1 query reduction in Modul based models]
**Learning:** The Janmensik\Jmlib\Modul ORM executes the full query defined in $sql_base (including heavy JOINs) even for basic lookups like getId() or getRandom(). Calling get() and then getId() on the result causes a 100% redundant heavy query.
**Action:** When a method needs to load additional relationships or process a record, accept int|array $idOrData instead of just int $id. Pass the full row array if already fetched, bypassing the redundant getId() call.
