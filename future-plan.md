# Wordle Hint Pro - Future Implementation Plan

This document outlines the roadmap for upcoming features and optimizations for the Wordle Hint Pro WordPress plugin.

## 1. Administrative Insights & Monitoring
- [ ] **Status Dashboard**: Create a "System Health" table in the plugin settings showing the last 7 days of entries with status icons (Success, Manual, Missing).
- [ ] **Integrated Log Viewer**: Add a "Logs" tab to display the last 50 scraper/AI events directly in the WordPress admin.
- [x] **One-Click Regeneration**: Add an admin action to "Regenerate AI Hints" for existing entries to handle low-quality AI outputs.
- [ ] **Entry Management**: Build a simple CRUD table in the admin to edit/delete past entries without using the manual save form.
- [x] **JSON Cache Status Display**: Show last_updated timestamp of `wordle-cache.json` in the admin panel under System Status for easy debugging.

## 2. Frontend & User Experience
- [ ] **Archive Shortcode (`[wordle_archive]`)**: Render a grid or list of past puzzles with links to their hints (e.g., `?date=YYYY-MM-DD`).
- [ ] **Countdown Timer (`[wordle_timer]`)**: A live countdown until the next Wordle hints are released.
- [ ] **"Copy Clues" Button**: A one-click button to copy hints (without the answer) for social sharing.
- [x] **Dynamic SEO Meta Tags**: Hooks into `document_title_parts` (WP core), Rank Math, and Yoast filters to update the page title to "Wordle Hint #[number] - [Date]" and inject a keyword-rich meta description dynamically based on the current puzzle.
- [x] **Structured Data (Schema)**: Handled via Rank Math plugin — no custom code needed.
- [x] **Dark Mode Persistence**: Restore user's dark/light theme preference from `localStorage` on every page load so it survives refreshes.
- [x] **URL Date Override**: Support `?date=YYYY-MM-DD` in the URL for testing/archive viewing with strict format validation.

## 3. Reliability & AI Optimization
- [x] **Pre-emptive Scraping**: Update the scheduler to attempt fetching 3–7 days in advance if the source allows.
- [x] **AI Safety Filter**: Add logic to verify that the generated hints do *not* contain the actual `WORD`.
- [x] **AI Safety Filter — Retry Validation**: Fixed bug where the safety-filter retry result was never re-validated before being returned. Now both the initial and retry responses are checked; falls back to static hints if both fail.
- [x] **Contextual Prompts**: Refine the `wordle_hint_ai_prompt` to explicitly forbid direct synonyms or mentioning the word length if it's always 5.
- [x] **Advanced Fallback Clues**: Create a local dictionary of static hints for common words as a tertiary fallback.

## 4. Technical Performance & Security
- [ ] **API Rate Limiting**: Implement basic IP-based rate limiting for REST endpoints using WordPress transients.
- [x] **Smart Cache-Busting**: Implement versioned JSON fetching (`?v=YYYY-MM-DD`) based on user local date to prevent stale data while maintaining high performance.
- [x] **Standardized JSON Cache Structure**: JSON output now follows a stable `{ meta: {...}, data: { "YYYY-MM-DD": {...} } }` format ready for multi-site client plugin consumption.
- [x] **UTC Date Bug Fix**: Replaced `toISOString()` (which returns UTC) with local date component building. Prevents users in UTC+5 at 11 PM from seeing the wrong Wordle.
- [x] **Future-Puzzle Fallback Bug Fix**: Fixed PHP fallback that was returning future puzzles (up to 7 days ahead) instead of yesterday's data when today's entry was missing. Now uses `WHERE date <= today` cap.
- [x] **Date Input Sanitization**: Added strict `preg_match` format validation on `?date=` URL parameter before it reaches the database query.
- [ ] **Object Cache Integration**: Utilize `wp_cache_get`/`set` to complement the static JSON cache for even faster API responses.
- [ ] **Telemetry/Alerting**: (Optional) Send an email notification to the site admin if the daily scraper fails after all retries.

## 5. Maintenance & Documentation
- [x] **Update `AGENTS.md`**: Synchronize the roadmap to reflect the PHP/WordPress architecture instead of the original Node.js plan.
- [ ] **Export/Import Utility**: Add a tool to export Wordle data as CSV for backups or migration to other sites.

## 6. Multi-Site Architecture (Future)
- [ ] **Client Plugin**: Create a lightweight client plugin that fetches data from this main plugin's `wordle-cache.json` endpoint instead of scraping externally.
- [ ] **CORS Headers**: Add appropriate CORS headers so external client sites can fetch `wordle-cache.json` without browser blocking.
- [ ] **Client Plugin Auth**: Optionally protect the JSON endpoint with a shared API key for trusted client sites.
