# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A custom PHP CMS/blog system ("myweb") running on XAMPP. Dark-themed, role-based access, with article management, static pages (called "资料"), file sharing, PDF viewer/annotation tool, friend links, announcements, AI-powered article assistant, AI site search, and a Xiaomi MiMo chatbot.

## Tech Stack

- **PHP 8+** (no framework — plain PDO/MySQL)
- **MySQL/MariaDB** via PDO
- **CSS** with custom properties (dark theme, no framework)
- **JavaScript** (vanilla — no framework)
- **PDF.js** + **PDF-Lib** for browser-based PDF viewing/annotation/editing

## External Config Files

All sensitive config files are stored **outside the web root** at `../myweb-config/` (relative to `includes/`):

| File | Purpose |
|------|---------|
| `database.php` | Must define a `db()` function returning a PDO instance. See `includes/db_loader.php:5-10` for path resolution. |
| `ai_config.php` | Defines `DEEPSEEK_API_KEY`, `DEEPSEEK_MODEL` (default `deepseek-chat`), `DEEPSEEK_BASE_URL` (default `https://api.deepseek.com`) |
| `mimo_config.php` | Defines `MIMO_API_KEY`, `MIMO_MODEL` (default `mimo-v2.5`), `MIMO_BASE_URL` (default `https://api.xiaomimimo.com/v1`) |

All three config files are resolved using the same `__DIR__`-relative search pattern (tries `../../myweb-config`, `../myweb-config`, `../../../myweb-config`).

## Database Schema

Tables: `articles`, `categories`, `tags`, `article_tags` (many-to-many), `users`, `settings` (key-value), `announcements`, `links`, `pages`, `mimo_conversations`, `mimo_messages`. See `database.sql` for full schema.

## Architecture

### Request Flow
1. `includes/db_loader.php` — loads DB config, establishes PDO connection (via `db()` function)
2. `includes/header.php` — session start, loads site settings with session cache, output buffering for `<html>` header
3. Page-specific logic queries DB and renders HTML
4. `includes/footer.php` — friend links, footer, closes HTML

### Key Global Functions (in `includes/helpers.php`)
- `getPdfFiles()` — scans `uploads/` for `.pdf` files sorted by modification time

### Key Functions (defined in `includes/header.php` or inline)
- `db()` — returns PDO instance (defined in external config)
- `isLoggedIn()` — checks `$_SESSION['user_id']`
- `hasRole(...)` — checks current user's role against allowed roles
- `currentUser()` — returns current user row from DB
- `csrfField()` / `verifyCsrf()` — CSRF token generation and validation
- `redirect($url)` — header redirect + exit
- `deleteForm($action, $name, $value, $label)` — generates POST delete button form
- `sortField($sort)` — maps sort key to SQL ORDER BY clause
- `isLoginThrottled()` / `recordLoginAttempt()` / `clearLoginAttempts()` — login rate limiting via session

### Role System
Four roles: `super_admin` > `admin` > `editor` > `user`.
- **super_admin**: Full access, including site settings and user role management
- **admin**: CRUD all content, approve/reject articles, manage categories/tags/links/announcements/files
- **editor**: Write/edit articles (submitted as "pending" for admin approval), manage pages
- **user**: Basic logged-in access (PDF viewer, registered user)

### AI Features (AiService)

A unified `AiService` class (`includes/ai_service.php`) wraps the DeepSeek Chat API (OpenAI-compatible) via cURL, with these capabilities:

| Method | Purpose |
|--------|---------|
| `chat()` | Base chat method with configurable max_tokens/temperature |
| `summarize()` | Generate article summary (50-100 chars) |
| `polish()` | Proofread and polish article text |
| `expand()` | Continue writing article content with configurable length |
| `generateArticle()` | Full article generation from topic (+ style: general/formal/casual/tech) |
| `suggestTitle()` | Recommend 3-5 titles for existing content |
| `expandSearchQuery()` | Expand a natural-language query into search keywords |
| `rankResults()` | AI-rank search results by relevance |
| `recommendFromCandidates()` | Select most relevant articles from candidates |
| `chatWithContext()` | Q&A over provided website content |

Three AJAX endpoints consume this service:

- **`ai_chat.php`** — Site-wide AI assistant widget. POST endpoint: extracts keywords → searches articles/pages → builds context → answers user questions. Rate-limited to 20 req/hr per IP.
- **`ai_search.php`** — AI-enhanced search (`GET ?q=`). Expands query via AI → searches articles + pages → AI-ranks results. Cached in session for 5 minutes.
- **`ai_recommend.php`** — Article recommendations (`GET ?id=N`). Finds same-category articles → AI-ranks → falls back to chronological order on AI failure. Returns JSON.
- **`admin/ai_helper.php`** — Admin article editor AI assistant (POST only). Restricted to `super_admin`/`admin`/`editor` roles. Actions: `summarize`, `polish`, `expand`, `generate`, `suggest_title`.

### MiMo Chat (Xiaomi MiMo)

A chatbot powered by the Xiaomi MiMo API (`includes/mimo_service.php`), OpenAI-compatible, supporting multiple models:

| Model | Description |
|-------|-------------|
| `mimo-v2.5` | Native full-modal (default) |
| `mimo-v2.5-pro` | Flagship |
| `mimo-v2-pro` | 1M context text-only |
| `mimo-v2-omni` | Multi-modal (text/image/audio/video) |

The widget (`includes/mimo_widget.php`) is a floating chat panel with conversation management, model selection, and new conversation support.

`mimo_chat.php` handles all AJAX operations:

