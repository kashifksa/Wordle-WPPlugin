# Wordle Hint Pro — Multi-Site Architecture (v2)

## 0) Objective
Maintain a high-performance WordPress network that synchronizes Wordle data across multiple domains while ensuring unique, SEO-optimized content on every site.

## 1) High-Level Architecture
*   **Master Hub**: Scrapes NYT, enriches with MW Dictionary, generates primary hints, and broadcasts via secure REST API.
*   **Client Satellite**: Fetches raw data from Master Hub, generates **local unique hints** using site-specific AI personas, and maintains a local archive mirror.

## 2) Components & Features
*   **Secure API Sharing**: Master Hub uses `X-Wordle-Key` and CORS headers for secure data broadcasting.
*   **Redundant AI**: Primary (Groq) and Fallback (Google Gemini) integration for 100% uptime in hint generation.
*   **Unique Personas**: Every site can define a "System Persona" (Riddler, Coach, etc.) in settings to differentiate content for SEO.
*   **Spoiler Guard**: Future puzzles are synced in advance but locked behind a glassmorphic overlay until midnight in the user's local timezone.
*   **Audit System**: Dedicated "System Logs" dashboard to track network health, sync success, and AI failures.

## 3) Configuration (WP Options)
*   `wordle_operation_mode`: Toggles between `master` and `client`.
*   `wordle_network_sharing_key`: Secret key for data broadcast.
*   `wordle_master_api_url`: Hub endpoint for satellites.
*   `wordle_hint_ai_system_prompt`: Custom AI personality prompt.

## 4) Rules for Development
*   **Persistence**: Never delete historical puzzles; maintain a "Forever Archive."
*   **Performance**: Use `wordle-data.json` static cache for zero-DB-hit frontend loads.
*   **Security**: Always use Nonces and Capability checks for admin actions.
*   **Aesthetics**: Glassmorphism and premium Lucide icons are non-negotiable for the UI.
