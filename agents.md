# AGENTS.md — Wordle Backend App (Scalable)

## 0) Objective

Build a reliable backend service that:

* Fetches Wordle data once daily (safe scraping)
* Generates unique hints (AI-assisted)
* Computes letter analytics locally
* Stores structured records
* Serves a clean API for multiple frontends (WordPress plugin, future apps)

Primary goals:

* No hardcoded domains/keys
* Easy migration (temp → production)
* Multi-site & multi-locale ready
* Fault-tolerant & observable

---

## 1) High-Level Architecture

Flow:
Scheduler → Scraper → Validator → Analyzer → Hint Generator → Storage → API

Components:

* Scheduler (cron + retry)
* Scraper (HTML fetch + parse)
* Analyzer (local logic)
* Hint Generator (AI)
* Storage (MySQL preferred)
* API Server (HTTP/JSON)
* Cache Layer (optional)
* Logger

Separation:

* Backend handles data & API only
* Frontend (WordPress plugin) handles UI & user timezone

---

## 2) Configuration (ENV-FIRST, NO HARDCODING)

All values must be configurable via environment variables or a config file.

Required ENV:

# Server

PORT=3000
API_BASE_URL=
CORS_ORIGIN=*

# Time & Scheduling

TIMEZONE=Asia/Karachi
CRON_SCHEDULE=35 15 * * *     # 03:35 PM PKT
RETRY_INTERVAL_MIN=5
RETRY_MAX_ATTEMPTS=12        # ~1 hour window

# Scraper

SCRAPE_URL=
SCRAPE_TIMEOUT_MS=10000
SCRAPE_USER_AGENT=

# AI (Hints)

AI_API_KEY=
AI_MODEL=
AI_PROMPT_TEMPLATE=

# Security

API_KEY=                     # for protected endpoints

# Database (MySQL)

DB_HOST=
DB_PORT=3306
DB_USER=
DB_PASS=
DB_NAME=
DB_POOL_SIZE=5

# Feature Flags

ENABLE_CACHE=true
ENABLE_FALLBACK_HINTS=true

Rules:

* Never embed domains, keys, or prompts in code
* All endpoints must derive from API_BASE_URL when needed

---

## 3) Scheduler

Responsibilities:

* Trigger job daily at CRON_SCHEDULE (PKT)
* If job fails → retry every RETRY_INTERVAL_MIN
* Stop retry after success or RETRY_MAX_ATTEMPTS

Pseudo:
run_job():
if latest_puzzle_exists_for_today():
log("Already exists → skip")
return

attempt = 0
while attempt < RETRY_MAX_ATTEMPTS:
result = fetch_and_process()
if result.success:
log("Success")
break
sleep(RETRY_INTERVAL_MIN)
attempt++

Observability:

* Log each attempt with timestamp
* Emit success/failure metrics

---

## 4) Scraper

Responsibilities:

* Fetch HTML from SCRAPE_URL
* Extract:

  * date (ISO)
  * puzzle_number (INT)
  * word (UPPERCASE)

Requirements:

* Set headers (User-Agent from config)
* Respect timeouts
* Add 2–5s jitter delay before request
* Max 1–3 requests/day

Failure Handling:

* Throw retriable error on network/parse failure
* Validate extracted fields strictly

---

## 5) Validation & Deduplication

Before insert:

* Ensure all fields present
* Ensure puzzle_number is integer and valid
* Check DB for existing puzzle_number

Rule:

* If exists → skip (idempotent)

DB Constraint:

* UNIQUE(puzzle_number)

---

## 6) Analyzer (Local Only, No API)

Input: WORD

Compute:

* letter_count
* vowel_count (A,E,I,O,U)
* consonant_count
* repeated_letters (array or comma string)
* first_letter

Implementation:

* Pure function
* Deterministic
* Unit-testable

---

## 7) Hint Generator (AI)

Input:

* WORD only

Output:

* hint1 (vague)
* hint2 (category)
* hint3 (specific)
* final_hint (strong)

Rules:

* No direct reveal
* No copying from sources
* Each hint ≤ 12 words
* Simple English

Prompt Template:

* Stored in AI_PROMPT_TEMPLATE (config)
* Uses {{WORD}} placeholder

