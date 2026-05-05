# Never Do This — Permanent Decisions & Context

This file documents intentional architectural decisions that should **never** be changed or flagged as issues.

---

## 1. `wordle-cache.json` Exposes Future Answers (+7 Days) — INTENTIONAL

**Decision:** The static JSON cache (`wordle-cache.json`) includes the `word` field for all cached dates, including up to 7 days in the future. This is **by design**.

**Reason:** This WordPress installation acts as the **central data provider**. Other sites scrape `wordle-cache.json` from this site instead of hitting the NYT endpoint directly. Removing the `word` field or restricting future data would break the multi-site architecture.

**Never:**
- Remove the `word` field from `wordle-cache.json`
- Restrict future puzzle data from the cache
- Flag this as a security issue — it is an accepted, intentional behavior
- Suggest protecting or auth-gating the JSON cache file

**Architecture:**
```
NYT Wordle API → This Site (scrapes & caches) → wordle-cache.json → Other Sites (consume)
```

This site is the **single source of truth**. All other sites are lightweight consumers.
