# AGENTS.md — Wordle Hint Pro (WordPress Architecture)

## 0) Objective

Build a robust WordPress plugin that:

* Fetches Wordle data automatically (daily/pre-emptive scraping)
* Generates unique hints using AI (Groq/Llama integration)
* Computes letter analytics locally
* Stores structured records in the WordPress database
* Serves a clean JSON API and interactive shortcodes for the frontend

Primary goals:

* No hardcoded domains/keys
* Native WordPress integration (Shortcodes, Admin UI, WP Cron)
* High performance via static JSON caching
* Fault-tolerant & observable

---

## 1) High-Level Architecture

Flow:
WP Cron → Scraper → Validator → Analyzer → AI Hint Generator → DB Storage → JSON Cache → Frontend Shortcode

Components:

* **Scheduler**: WordPress Cron (`wp_schedule_event`) with retry logic via transients.
* **Scraper**: PHP-based HTML fetching (`wp_remote_get`) and parsing.
* **Analyzer**: Pure PHP logic for letter/vowel/consonant counts.
* **Hint Generator**: Groq API integration (Llama models) with local pattern fallbacks.
* **Storage**: Custom WP database table (`wp_wordle_data`).
* **Cache**: Static JSON file (`wordle-data.json`) for zero-DB-hit frontend delivery.
* **Admin UI**: Plugin settings page for configuration and manual data entry.

---

## 2) Configuration (WordPress Options)

All values are stored in the WordPress `options` table and manageable via the Admin UI.

Required Settings:

* **API Keys**: Groq AI API Key, Scraper Source URL.
* **Time & Scheduling**: Scrape time (default 03:35 PM PKT), retry intervals.
* **AI Tuning**: Model choice (llama-3.1-8b), custom prompt templates.
* **Security**: API key for restricted manual endpoints (if any).

Rules:

* Use `get_option()` for all configurations.
* Provide defaults for all settings.
* Never hardcode secrets in code.

---

## 3) Scheduler (WP Cron)

Responsibilities:

* Trigger `wordle_daily_scrape` daily.
* Implement "Pre-emptive Scraping": Fetch 3–7 days in advance if available.
* If a day is missing: Attempt retry every 15–30 minutes via a temporary cron schedule.

---

## 4) Scraper

Responsibilities:

* Fetch HTML from configured source.
* Extract: `date`, `puzzle_number`, and `word`.
* Requirements: Respect robots.txt (implied), use realistic User-Agents, and implement request jitter.

---

## 5) Validation & Deduplication

Before Database Insert:

* Validate `puzzle_number` is unique.
* Sanitize all text fields.
* Ensure `word` is a valid 5-letter uppercase string.

---

## 6) Analyzer (Local PHP)

Input: `WORD`

Compute:

* `vowel_count` (A, E, I, O, U)
* `starts_with` (First letter)
* `ends_with` (Last letter)
* `repeated_letters` (Array of duplicates)

---

## 7) Hint Generator (Groq AI)

Input: `WORD`

Output:

* `hint1` (Vague/Thematic)
* `hint2` (Category/Usage)
* `hint3` (Specific/Mechanical)
* `final_hint` (Strong/Conceptual)

Rules:

* **Safety Filter**: Never include the actual word in any hint.
* **Length**: Max 12 words per hint.
* **Fallback**: If AI fails, use "Advanced Fallback Clues" (Pattern-based: "Starts with X", "Ends with Y", etc.).

---

## 8) Data Model

Table: `wp_wordle_data`

Columns:

* `id`: BIGINT(20) PK AUTO_INCREMENT
* `puzzle_date`: DATE
* `puzzle_number`: INT UNIQUE
* `word`: VARCHAR(10)
* `hint1`, `hint2`, `hint3`, `final_hint`: TEXT
* `vowel_count`: INT
* `starts_with`: VARCHAR(1)
* `created_at`: TIMESTAMP

---

## 9) JSON Cache Layer

To ensure < 50ms frontend loads:

* Every DB update triggers a rewrite of `wordle-data.json`.
* The frontend JS fetches this static file directly.
* Fallback: If JSON is missing, the plugin regenerates it on the `init` hook.

---

## 10) Shortcodes & UI

* `[wordle_hint]`: Main interactive UI for daily hints.
* `[wordle_archive]`: (Planned) List of past puzzles.
* `[wordle_timer]`: (Planned) Countdown to next puzzle.

---

## 11) Performance & Security

* **Caching**: Use WordPress Object Cache where available.
* **Security**: Nonces for all admin actions, capability checks (`manage_options`).
* **Rate Limiting**: (Planned) IP-based limiting for manual regeneration triggers.

---

## 12) Testing & Maintenance

* **Dry Run**: Admin button to test scraper/AI without saving.
* **Log Viewer**: View recent scraper/AI activity in the admin dashboard.
* **Export/Import**: Utility to move data between sites.

