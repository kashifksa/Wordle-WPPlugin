# Wordle Hint Pro - Implementation Roadmap

This document is ordered by **implementation priority** — top to bottom, one task at a time.
Tasks marked `[x]` are already completed and kept for reference.

---

## ✅ Already Completed (Reference)

- [x] **One-Click Regeneration**: Admin action to "Regenerate AI Hints" for existing entries.
- [x] **JSON Cache Status Display**: Shows `last_updated` timestamp in admin System Status panel.
- [x] **Archive Shortcode (`[wordle_archive]`)**: Grid of past puzzles with `?date=YYYY-MM-DD` links.
- [x] **Dynamic SEO Meta Tags**: Hooks into Rank Math, Yoast, and WP core `document_title_parts`.
- [x] **Structured Data (Schema)**: Handled via Rank Math — no custom code needed.
- [x] **Dark Mode Persistence**: Restores theme preference from `localStorage` on every load.
- [x] **URL Date Override**: Supports `?date=YYYY-MM-DD` with strict `preg_match` validation.
- [x] **Pre-emptive Scraping**: Fetches 3–7 days in advance if source allows.
- [x] **AI Safety Filter**: Verifies hints do not contain the actual `WORD`.
- [x] **AI Safety Filter — Retry Validation**: Both initial and retry responses are validated before returning.
- [x] **Contextual Prompts**: Prompt forbids direct synonyms or revealing word length.
- [x] **Advanced Fallback Clues**: Local static dictionary as tertiary AI fallback.
- [x] **Smart Cache-Busting**: Versioned JSON fetching (`?v=YYYY-MM-DD`) per user's local date.
- [x] **Standardized JSON Cache Structure**: Stable `{ meta, data: { "YYYY-MM-DD": {...} } }` format.
- [x] **UTC Date Bug Fix**: Replaced `toISOString()` with local date component building.
- [x] **Future-Puzzle Fallback Bug Fix**: `WHERE date <= today` cap prevents future data leaking.
- [x] **Date Input Sanitization**: `preg_match` validates `?date=` before it hits the DB.
- [x] **Update `AGENTS.md`**: Synchronized to reflect PHP/WordPress architecture.
- [x] **WordleBot Statistics**: Difficulty ratings, avg guesses, distributions via Engaging Data.
- [x] **Performance Badges**: Difficulty labels (Easy/Hard/Insane) and Avg. Guesses on frontend.
- [x] **Advanced Linguistics**: Letter frequency scoring and dictionary-based enrichment fields.
- [x] **Merriam-Webster Integration**: `part_of_speech`, `definition`, `etymology`, `synonyms` populated.

---

## 🚀 Implementation Queue (Do In Order)

### Step 1 — ✅ Security: Fix API Key Fails Open (Completed)
**File**: `class-wordle-api.php`, L94
`if ( ! $stored_key ) return true;` makes `/solution` and `/save` fully public when no key is set.
**Fix**: Return `false` when no key is configured, or log a WP site-health warning. (Fixed: Now fails secure).

---

### Step 2 — ✅ Security: Add Pagination Cap to `/all` Endpoint (Completed)
**File**: `class-wordle-api.php`, `get_all_wordle()`
No maximum on `$limit` — anyone can request `?limit=99999` and dump the full database.
**Fix**: Add `$limit = min( 100, intval( $limit ) );` (Fixed: Now capped at 100).

---

### Step 3 — ✅ Security: Sanitize `/save` Endpoint Inputs (Completed)
**File**: `class-wordle-api.php`, `save_wordle()`
`$request->get_params()` passed raw to `insert_puzzle()` — allows mass-assignment of any DB column.
**Fix**: Whitelist and sanitize only known fields before inserting. (Fixed: Implemented strict whitelist).

---

### Step 4 — ✅ Performance: Restrict Cache Check to Frontend Only (Completed)
**File**: `class-wordle-api.php`, L11
`maybe_refresh_cache()` fires on every `init` including admin pages, calling `filemtime()` on every load.
**Fix**: Wrap logic in `if ( ! is_admin() )` check. (Fixed: Restricted to frontend).

---

### Step 5 — ✅ Code Quality: Fix Dead Action Hook (Completed)
**File**: `class-wordle-solver.php`, L15
`add_action('wordle_after_scrape', ...)` is registered but `do_action('wordle_after_scrape')` is never called anywhere.
**Fix**: Fire `do_action('wordle_after_scrape')` in the scraper after a successful save. (Fixed: Action added to Scraper).

---

### Step 6 — ✅ Performance: Optimize Solver Deduplication (Completed)
**File**: `class-wordle-solver.php`, L171
`array_unique()` + `sort()` on a flat array is inefficient on every cache refresh.
**Fix**: Use `$words[$word] = true` associative map during collection, then `array_keys()` at the end. (Fixed: Switched to map-based deduplication).

---

