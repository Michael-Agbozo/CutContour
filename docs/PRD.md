# CutContour AI Generator — Product Requirements Document

**Version:** 1.0  
**Status:** Build Ready  
**Author:** Michael Agbozo  
**Date:** 2026-04-14

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
| View processing logs | Per-job: duration, AI usage, failures |
| Monitor pipeline health | Error rate, memory spikes, slow jobs |
| Trigger storage cleanup | Manual override of the daily cron |
| Inspect failed jobs | Full error_message and stack context |

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
   - Resize if oversized (preserve ratio)
   - Remove ICC profile quirks
3. Edge detection (ImageMagick Canny / threshold)
4. Binary mask generation
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

`gpt-4.1-mini` via Laravel AI SDK (configurable in `config/ai.php`).

### Request Structure

```php
$response = AI::chat()->send([
    'model' => 'gpt-4.1-mini',
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Extract the main subject from this image for die-cut path generation. Return a binary segmentation mask or a simplified SVG path outlining the subject boundary. Ignore background elements.',
                ],
                [
                    'type' => 'image',
                    'image' => $filePath,
                ],
            ],
        ],
    ],
]);
```

### Expected AI Output (in priority order)

1. **Binary segmentation mask** (PNG) — preferred; passed directly to Potrace
2. **Simplified SVG path** — normalized and used as the CutContour path
3. **Boundary description** (text) — regenerate mask via ImageMagick using coordinates

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
| Framework | Laravel (monolith) |
| PHP | 8.4+ |
| Image processing | ImageMagick |
| Vectorization | Potrace |
| AI | Laravel AI SDK |
| Storage | Local filesystem (VPS) |
| Queue | Sync (MVP) — Redis + workers in v2 |
| Auth | Laravel Fortify (email + password) |

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
| 2FA | No (v2 roadmap) |
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
| 90 days elapsed | Original + output deleted from disk |
| After deletion | `cut_jobs.status` → `expired`; paths nulled |
| User deletes account | All files purged immediately |

### Cleanup

A **daily scheduled job** (`CleanupExpiredJobs`) runs at 02:00 and:
1. Queries `cut_jobs` where `expires_at < now()` and `status != expired`
2. Deletes files from storage
3. Updates job status to `expired`

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
| `status` | `enum` | `processing`, `completed`, `failed`, `expired` |
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
AI_DEFAULT_MODEL=gpt-4.1-mini
CUTJOB_RETENTION_DAYS=90
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