| Action | Method | Description |
|--------|--------|-------------|
| `send` | POST | Send message, get AI reply. Parses JSON tool commands from AI response |
| `list` | GET | List user's conversations (by user_id or session_id) |
| `load` | GET | Load messages from a conversation |
| `new` | POST | Create new conversation |
| `delete` | POST | Delete conversation + messages |
| `rename` | POST | Rename conversation |

**Agentic tools** — the MiMo AI can output JSON tool commands inline, which `mimo_chat.php` detects, executes, and feeds back for a final reply:

- `search_articles` — keyword search across published articles
- `get_article` — fetch single article by ID (with images extraction)
- `browse_page` — fetch page HTML via cURL to localhost, strip to text
- `navigate_to` — return a URL for the front-end to navigate to
- `list_articles` — recent articles list (optionally by category)

Conversations are stored in `mimo_conversations` and `mimo_messages` tables, identified by `user_id` (logged in) or `session_id` (anonymous).

### Front-end Routes
| Path | Description |
|------|-------------|
| `/myweb/` | Home page with articles list (sortable by date/views, filterable by tag/category, paginated) |
| `/myweb/article.php?id=N` | Article detail with prev/next navigation, view counting (once per session) |
| `/myweb/pages.php` | "资料" page with tabs for documents and shared files |
| `/myweb/page.php?slug=X` | Static page detail |
| `/myweb/search.php` | Search across articles and pages |
| `/myweb/login.php` | Login with redirect support and throttling |
| `/myweb/register.php` | Invite-code gated registration |
| `/myweb/logout.php` | Session destroy + redirect |
| `/myweb/ai_chat.php` | POST endpoint for site-wide AI assistant widget |
| `/myweb/ai_search.php?q=X` | AJAX — AI-enhanced search with keyword expansion and AI ranking |
| `/myweb/ai_recommend.php?id=N` | AJAX — AI-powered article recommendations |
| `/myweb/mimo_chat.php?action=X` | AJAX — MiMo chatbot with conversation management and tool execution |

### Admin Routes (`/myweb/admin/`)
| Path | Description |
|------|-------------|
| `index.php` | Dashboard with stats |
| `articles.php` | Article list with approve/reject/delete |
| `article_edit.php` | Article editor (HTML content, tags, category, cover image, status) |
| `categories.php` | Category CRUD |
| `tags.php` | Tag CRUD |
| `pages.php` / `page_edit.php` | Static page CRUD |
| `links.php` | Friend link CRUD |
| `announcements.php` | Announcement CRUD |
| `users.php` | User management (role assignment, enable/disable) |
| `settings.php` | Site name/description config (super_admin only) |
| `files.php` | File upload/delete/list with drag-and-drop |
| `ai_helper.php` | AI article assistant AJAX endpoint (summarize/polish/expand/generate/suggest_title) |

### Tools (`/myweb/tools/`)
| Path | Description |
|------|-------------|
| `pdf.php` | Browser-based PDF viewer with annotation, rotation, download (admin: save back to server) |
| `pdf_save.php` | Save edited PDF back to server (admin only, JSON response) |
| `pdf_delete.php` | Delete a PDF file (JSON response) |
| `pdf_list.php` | Return PDF file list HTML fragment |
| `upload_save.php` | Upload a PDF file (JSON response) |

### Directory Structure
```
myweb/
├── admin/          # Admin panel pages
├── assets/         # PDF.js and PDF-Lib libraries
├── css/            # style.css (global dark theme via CSS custom properties)
├── includes/       # header.php, footer.php, db_loader.php, helpers.php, admin_sidebar.php,
│                   # ai_service.php (DeepSeek), mimo_service.php (Xiaomi MiMo),
│                   # chat_widget.php (AI chat floating widget),
│                   # mimo_widget.php (MiMo chat floating widget)
├── js/             # script.js (nav toggle, animations, content editor toolbar)
├── tools/          # PDF tool pages
├── uploads/        # User-uploaded files and PDFs (also serves as media library)
├── database.sql    # Full schema dump (includes FULLTEXT indexes)
├── ai_chat.php      # Site-wide AI assistant widget AJAX endpoint
├── ai_search.php    # AI-enhanced search (keyword expansion + AI ranking)
├── ai_recommend.php # AI-powered article recommendations
├── mimo_chat.php    # MiMo chatbot with conversation management + agentic tools
└── CLAUDE.md       # This file
```

### Security Patterns
- All DB queries use prepared statements via PDO (no raw concatenation except LIMIT/OFFSET with integer-cast values)
- Password hashing via `password_hash()` / `password_verify()` with `PASSWORD_DEFAULT`
- Session cookie hardening: `httponly`, `SameSite=Lax`, conditional `secure`
- CSRF protection on all POST forms via token
- Login throttling: tracks attempts in session, 15-minute cooldown
- Role enforcement at top of each admin page
- File upload validation: extension whitelist, MIME type checking (images), size limits
- basename() used for delete paths to prevent directory traversal
- Redirect validation ensures redirects stay within `/myweb/`
- Registration gated by hardcoded invite code

### Key Design Decisions
- Articles and pages support raw HTML content (not Markdown)
- Content editor uses a simple toolbar that inserts HTML tags into a textarea
- View counting is session-scoped (same session doesn't double-count)
- Site settings are loaded once per session and cached in `$_SESSION['settings_cache']`
- PDF files stored on filesystem in `uploads/` (not in DB)
- Friend links displayed in footer on every page
- Announcements shown in a styled bar at the top of every page
- Debug mode auto-detected by local IP (`127.0.0.1`, `::1`) for potential dev-only features
