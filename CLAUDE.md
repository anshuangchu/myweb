# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A custom PHP CMS/blog system ("myweb") running on XAMPP. Dark-themed, role-based access, with article management, file sharing, PDF viewer/annotation tool, friend links, announcements, AI-powered article assistant, AI site search, Xiaomi MiMo chatbot, social features (friends + private messaging), and user settings with theme customization.

## Tech Stack

- **PHP 8+** (no framework — plain PDO/MySQL)
- **MySQL/MariaDB** via PDO
- **CSS** with custom properties (dark theme, design system v3.0 with semantic tokens)
- **JavaScript** (vanilla — no framework)
- **PDF.js** + **PDF-Lib** for browser-based PDF viewing/annotation/editing

## Development

- **No build step** — PHP files are served directly by Apache via XAMPP
- **No test suite, no linter, no package manager** — raw PHP application
- The `database.php` external config installs a global `set_exception_handler` that shows error details only in debug mode (auto-detected by local IP: `127.0.0.1` or `::1`)
- Changes take effect immediately on page reload

## External Config Files

All sensitive config files are stored **outside the web root** at `../myweb-config/` (relative to `includes/`):

| File | Purpose |
|------|---------|
| `database.php` | Must define a `db()` function returning a PDO instance. See `includes/db_loader.php` for path resolution. |
| `ai_config.php` | Defines `DEEPSEEK_API_KEY`, `DEEPSEEK_MODEL` (default `deepseek-chat`), `DEEPSEEK_BASE_URL` (default `https://api.deepseek.com`) |
| `mimo_config.php` | Defines `MIMO_API_KEY`, `MIMO_MODEL` (default `mimo-v2.5`), `MIMO_BASE_URL` (default `https://api.xiaomimimo.com/v1`). **Must be created manually** — the file does not ship by default. |
| `invite_config.php` | Defines `INVITE_CODE` for registration gating |

Config files are resolved using a cascading `__DIR__`-relative search: `ai_service.php` and `mimo_service.php` try `../../myweb-config` → `../myweb-config` → `../../../myweb-config`; `db_loader.php` tries only `../../myweb-config` → `../../../myweb-config` (skips `../myweb-config`).

## Database Schema

Tables: `articles`, `categories`, `tags`, `article_tags` (many-to-many), `users`, `settings` (key-value), `announcements`, `links`, `login_attempts`, `pages` (legacy, no longer used by frontend), `friends`, `messages`, `user_settings`, `mimo_conversations`, `mimo_messages`. See `database.sql` for full schema.

## Architecture

### Request Flow
1. `includes/db_loader.php` — loads DB config + `helpers.php`, makes `db()` available
2. `includes/header.php` — session start, loads site settings with session cache, output buffering for `<html>` header
3. Page-specific logic queries DB and renders HTML
4. `includes/footer.php` — friend links, footer, conditionally loads AI chat widget (for logged-in users only, unless `settings.ai_enabled` is `'0'`), closes HTML

### Service Layer

Business logic is extracted into service classes under `includes/`:

| Service | File | Responsibility |
|---------|------|----------------|
| `FileService` | `file_service.php` | File scanning, icon/emoji mapping, MIME validation (18 types), safe filename generation, text preview streaming |
| `SearchService` | `search_service.php` | FULLTEXT search (BOOLEAN MODE) with LIKE fallback, Chinese/English tokenization |
| `FriendService` | `social_service.php` | Friend requests (send/accept/reject/remove), user search |
| `MessageService` | `social_service.php` | Private messaging with 30-day auto-cleanup, conversation lists, unread counts |
| `UserService` | `user_service.php` | User settings (display name, bio, avatar upload, theme color presets), password change |
| `AiService` | `ai_service.php` | DeepSeek Chat API wrapper (chat, summarize, polish, expand, generate, search ranking) |
| `MimoService` | `mimo_service.php` | Xiaomi MiMo API wrapper |
| `ArticleService` | `article_service.php` | Article CRUD, pagination, status management |

All services use static methods and parameterized PDO queries. No DI container.

### Key Global Functions

Utility functions are split between `includes/helpers.php` (loaded automatically by `db_loader.php`) and the external config:

**`includes/helpers.php`:**
- `getUploadedFiles($allowed)` — scans `uploads/` for files matching allowed extensions, sorted by mtime desc
- `formatSize($bytes)` — human-readable file size (B/KB/MB)
- `renderPagination($currentPage, $totalPages, $url)` — pagination HTML with smart windowing
- `safeRedirect($url)` — redirect with meta-refresh fallback when headers already sent

**`../myweb-config/database.php` (external config):**
- `db()` — PDO singleton (static variable, `ERRMODE_EXCEPTION`, `FETCH_ASSOC`)
- `isLoggedIn()` / `currentUser()` / `hasRole(...)` — auth helpers
- `redirect($url)` — header redirect + exit
- `getClientIP()` — resolves client IP (supports Cloudflare, X-Forwarded-For)
- `sortField($sort)` — maps sort key to SQL ORDER BY clause (whitelist: `'date'` → `a.created_at DESC`, `'views'` → `a.views DESC`)
- `csrfToken()` / `csrfField()` / `verifyCsrf()` — CSRF token generation and validation
- `deleteForm($action, $key, $value, $label)` — generates POST delete button form
- `isLoginThrottled()` / `recordLoginAttempt()` / `clearLoginAttempts()` — login rate limiting via `login_attempts` DB table (5 attempts per 15 min per IP)

