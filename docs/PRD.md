# CutContour AI Generator — Product Requirements Document

**Version:** 1.3  
**Status:** Build Ready  
**Author:** Michael Agbozo  
**Date:** 2026-04-18

---

## Table of Contents

1. [Product Overview](#1-product-overview)
2. [Problem Statement](#2-problem-statement)
3. [Solution](#3-solution)
4. [User Roles & Capabilities](#4-user-roles--capabilities)
5. [User Flow](#5-user-flow)
6. [Supported Inputs](#6-supported-inputs)
7. [Processing Pipeline](#7-processing-pipeline)
8. [Output Specification](#8-output-specification)
9. [AI Integration](#9-ai-integration)
10. [System Architecture](#10-system-architecture)
11. [Authentication](#11-authentication)
12. [Storage & Retention](#12-storage--retention)
13. [Database Schema](#13-database-schema)
14. [UI Requirements](#14-ui-requirements)
15. [Error Handling](#15-error-handling)
16. [Observability & Logging](#16-observability--logging)
17. [Performance Requirements](#17-performance-requirements)
18. [Deployment](#18-deployment)
19. [Success Criteria](#19-success-criteria)
20. [Known Limitations](#20-known-limitations)
21. [Future Roadmap](#21-future-roadmap)
22. [Notifications & Download System](#22-notifications--download-system)
23. [Admin Section](#23-admin-section)
24. [Permission & Authorization System](#24-permission--authorization-system)
25. [AI SDK Integration (Implemented)](#25-ai-sdk-integration-implemented)
26. [Security & Quality Fixes (v1.3)](#26-security--quality-fixes-v13)
27. [Usage Quotas & Failed Job Retention (v1.3)](#27-usage-quotas--failed-job-retention-v13)

---

## 1. Product Overview

CutContour AI Generator is a Laravel SaaS tool that converts design files into print-ready PDFs with accurate vector cut paths, using the industry-standard **CutContour** spot color layer compatible with RIP software, Adobe Illustrator, and CorelDRAW.

The system uses a **hybrid pipeline**:

| Component | Technology | Role |
|---|---|---|
| Subject detection | Laravel AI SDK | Enhance accuracy on complex images |
| Vectorization | Potrace | Raster mask → clean vector paths |
| Image processing | ImageMagick | Preprocessing, format normalization |
| PDF export | Custom PHP service | Layer-accurate PDF assembly |

**MVP Scope:** Single-user upload → automated processing → download. No manual editing, no batch mode, no API.

---

## 2. Problem Statement

Print shops waste significant time and introduce errors through:

- Manual cut path creation in Illustrator or CorelDRAW
- Inconsistent raster-to-vector conversion from generic tools
- Compression artifacts and rasterized cut paths that fail in RIP software
- Re-work cycles when files arrive without cut paths

The bottleneck is not design skill — it is the absence of an automated, reliable pipeline that outputs production-ready files.

---

## 3. Solution

> Upload file → AI-assisted subject isolation → deterministic vectorization → CutContour layer → download PDF.

Fully automated. No design tools. No manual steps. The user only uploads and downloads.

**Core design principle:** AI is an optional *quality enhancer*, not the primary engine. Deterministic vectorization always runs; AI improves input quality when confidence is low.

---

## 4. User Roles & Capabilities

### Standard User

| Capability | Notes |
|---|---|
| Upload files | JPG, JPEG, PNG, SVG, PDF, AI |
| Track job status | Real-time polling on the single-screen UI |
| Preview cutline overlay | Non-editable, visual confirmation only |
| Download output PDF | Available until expiry (90 days) |
| View job history | All past jobs with status and expiry date |

### System Admin (internal)

| Capability | Notes |
|---|---|
| Admin dashboard | Real-time stats: total jobs, AI usage rate, failure rate, avg processing time |
| View all jobs | Search, filter by status/AI usage, sort, paginated (20/page) |
| Inspect failed jobs | Full error_message, expandable details, retry or delete actions |
| Manage users | Search, sort, toggle admin role, view 2FA status, job counts |
| System health | Pipeline binary checks, queue status, storage cleanup, config display |
| Monitor pipeline health | Error rate, memory spikes, slow jobs |
| Role-based login redirect | Admins → `/admin`, users → `/dashboard` |

---

## 5. User Flow

```
[Login] 
    ↓
[Upload Screen]
    - Drag & drop or file picker
    - Validation: format + file size
    ↓
[Processing] (server-side, async)
    - Path decision: Fast or AI-Enhanced
    - Status polling shown to user
    ↓
[Preview]
    - Original artwork thumbnail
    - CutContour overlay (semi-transparent)
    ↓
[Download]
    - Single button → PDF download
    - File retained for 90 days
```

Edge cases handled in flow:
- Upload validation failure → inline error, no job created
- Processing failure → job marked `failed`, user sees actionable message
- Expired job → download button disabled, expiry notice shown

---

## 6. Supported Inputs

### Accepted Formats

| Format | Notes |
|---|---|
| JPG / JPEG | Standard lossy raster |
| PNG | Preferred — lossless, supports transparency |
| SVG | Passed through with vector-aware preprocessing |
| PDF | Flattened and re-rendered before processing |
| AI (Adobe Illustrator) | Converted via ImageMagick pipeline |

### Constraints

| Parameter | Limit |
|---|---|
| Max file size | 100 MB |
| Min resolution (raster) | 72 DPI (system normalizes upward) |
| Max dimensions | No hard limit; memory-bound at ~10,000 × 10,000px |

---

## 7. Processing Pipeline

### Path Decision Logic

```
Upload received
    ↓
Preprocessing (ImageMagick)
    ↓
Confidence check
    ├── HIGH confidence → Fast Path
    └── LOW confidence OR complex image → AI-Enhanced Path
```

Confidence is determined by:
- Edge clarity (contrast ratio of detected edges)
- Background complexity (number of distinct color regions)
- Format type (SVG/clean PNG → fast; JPEG with busy backgrounds → AI)

---

### 7A. Fast Path (No AI)

Used when the image is clean and edges are well-defined.

```
1. Upload
2. ImageMagick preprocessing
   - Normalize format (convert to PNG)
   - Cap at 10,000 × 10,000 px (preserve ratio)
   - Resize to user-specified target dimensions (W × H) with centre-aligned
     letterbox padding (white for opaque, transparent for alpha-channel images)
   - Remove ICC profile quirks
3. Subject mask generation (subject contour, not bounding box)
   - PNG with alpha channel → extract alpha channel directly (pixel-perfect boundary)
   - Opaque images (JPEG etc.) → Canny edge detection + morphological close +
     multi-corner flood-fill to isolate subject; small dilation fills edge seam
4. Contour offset → morphological dilation by user-specified offset (px)
5. Potrace vectorization → SVG paths
6. CutContour layer generation
7. PDF assembly and export
```

---

### 7B. AI-Enhanced Path (Laravel AI SDK)

Used when confidence is low, background is complex, or subject isolation is uncertain.

```
1. Upload
2. ImageMagick preprocessing (same as Fast Path)
3. AI Analysis (Laravel AI SDK)
   - Send image with instruction prompt
   - Receive: segmentation mask, simplified SVG path, or boundary description
4. AI output normalization
   - mask → binary PNG → Potrace
   - SVG path → normalize → CutContour path
   - boundary description → regenerate mask via ImageMagick
5. Potrace vectorization
6. CutContour layer generation
7. PDF assembly and export
```

**Fallback Rule:** If the AI call fails (timeout, API error, unexpected output), the system automatically falls back to the Fast Path using edge detection. The job does not fail — it degrades gracefully.

---

### 7C. Service Responsibilities

| Service | Responsibility |
|---|---|
| `AIService` | Call Laravel AI SDK, parse response, normalize output |
| `ImageProcessingService` | All ImageMagick operations (preprocessing, masking) |
| `VectorizationService` | Potrace execution, SVG path cleanup |
| `PdfService` | Assemble final layered PDF with CutContour spot color |
| `ConfidenceService` | Decide pipeline path (Fast vs. AI-Enhanced) |

---

## 8. Output Specification

### File Details

| Property | Value |
|---|---|
| Format | PDF only |
| Filename | `{original_name}_{width}x{height}.pdf` |
| Dimensions | Width and height in the filename are the user-specified target dimensions (in pixels at 96 px/in); the artwork is scaled to fit and padded to these exact dimensions |

### PDF Layer Structure

| Layer | Content |
|---|---|
| Layer 1 (bottom) | Original artwork at full resolution |
| Layer 2 (top) | CutContour vector path — pure spot color, no rasterization |

### CutContour Spot Color

| Property | Value |
|---|---|
| Spot color name | `CutContour` |
| CMYK values | `0, 100, 0, 0` |
| Hex reference | `#ec008c` (visual approximation only — not used in output) |
| Overprint | Off |

### Quality Requirements

- Minimum output equivalent: **300 DPI**
- Cut path: **fully vector** — never rasterized at any stage
- No JPEG compression artifacts introduced post-upload
- No downsampling of source artwork
- Geometry clean at all zoom levels (no aliased paths)

### Compatibility Targets

| Software | Must Open Correctly |
|---|---|
| Adobe Illustrator (CC+) | Yes |
| CorelDRAW (X7+) | Yes |
| RIP software (EFI, Caldera, etc.) | Yes |

---

## 9. AI Integration

### Purpose

Improve subject isolation on complex images before vectorization. AI does not generate the final cut path — Potrace does. AI improves the *input* to Potrace.

### Model

`gemini-2.0-flash` via Laravel AI SDK Agent pattern (configurable via `#[Provider]` and `#[Model]` attributes on the agent class). Multi-provider support via `config/ai.php` — can use OpenAI, Anthropic, Gemini, or any supported provider.

### Request Structure

The AI integration uses the Laravel AI SDK Agent pattern with structured output:

```php
use App\Ai\Agents\SubjectIsolationAgent;
use Prism\Prism\ValueObjects\Media\Image;

$response = (new SubjectIsolationAgent)->prompt(
    'Extract the main subject from this image for die-cut path generation.',
    [Image::fromLocalPath($preprocessedPath)],
);

$svgPathData = $response['svg_path'];   // SVG path d attribute
$confidence  = $response['confidence'];  // 0.0–1.0
```

### Agent Architecture

`SubjectIsolationAgent` implements `Agent` + `HasStructuredOutput`:
- Returns structured JSON with `svg_path` (SVG path `d` attribute) and `confidence` (0–1)
- Uses `#[Provider(Lab::Gemini)]` and `#[Model('gemini-2.0-flash')]` attributes
- 45-second timeout
- Provider failover supported: `provider: [Lab::Gemini, Lab::OpenAI]`

### Expected AI Output

Structured JSON with two fields:

1. **`svg_path`** (string) — simplified SVG path `d` attribute outlining the subject boundary
2. **`confidence`** (float, 0–1) — model's confidence in the isolation quality

### Fallback Behavior

| Condition | System Response |
|---|---|
| AI API timeout | Fall back to Fast Path; log `ai_fallback = true` |
| Malformed AI response | Fall back to Fast Path; log response for review |
| AI returns empty output | Fall back to Fast Path |
| AI service unavailable | Fall back to Fast Path; alert admin if sustained |

---

## 10. System Architecture

### Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 (monolith) |
| PHP | 8.5 |
| Frontend | Livewire 4 SFC + Flux UI (free) + Alpine.js + Tailwind CSS 4 |
| Image processing | ImageMagick |
| Vectorization | Potrace |
| AI | Laravel AI SDK (`laravel/ai`) — Agent pattern with Gemini 2.0 Flash |
| Storage | Local filesystem (VPS) |
| Queue | Sync (MVP) — Redis + workers in v2 |
| Auth | Laravel Fortify (email + password + 2FA) |
| Testing | Pest 4 |

### Request Lifecycle

```
HTTP Request (file upload)
    ↓
UploadController
    ↓ creates CutJob record (status: processing)
    ↓ dispatches ProcessCutJob (sync in MVP)
ProcessCutJob
    ↓
ConfidenceService → decides path
    ↓
ImageProcessingService (preprocess)
    ↓ [AI path only]
AIService → normalize output
    ↓
VectorizationService (Potrace)
    ↓
PdfService (assemble + export)
    ↓
Update CutJob (status: completed, output_path)
    ↓
HTTP Response → polling detects completion → UI updates
```

---

## 11. Authentication

### Method

Email + password via **Laravel Fortify**.

### Session

Standard Laravel session-based auth (no API tokens in MVP).

### Requirements

| Feature | Required |
|---|---|
| Login | Yes |
| Registration | Yes |
| Password reset | Yes |
| Email verification | Optional (configurable) |
| 2FA (TOTP) | Yes — via Fortify (QR code setup, recovery codes) |
| Social login | No |

### Authorization

All `CutJob` records are scoped to the authenticated user. Users cannot access other users' jobs or files (enforced at controller and policy level).

---

## 12. Storage & Retention

### Structure

```
storage/app/
    users/{user_id}/
        jobs/{job_id}/
            original.{ext}
            output.pdf
```

### Retention Policy

| Event | Action |
|---|---|
| Job created | Files stored under user path |
| 90 days elapsed (completed jobs) | Original + output deleted from disk |
| 3 hours elapsed (failed jobs) | Files and record cleaned up aggressively |
| After deletion | `cut_jobs.status` → `expired`; paths nulled |
| User deletes account | All files purged immediately |

### Cleanup

A **daily scheduled job** (`CleanupExpiredJobs`) runs at 02:00 and:

1. **Completed/processing jobs:** Queries `cut_jobs` where `expires_at < now()` and `status != expired` → deletes files, marks `expired`
2. **Failed jobs:** Queries `cut_jobs` where `status = failed` and `created_at < now() - failed_retention_hours` → deletes files, marks `expired`

| Config Key | Env Variable | Default | Purpose |
|---|---|---|---|
| `cutjob.retention_days` | `CUTJOB_RETENTION_DAYS` | 90 | Retention window for completed jobs |
| `cutjob.failed_retention_hours` | `CUTJOB_FAILED_RETENTION_HOURS` | 3 | Retention window for failed jobs |

---

## 13. Database Schema

### `users`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` / `bigint` | Primary key |
| `name` | `string` | |
| `email` | `string` | Unique |
| `password` | `string` | Hashed |
| `email_verified_at` | `timestamp` | Nullable |
| `remember_token` | `string` | Nullable |
| `timestamps` | | |

### `cut_jobs`

| Column | Type | Notes |
|---|---|---|
| `id` | `ulid` / `bigint` | Primary key |
| `user_id` | `foreignId` | Cascades on delete |
| `original_name` | `string` | Original filename |
| `file_path` | `string` | Relative storage path |
| `output_path` | `string` | Nullable until complete |
| `file_type` | `string` | `jpg`, `png`, `pdf`, `svg`, `ai` |
| `width` | `unsignedInteger` | Pixels |
| `height` | `unsignedInteger` | Pixels |
| `status` | `enum` | `pending`, `processing`, `completed`, `failed`, `expired` |
| `ai_used` | `boolean` | Whether AI path was taken |
| `confidence_score` | `float` | Nullable — output of ConfidenceService |
| `processing_duration_ms` | `unsignedInteger` | Nullable |
| `error_message` | `text` | Nullable |
| `expires_at` | `timestamp` | Set on creation (now + 90 days) |
| `timestamps` | | |

---

## 14. UI Requirements

### Layout (Single Screen — Post-Login)

```
┌──────────────────────────────────────┐
│  [Upload Zone]                        │
│  Drag & drop or click to upload       │
│  JPG, PNG, SVG, PDF, AI — max 100MB  │
├──────────────────────────────────────┤
│  [Processing Status]                  │
│  Spinner + step label (optional)      │
├──────────────────────────────────────┤
│  [Preview]                            │
│  - Artwork thumbnail                  │
│  - CutContour overlay (semi-opaque)  │
├──────────────────────────────────────┤
│  [Download Button]   [Metadata]       │
│                      filename         │
│                      dimensions       │
│                      processing time  │
└──────────────────────────────────────┘
```

**Job History** — accessible below the main screen. Lists past jobs with: filename, status badge, expiry date, re-download link.

### Hard Constraints

- No editing tools, brushes, or path manipulation
- No multi-step wizard
- No canvas interactions
- Preview is read-only overlay — not interactive

---

## 15. Error Handling

### User-Facing Messages

| Condition | Message |
|---|---|
| File too large | "File exceeds 100MB limit. Please reduce the file size and try again." |
| Unsupported format | "Unsupported file type. Please upload a JPG, PNG, SVG, PDF, or AI file." |
| Processing failed | "Processing failed. This may be due to file complexity. Try a simpler or higher-contrast version." |
| Download expired | "This file has expired and is no longer available." |
| AI degraded (transparent) | No message to user — system handles silently |

### System Behavior

| Condition | Behavior |
|---|---|
| AI timeout / failure | Silently fall back to Fast Path; log `ai_fallback = true` |
| Potrace crash | Mark job `failed`; log stack trace |
| Memory spike (large file) | Log warning; allow job to continue unless OOM |
| Disk full | Fail job with internal alert; surface generic error to user |
| Invalid PDF structure | Log and mark `failed`; do not serve broken output |

---

## 16. Observability & Logging

Every `CutJob` records:

| Field | Purpose |
|---|---|
| `ai_used` | Measure AI path usage rate |
| `confidence_score` | Tune path-decision thresholds |
| `processing_duration_ms` | Track performance regressions |
| `error_message` | Diagnose failures without digging through logs |

**Laravel Log entries** (structured, per job):
- Start/end timestamps
- File type and dimensions
- Path taken (fast vs. AI)
- AI fallback flag
- Potrace execution result
- Final PDF size

Admin can filter by job ID, user ID, and status via the internal log view.

---

## 17. Performance Requirements

| Metric | Target |
|---|---|
| Processing time (typical) | < 30 seconds |
| Processing time (worst case, 100MB) | < 90 seconds |
| Upload handling | Streaming (no full-memory load) |
| Concurrent jobs (MVP) | 3–5 simultaneous (single VPS) |
| Storage I/O | Non-blocking where possible |

Memory-intensive operations (Potrace, ImageMagick) must be isolated. If memory limits are approached during processing, the job is failed cleanly rather than crashing the server.

---

## 18. Deployment

### MVP Infrastructure

| Component | Setup |
|---|---|
| Server | Single VPS (2–4 vCPU, 4–8GB RAM recommended) |
| Web server | Nginx or Laravel Herd (local dev) |
| PHP | 8.4 |
| Storage | Local filesystem |
| Queue | Sync (in-process, no Redis required) |
| Scheduler | `php artisan schedule:run` via cron (1-minute interval) |

### Required System Dependencies

| Dependency | Used By |
|---|---|
| ImageMagick | `ImageProcessingService` |
| Potrace | `VectorizationService` |
| GhostScript | PDF handling (via ImageMagick) |
| PHP GD / Imagick ext | PHP-level image ops |

### Environment Variables

```env
OPENAI_API_KEY=          # Optional — for OpenAI provider
GEMINI_API_KEY=          # Primary — for Gemini vision (free tier available)
CUTJOB_RETENTION_DAYS=90
CUTJOB_FAILED_RETENTION_HOURS=3
CUTJOB_MAX_FILE_SIZE_MB=100
CUTJOB_CONFIDENCE_THRESHOLD=0.65
```

---

## 19. Success Criteria

The MVP is considered successful when all of the following hold:

| Criterion | Measure |
|---|---|
| Upload → PDF works end-to-end | 100% of clean test files process successfully |
| PDF opens in target software | Verified in Illustrator, CorelDRAW, and one RIP tool |
| Cut paths follow object boundary | Subjective QA pass on 10 diverse test images |
| 100MB files are handled | No crashes, no timeouts on oversized test files |
| AI improves difficult images | Side-by-side comparison shows measurable improvement |
| Files available for 90 days | Cleanup job verified to not delete early |
| Failed jobs surface clear errors | No silent failures; every failure has a log entry |

---

## 20. Known Limitations (MVP)

| Limitation | Impact |
|---|---|
| Best results on clean, high-contrast designs | Busy or low-contrast artwork may produce rough cut paths |
| No manual cutline correction | User must re-upload an improved source file if output is unsatisfactory |
| No multi-layer AI segmentation | AI handles single primary subject only |
| Single-server deployment | No horizontal scaling; throughput is hardware-bound |
| Sync queue | Jobs block the request during processing (mitigated by < 30s target) |
| Local storage only | No CDN, no redundancy — data loss risk on VPS failure |

---

## 21. Future Roadmap (Post-MVP)

| Feature | Priority | Notes |
|---|---|---|
| Redis queue + workers | High | Required for scale; unblocks concurrent processing |
| Manual cutline editing | High | Livewire canvas overlay with path adjust |
| Batch processing | Medium | Multiple uploads in one job |
| REST API with token auth | Medium | For agency integrations |
| Team/organization accounts | Medium | Shared job history and billing |
| Advanced AI segmentation | Low | SAM2 or similar for multi-subject isolation |
| Cloud storage (S3) | Low | Replace local storage for durability |
| Webhook notifications | Low | Notify on job completion |
| ~~2FA~~ | ~~High~~ | ~~Implemented in v1.2~~ |
| ~~Admin dashboard~~ | ~~High~~ | ~~Implemented in v1.2~~ |
| ~~Permission system~~ | ~~High~~ | ~~Implemented in v1.2~~ |
| ~~AI SDK Agent pattern~~ | ~~Medium~~ | ~~Implemented in v1.2~~ |
| ~~Failed job cleanup~~ | ~~Medium~~ | ~~Implemented in v1.3 — auto-purge after 3 hours~~ |
| ~~Usage quotas~~ | ~~Medium~~ | ~~Implemented in v1.3 — monthly job limits~~ |

---

## 22. Notifications & Download System

> **Added in v1.1** — implemented post-PRD alongside the initial build.

### Overview

Users receive real-time feedback when a job finishes (success or failure) without having to poll the dashboard. Completed jobs also trigger an email with a secure, time-limited download link.

### In-App Notifications

| Aspect | Detail |
|---|---|
| Storage | Laravel database notifications (`notifications` table) |
| Channels | `database` + `mail` on success; `database` only on failure |
| Persistence | Notifications are never deleted — full history is accessible |
| Unread indicator | Bell icon in sidebar shows unread count badge (max display `9+`) |
| Mark as read | Opening a notification decrements the unread count immediately |
| Mark all as read | One-click action clears all unread notifications |
| History page | `/notifications` — paginated 20 per page, full archive |
| Count refresh | Bell polls every 60 seconds via `wire:poll` |

### Notification Bell Component

- Livewire SFC component (`notification-bell`) embedded in both the desktop sidebar and the mobile header.
- Renders a dropdown showing the 8 most recent notifications on click.
- Completed notifications open the download URL in a new tab when clicked.
- Links to the full `/notifications` history page in the dropdown footer.

### Email Notification

Sent only for successful completions via Laravel Mail (queued).

| Field | Value |
|---|---|
| Subject | `Your file is ready: {filename}` |
| Body | File name, "processed successfully" message, download button |
| Link expiry | 7 days |
| Channel | `mail` (uses app's default mailer) |

### Secure Download Links

| Aspect | Detail |
|---|---|
| Mechanism (email) | `URL::temporarySignedRoute()` — HMAC-signed, 7-day expiry |
| Mechanism (dashboard) | `URL::signedRoute()` — HMAC-signed, permanent (valid until job expires) |
| Mechanism (create page) | `Storage::download()` via Livewire `wire:click` — streamed through Livewire's `SupportFileDownloads` hook |
| Route | `GET /jobs/{cutJob}/download` (named `jobs.download`) |
| Middleware | `['auth', 'signed']` — unauthenticated users redirect to login then back |
| Authorization | `CutJobPolicy@download` — owner-only, completed jobs only |
| Response | `Storage::download()` streamed response with descriptive filename (`{name}_{w}x{h}.pdf`) |

---

## 23. Admin Section

> **Added in v1.2** — full admin panel with role-based access.

### Overview

Super admins have a dedicated section at `/admin/*` with full visibility into jobs, users, and system health. The admin sidebar is separated from the workspace sidebar — admins see only admin navigation, regular users see only workspace navigation.

### Admin Pages

| Page | Route | Purpose |
|---|---|---|
| Dashboard | `/admin` | Stats grid (total jobs, completed, failed, AI usage rate), pipeline metrics (avg processing time, queue depth), recent failures (responsive card + table layout) |
| All Jobs | `/admin/jobs` | Search, status/AI filter, sortable columns, inline delete, paginated 20/page |
| Failed Jobs | `/admin/failed-jobs` | Search, expandable error details, retry (re-dispatches `ProcessCutJob`), delete, paginated 20/page |
| Users | `/admin/users` | Search, sortable, admin role toggle, 2FA status indicator, job count per user, paginated 20/page |
| System | `/admin/system` | Pipeline binary health checks (ImageMagick, Potrace, Inkscape, GhostScript), queue status, storage cleanup trigger, configuration display |

### Middleware

All admin routes are protected by three middleware layers:

```
['auth', 'verified', 'admin']
```

| Middleware | Purpose |
|---|---|
| `auth` | Requires authenticated session |
| `verified` | Requires email verification |
| `admin` | `EnsureUserIsAdmin` — checks `$user->is_admin`, aborts 403 |

### Login Redirect

Custom `LoginResponse` (overrides Fortify's default):
- Admin users → `route('admin.dashboard')`
- Regular users → `route('dashboard')`

### Implementation

- All admin pages are Livewire SFC components (⚡ prefix)
- Routes defined in `routes/admin.php` (required from `routes/web.php`)
- `CutJobPolicy` has a `before()` method granting admins full access to all jobs

---

## 24. Permission & Authorization System

> **Added in v1.2** — centralized permission enum with Gate registration.

### Permission Enum

`App\Permission` — a backed enum with 5 named abilities:

| Permission | Slug | Check |
|---|---|---|
| `AccessAdmin` | `access-admin` | `$user->is_admin` |
| `AccessWorkspace` | `access-workspace` | `!$user->is_admin` |
| `ManageUsers` | `manage-users` | `$user->is_admin` |
| `ManageSystem` | `manage-system` | `$user->is_admin` |
| `ViewAllJobs` | `view-all-jobs` | `$user->is_admin` |

### Gate Registration

Gates are registered automatically in `AppServiceProvider::registerPolicies()`:

```php
foreach (Permission::cases() as $permission) {
    Gate::define($permission->value, fn (User $user) => $permission->check($user));
}
```

### Sidebar Scoping

- Workspace nav (Dashboard, New Job, Recent Jobs): `@can('access-workspace')` — visible only to regular users
- Admin nav (Dashboard, All Jobs, Failed Jobs, Users, System): `@can('access-admin')` — visible only to admins
- Admins see **only** admin navigation; users see **only** workspace navigation

### Users Table

| Column | Type | Purpose |
|---|---|---|
| `is_admin` | `boolean` | Default `false` — determines role |
| `two_factor_secret` | `text` | Fortify 2FA TOTP secret (nullable) |
| `two_factor_recovery_codes` | `text` | Backup codes (nullable) |

---

## 25. AI SDK Integration (Implemented)

> **Added in v1.2** — replaced raw HTTP calls with Laravel AI SDK Agent pattern.

### Package

`laravel/ai` v0.6.0 (official first-party Laravel AI package, wraps Prism PHP internally).

### Architecture

```
ProcessCutJob
    ↓ (confidence low)
AIService::analyze()
    ↓
SubjectIsolationAgent::prompt()
    ↓ (with image attachment via Prism\Prism\ValueObjects\Media\Image)
Gemini 2.0 Flash (vision)
    ↓ (structured JSON output)
{ svg_path: "M ...", confidence: 0.92 }
    ↓
AIService writes SVG to disk
    ↓
ProcessCutJob continues with AI-generated mask
```

### Key Files

| File | Purpose |
|---|---|
| `app/Ai/Agents/SubjectIsolationAgent.php` | Agent class — `HasStructuredOutput`, returns `svg_path` + `confidence` |
| `app/Services/AIService.php` | Orchestrates agent call, handles fallback, writes SVG output |
| `config/ai.php` | Provider configuration (published from `laravel/ai`) |

### Provider Support

Multiple AI providers can be configured simultaneously via `.env`:

```env
GEMINI_API_KEY=...    # Primary (free tier available)
OPENAI_API_KEY=...    # Optional
ANTHROPIC_API_KEY=... # Optional
```

Failover is supported via the `#[Provider]` attribute or per-prompt:

```php
$response = (new SubjectIsolationAgent)->prompt('...', provider: [Lab::Gemini, Lab::OpenAI]);
```

### Testing

The agent supports `fake()` for testing without real API calls:

```php
SubjectIsolationAgent::fake([['svg_path' => 'M 0 0 L 100 0 Z', 'confidence' => 0.9]]);
SubjectIsolationAgent::assertPrompted(fn ($prompt) => $prompt->contains('subject'));
```

---

## 26. Security & Quality Fixes (v1.3)

> **Added in v1.3** — batch of security hardening, bug fixes, and quality improvements.

### File Upload Validation (#2)

- Server-side file size validation enforces `config('cutjob.max_file_size_mb')` limit (default 100 MB)
- Validation runs before any processing begins

### Authorization Hardening (#3, #6)

- Admin job deletion requires `Gate::authorize('view-all-jobs')` — prevents non-admin users from deleting jobs even with a direct request
- Admin `toggleAdmin` action gated with `Gate::authorize('manage-users')`
- `CutJobPolicy@before()` grants admins access to all jobs

### Factory File Paths (#4)

- `CutJobFactory` uses `Str::ulid()` instead of `fake()->uuid()` to match the `HasUlids` trait on `CutJob`

### N+1 Query Prevention (#5)

- Dashboard job status polling (`checkJobStatus`) selects only required columns instead of `SELECT *`

### Error Message Security (#7)

- Internal exception details are no longer exposed to end users
- Failed jobs show a generic user-friendly message; full stack traces remain in server logs only

### Input Validation (#8, #10)

- `jobName` field validates with `max:255` to prevent oversized input
- Target dimensions (width/height) capped at 4096 px maximum to prevent memory abuse

### Model Security (#9)

- Removed system-only fields (`ai_used`, `confidence_score`, `processing_duration_ms`, `error_message`) from `CutJob::$fillable`
- These fields are now set only internally by the processing pipeline

### Scope Naming (#11)

- Renamed `scopePending` to `scopeVisible` on `CutJob` — the scope filters out expired jobs, not pending ones

### SVG Path Validation (#12)

- `AIService` validates SVG path data returned by the AI agent before writing to disk
- Prevents malformed or empty paths from entering the vectorization pipeline

---

## 27. Usage Quotas & Failed Job Retention (v1.3)

> **Added in v1.3** — monthly usage tracking and aggressive failed job cleanup.

### Monthly Usage Quotas (#24)

The dashboard tracks per-user monthly job usage against a plan limit.

| Aspect | Detail |
|---|---|
| Metric | Count of non-failed jobs created in the current calendar month |
| Default limit | 10 jobs (Starter plan) |
| Display | Progress bar on dashboard with `X of Y jobs used` |
| Exclusion | Failed jobs do **not** count toward the quota |
| Upgrade CTA | Links to billing/upgrade page when approaching limit |

### Failed Job Auto-Deletion (#26)

Failed jobs are cleaned up aggressively since they have no useful output.

| Aspect | Detail |
|---|---|
| Retention window | 3 hours (configurable via `CUTJOB_FAILED_RETENTION_HOURS`) |
| Cleanup trigger | Same `cutjob:cleanup` scheduled command (daily at 02:00) |
| Behavior | Files purged from storage, record marked `expired`, paths nulled |
| Recent failed jobs | Jobs less than 3 hours old are preserved for user visibility |
| Completed jobs | Unaffected — retain full 90-day retention window |
