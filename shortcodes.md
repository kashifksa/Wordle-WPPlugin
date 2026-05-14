# Wordle Hint Pro — Shortcode Reference

This document lists all available shortcodes for the Wordle Hint Pro plugin. All shortcodes work on both **Master Hub** and **Client Satellite** sites.

---

### 1. Daily Hint Board
The primary shortcode to display today's (or a specific day's) Wordle hints, stats, and "Reveal" mechanic.
*   **Shortcode**: `[wordle_hints]`
*   **Alternative**: `[wordle-hints]`
*   **Parameters**:
    *   `locale`: (Optional) Defaults to `global`. Use for multi-language support.
*   **Features**: Includes Glassmorphic UI, AJAX date navigation, and Spoiler Guard.

### 2. Historical Archive
Displays a beautiful list of past Wordle puzzles with their answers and links to hints.
*   **Shortcode**: `[wordle_archive]`
*   **Alternative**: `[wordle-archive]`
*   **Features**: Automatically filters out future puzzles (Spoiler-proof). Supports instant AJAX navigation back to the main board.

### 3. Wordle Solver Tool
A premium tool that helps users solve their own Wordle games by filtering possibilities based on their color-coded guesses.
*   **Shortcode**: `[wordle_solver]`
*   **Alternative**: `[wordle-solver]`
*   **Features**: Letter-by-letter validation (Green/Yellow/Gray), 15,000+ word dictionary, and glassmorphic design.

### 4. Countdown Timer
Displays a live countdown timer until the next Wordle puzzle is released (midnight).
*   **Shortcode**: `[wordle_timer]`
*   **Alternative**: `[wordle-timer]`
*   **Logic**: Syncs with the user's local browser time for 100% accuracy.

### 5. Monthly Roundup
Displays a summary list of all Wordle words for the current month. Great for SEO "month-in-review" pages.
*   **Shortcode**: `[wordle_monthly_roundup]`
*   **Behavior**: Lists date, puzzle number, answer, and difficulty level.

### 6. Email Subscription Form
A glassmorphic widget allowing users to subscribe for daily Wordle hint email reminders.
*   **Shortcode**: `[wordle_subscription]`
*   **Features**: AJAX-based submission, instant success validation, and integration with the Daily Reminder cron.

---

## Pro Tip for Multi-Site
If you are running a **Client Satellite** site, these shortcodes will automatically pull data from your local mirrored database (which stays synced with your Master Hub). No extra configuration is needed!