Fallback:
If AI fails or times out:

* Generate basic hints locally (pattern-based)

---

## 8) Data Model

Table: wordle_data

Columns:

* id INT PK AUTO_INCREMENT
* date DATE (indexed)
* puzzle_number INT UNIQUE (indexed)
* word VARCHAR(10)
* hint1 TEXT
* hint2 TEXT
* hint3 TEXT
* final_hint TEXT
* vowel_count INT
* repeated_letters VARCHAR(32)
* locale VARCHAR(16) DEFAULT 'global'
* created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

Indexes:

* UNIQUE(puzzle_number)
* INDEX(date)
* INDEX(locale, date)

---

## 9) Storage Layer

Preferred: MySQL (connection pool)

Responsibilities:

* insert(record)
* exists(puzzle_number)
* fetch_latest(limit, locale)
* fetch_by_date(date, locale)

Rules:

* Use parameterized queries
* Handle connection retries
* Keep queries minimal

Dev Mode:

* JSON file allowed (feature-flagged)

---

## 10) API Design

Base: /api/wordle

### GET /api/wordle

Query (optional):

* locale=global

Response:
{
"today": {...},
"yesterday": {...},
"tomorrow": {...}
}

Behavior:

* Fetch latest 2–3 records by date
* Map to today/yesterday/tomorrow (server-side ordering only)
* Keep payload small

Cache:

* Cache response (e.g., 60–300s) if ENABLE_CACHE=true

---

### GET /api/wordle/all

Response:

* List of all records (paginated)

Query:

* page, limit, locale

---

### POST /api/wordle/save (optional/manual)

Headers:
Authorization: Bearer {API_KEY}

Body:

* date
* puzzle_number
* word
* hints[]
* vowel_count
* repeated_letters
* locale

Behavior:

* Validate + sanitize
* Check duplicate
* Insert if new

---

## 11) Time Handling

* Store dates in ISO (UTC-safe)
* Do NOT implement user timezone logic here
* Frontend/plugin decides which day to show

---

## 12) Security

* Protect POST routes with API_KEY
* Sanitize all inputs
* Escape outputs
* Basic rate limiting (per IP)
* Do not expose secrets in responses

---

## 13) CORS

* Allow CORS_ORIGIN
* Default: *
* Production: restrict to site domains

---

## 14) Caching (Optional but Recommended)

* In-memory cache for GET /api/wordle
* TTL: 60–300 seconds
* Invalidate on new insert

---

## 15) Logging & Monitoring

Log:

* job start/end
* fetch success/failure
* retries
* AI success/failure
* DB insert/skip
* API hits/errors

Format:

* timestamp + level + message

Optional:

* expose /health endpoint

---

## 16) Error Handling

* Distinguish retriable vs fatal errors
* Retry only retriable (network/parse)
* Graceful degradation if AI fails
* API always returns valid JSON

---

## 17) Performance

* Target API response < 100ms (cached)
* Use DB indexes
* Limit fields in responses
* Avoid N+1 queries

---

## 18) Deployment

Runtime:

* Node.js (Express or Fastify)

Environment:

* Hostinger Node (temporary domain)
* Support .env config

Startup:

* Initialize DB pool
* Register routes
* Start scheduler

---

## 19) Migration Strategy

To move temp → production:

Change ONLY:

* API_BASE_URL
* CORS_ORIGIN
* (optional) DB credentials

No code changes.

---

## 20) Scalability Roadmap

Design for:

* multiple frontends (WP sites per country)
* locale-based data (locale column)
* future central API domain (api.yourdomain.com)
* mobile apps

Rule:

* Backend = single source of truth

---

## 21) Testing

Must include:

* unit tests (analyzer)
* integration tests (API)
* dry-run mode for scraper

---

## 22) Do NOT

* Do NOT scrape multiple times/day
* Do NOT store data in WP pages
* Do NOT hardcode URLs/keys/prompts
* Do NOT mix frontend logic here
* Do NOT rely solely on AI

---

## 23) Success Criteria

* Runs daily without manual intervention
* No duplicate entries
* API returns correct structured data
* Works after domain change without edits
* Supports multiple sites via same backend