### Role System
Four roles: `super_admin` > `admin` > `editor` > `user`.
- **super_admin**: Full access, including site settings and user role management
- **admin**: CRUD all content, approve/reject articles, manage categories/tags/links/announcements/files/users
- **editor**: Write/edit articles (submitted as "pending" for admin approval)
- **user**: Basic logged-in access (PDF viewer, file browser, friends, messages, settings)

### Front-end Routes
| Path | Description |
|------|-------------|
| `/myweb/` | Home page with articles list (sortable by date/views, filterable by tag/category, paginated) |
| `/myweb/article.php?id=N` | Article detail with prev/next glow-card navigation, AI recommendations, copy-link |
| `/myweb/search.php` | Search across articles (FULLTEXT + LIKE fallback) |
| `/myweb/login.php` | Login with redirect support and throttling |
| `/myweb/register.php` | Invite-code gated registration |
| `/myweb/logout.php` | Session destroy + redirect |
| `/myweb/files.php` | File browser with type filters (image/document/archive/media), grid/list views, search |
| `/myweb/view.php?file=X` | File detail viewer with inline preview (images, PDFs, Office docs, text, video, audio, code) |
| `/myweb/friends.php` | Friend management (search/add/accept/reject/remove) |
| `/myweb/messages.php` | Private messaging (conversation list + chat view) |
| `/myweb/settings.php` | User settings (profile, avatar upload, theme color, password change) |

### AJAX Endpoints
| Path | Description |
|------|-------------|
| `/myweb/ai_chat.php` | Site-wide AI assistant widget (POST). Rate-limited to 20 req/hr per IP. |
| `/myweb/ai_search.php?q=X` | AI-enhanced search (keyword expansion + AI ranking). Rate-limited 20 req/hr/IP. Min 2 chars. |
| `/myweb/ai_recommend.php?id=N` | AI-powered article recommendations. Returns JSON. |
| `/myweb/ai_format.php` | AI HTML formatting (POST, logged-in, CSRF-protected). |
| `/myweb/admin/ai_helper.php` | Admin article editor AI assistant (POST, role-restricted). Actions: `summarize`, `polish`, `expand`, `generate`, `suggest_title`. |
| `/myweb/mimo_chat.php?action=X` | MiMo chatbot with conversation management + agentic tools. |

### Admin Panel

The admin sidebar (`includes/admin_sidebar.php`) groups navigation into two sections:
- **内容管理** (Content): dashboard, articles, files
- **系统设置** (System): categories, links, announcements, users (visible to admin+), site settings (super_admin only)

Note: `admin/tags.php` exists for tag CRUD but is not linked in the sidebar.

### Admin Routes (`/myweb/admin/`)
| Path | Description |
|------|-------------|
| `index.php` | Dashboard with stats |
| `articles.php` | Article list with approve/reject/delete, sort toolbar |
| `article_edit.php` | Article editor (HTML content, tags, category, cover image, status, AI helper) |
| `categories.php` | Category CRUD |
| `tags.php` | Tag CRUD (unlinked in sidebar) |
| `links.php` | Friend link CRUD |
| `announcements.php` | Announcement CRUD |
| `users.php` | User management (role assignment, enable/disable) |
| `settings.php` | Site name/description config (super_admin only) |
| `files.php` | File upload/delete/list with drag-and-drop, MIME validation for all 18 file types |

### Directory Structure
```
myweb/
├── admin/            # Admin panel pages + views/
├── assets/           # PDF.js and PDF-Lib libraries
├── css/              # style.css (dark theme, design tokens v3.0, component styles)
├── includes/         # Services, header/footer, db_loader, helpers, widgets
├── js/               # script.js, editor.js, files.js, search.js
├── uploads/          # User-uploaded files (also serves as media library)
├── database.sql      # Full schema dump (includes FULLTEXT indexes)
├── ai_chat.php       # AI assistant widget endpoint
├── ai_search.php     # AI-enhanced search endpoint
├── ai_recommend.php  # AI article recommendations endpoint
├── ai_format.php     # AI HTML formatting endpoint
├── mimo_chat.php     # MiMo chatbot endpoint
└── CLAUDE.md         # This file
```

### Security Patterns
- All DB queries use prepared statements via PDO (no raw concatenation except LIMIT/OFFSET with integer-cast values)
- Password hashing via `password_hash()` / `password_verify()` with `PASSWORD_DEFAULT`
- Session cookie hardening: `httponly`, `SameSite=Lax`, conditional `secure`
- CSRF protection on all POST forms via token
- Login throttling: tracks attempts in `login_attempts` DB table per IP, 15-min window (5 max)
- Role enforcement at top of each admin page
- File upload validation: extension whitelist + MIME type validation for ALL 18 file types (images, documents, archives, media)
- `basename()` used for delete paths to prevent directory traversal
- Redirect validation ensures redirects stay within `/myweb/`
- Registration gated by invite code defined in external `invite_config.php`

### Key Design Decisions
- Articles support raw HTML content (not Markdown)
- Content editor uses toolbar that inserts HTML tags into a textarea, plus AI formatting (`ai_format.php`)
- View counting is session-scoped (same session doesn't double-count)
- Site settings are loaded once per session and cached in `$_SESSION['settings_cache']`, invalidated by a `_version` counter
- Uploaded files stored on filesystem in `uploads/` (not in DB)
- Friend links displayed in footer on every page
- Announcements shown in a styled bar at the top of every page
- Debug mode auto-detected by local IP (`127.0.0.1`, `::1`)
- Design system uses semantic CSS tokens (`--text-primary`, `--bg-card`, `--border`, etc.) mapped to gray-scale palette
- Card components use `.glow-card` pattern with hover glow effects
- Article navigation uses glow-card components with SVG icons
- Messages auto-cleanup after 30 days retention
