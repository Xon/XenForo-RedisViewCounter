# XenForo-RedisViewCounter
Moves some view counters to use Redis-based increment counters rather than scratch tables in MySQL. 

Redis provides atomic get & del when pushing view counts totals into the database.

Note; Currently only handles thread views.