### Step 7 — ✅ Performance: Code Optimization Pass (Completed)
- [x] Consolidate near-identical `get_today_wordle` and `get_wordle_data` REST routes into one endpoint.
- [x] Remove `JSON_PRETTY_PRINT` from `wordle-data.json` in production to reduce file size ~15%.
- [x] Reduce 80+ `!important` declarations in `style.css` by increasing CSS specificity instead. (Cleaned up major UI blocks).

---

### Step 8 — ✅ Admin: Entry Management (CRUD Table) (Completed)
Build a professional `WP_List_Table` in admin to view, edit, and delete past puzzle entries in bulk — replacing the current manual save form. (Fixed: Implemented Wordle_List_Table and Manage Puzzles page).

---

### Step 9 — ✅ Admin: Status Dashboard (Completed)
Create a "System Health" table in admin settings showing the last 7 days with status icons (✅ Success / ⚠️ Manual / ❌ Missing). (Fixed: Added System Health overview to Settings page).

---

### Step 10 — ✅ Admin: Integrated Log Viewer (Completed)
Add a "Logs" tab to display the last 50 scraper/AI events directly in the WordPress admin panel. (Fixed: Implemented persistent logging system and unified tabbed interface).

---

### Step 11 — ✅ Admin: Telemetry / Email Alerting (Completed)
Send an email notification to the site admin if the daily scraper fails after all retries. (Fixed: Integrated wp_mail failure alerts into Wordle_Scheduler).

---

### Step 12 — ✅ Admin: Export/Import Utility (Completed)
Tool to export all Wordle data as CSV for backups or migration to other WordPress sites. (Fixed: Added CSV export engine and integrated buttons into Settings tab).

---

### Step 13 — ✅ API: Rate Limiting (Completed)
Implement basic IP-based rate limiting for public REST endpoints using WordPress transients. (Fixed: Added 60req/min threshold for /data, /today, and /all endpoints).

---

### Step 14 — ✅ Performance: Object Cache Integration (Completed)
Use `wp_cache_get`/`set` to complement the static JSON cache for faster REST API responses. (Fixed: Integrated WordPress Object Cache with 1-hour TTL and smart invalidation).

---

### Step 15 — ✅ Frontend: FAQ Schema Integration (Completed)
Inject JSON-LD `FAQPage` schema into the daily hints page to capture "People Also Ask" snippets and improve CTR. (Fixed: Implemented automated FAQ schema generator in Wordle_Frontend).

---

### Step 16 — ✅ Frontend: "Copy Clues" Button (Completed)
One-click copy of all 4 hints (without the answer) formatted for Pinterest, Twitter/X, and WhatsApp sharing. (Fixed: Added Copy Hints button with clipboard logic and visual feedback).

---

### Step 17 — 🎯 Frontend: Advanced Share Emoji Grid
Refine "Copy Results" to generate a pixel-perfect NYT-style emoji grid (🟩🟨⬜) for viral sharing.

---

### Step 18 — ⏱️ Frontend: Countdown Timer (`[wordle_timer]`)
Live shortcode countdown until the next Wordle puzzle drops at midnight.

---

### Step 19 — ♿ Frontend: Accessibility (a11y) Improvements
Add ARIA labels, keyboard focus states, and screen-reader support for the reveal grid and theme toggle.

---

### Step 20 — ♿ Frontend: High-Contrast "Colorblind Mode"
Toggle to switch Green/Yellow tiles to Orange/Blue (WCAG compliant) for users with color vision deficiency.

---

### Step 21 — 📊 Frontend: Positional Letter Frequency Insights
Surface "Strategy Insight" cards (e.g., *"S appears in position 1 in 15% of all Wordle words"*) for unique data value over competitors.

---

### Step 22 — 🕰️ Frontend: "On This Day" Historical Trivia
Widget showing the Wordle answer/hints from exactly 1 and 2 years ago today to boost internal linking and session time.

---

### Step 23 — 📅 Frontend: Monthly Roundup Generator
Automated tool to generate/update monthly archive pages (e.g., "Wordle Answers: May 2026") for long-tail date-based traffic.

---

### Step 24 — 🖼️ Frontend: Social Image Generator
Auto-generate a "Hint Card" image (PNG/JPG) using PHP GD library for Pinterest/Instagram social sharing.

---

### Step 25 — 📧 Engagement: Daily Reminder Opt-in
"Never Miss a Hint" email capture or browser push notification to build a recurring audience.

---

### Step 26 — 🌍 Multi-Site: CORS Headers
Add appropriate CORS headers so external client sites can fetch `wordle-cache.json` without browser blocking.

---

### Step 27 — 🌍 Multi-Site: Client Plugin
Lightweight WordPress plugin for client sites that reads from this plugin's `wordle-cache.json` instead of scraping NYT directly.

---

### Step 28 — 🌍 Multi-Site: Client Plugin Auth
Optionally protect the JSON cache endpoint with a shared API key for trusted client sites only.
