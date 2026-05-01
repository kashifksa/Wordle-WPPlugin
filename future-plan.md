# Wordle Hint Pro - Future Implementation Plan

This document outlines the roadmap for upcoming features and optimizations for the Wordle Hint Pro WordPress plugin.

## 1. Administrative Insights & Monitoring
- [ ] **Status Dashboard**: Create a "System Health" table in the plugin settings showing the last 7 days of entries with status icons (Success, Manual, Missing).
- [ ] **Integrated Log Viewer**: Add a "Logs" tab to display the last 50 scraper/AI events directly in the WordPress admin.
- [x] **One-Click Regeneration**: Add an admin action to "Regenerate AI Hints" for existing entries to handle low-quality AI outputs.
- [ ] **Entry Management**: Build a simple CRUD table in the admin to edit/delete past entries without using the manual save form.

## 2. Frontend & User Experience
- [ ] **Archive Shortcode (`[wordle_archive]`)**: Render a grid or list of past puzzles with links to their hints (e.g., `?date=YYYY-MM-DD`).
- [ ] **Countdown Timer (`[wordle_timer]`)**: A live countdown until the next Wordle hints are released.
- [ ] **"Copy Clues" Button**: A one-click button to copy hints (without the answer) for social sharing.
- [ ] **Dynamic SEO Meta Tags**: Hook into WordPress/Yoast filters to update the page title to "Wordle Hint #[number] - [Date]" dynamically based on the requested date.
- [ ] **Structured Data (Schema)**: Implement `FAQPage` or `HowTo` schema for better rich snippet performance in Google Search.

## 3. Reliability & AI Optimization
- [x] **Pre-emptive Scraping**: Update the scheduler to attempt fetching 3–7 days in advance if the source allows.
- [x] **AI Safety Filter**: Add logic to verify that the generated hints do *not* contain the actual `WORD`.
- [x] **Contextual Prompts**: Refine the `wordle_hint_ai_prompt` to explicitly forbid direct synonyms or mentioning the word length if it's always 5.
- [x] **Advanced Fallback Clues**: Create a local dictionary of static hints for common words as a tertiary fallback.

## 4. Technical Performance & Security
- [ ] **API Rate Limiting**: Implement basic IP-based rate limiting for REST endpoints using WordPress transients.
- [x] **Smart Cache-Busting**: Implement versioned JSON fetching (`?v=YYYY-MM-DD`) based on user local date to prevent stale data while maintaining high performance.
- [ ] **Object Cache Integration**: Utilize `wp_cache_get`/`set` to complement the static JSON cache for even faster API responses.
- [ ] **Telemetry/Alerting**: (Optional) Send an email notification to the site admin if the daily scraper fails after all retries.

## 5. Maintenance & Documentation
- [x] **Update `AGENTS.md`**: Synchronize the roadmap to reflect the PHP/WordPress architecture instead of the original Node.js plan.
- [ ] **Export/Import Utility**: Add a tool to export Wordle data as CSV for backups or migration to other sites.
