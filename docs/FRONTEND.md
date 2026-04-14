# CutContour AI Generator — Frontend Specification

**Version:** 1.0  
**Stack:** Laravel · Livewire v4 · Flux UI v2 · Tailwind CSS v4  
**Scope:** MVP — single authenticated screen, no editing tools

---

## Table of Contents

1. [Screens Overview](#1-screens-overview)
2. [Auth Screens](#2-auth-screens)
3. [Main App Screen](#3-main-app-screen)
4. [Upload Zone](#4-upload-zone)
5. [Processing Status](#5-processing-status)
6. [Preview Panel](#6-preview-panel)
7. [Download & Metadata](#7-download--metadata)
8. [Job History](#8-job-history)
9. [Error States](#9-error-states)
10. [Status Badge Reference](#10-status-badge-reference)
11. [Polling Contract](#11-polling-contract)
12. [Hard Constraints](#12-hard-constraints)

---

## 1. Screens Overview

| Screen | Route | Auth Required |
|---|---|---|
| Login | `/login` | No |
| Register | `/register` | No |
| Forgot Password | `/forgot-password` | No |
| Reset Password | `/reset-password/{token}` | No |
| App (main) | `/` or `/dashboard` | Yes |

There is **no multi-step wizard**. After login, the user lands on one screen that handles upload, status, preview, and download.

---

## 2. Auth Screens

Handled by **Laravel Fortify**. Build Blade views for:

- `login` — email + password form, remember me checkbox
- `register` — name, email, password, confirm password
- `forgot-password` — email field, submit sends reset link
- `reset-password` — new password + confirm, token in URL

No social login. No 2FA in MVP. Email verification is optional (configurable via `config/fortify.php`).

---

## 3. Main App Screen

Single Livewire component. All state is server-side. Alpine.js is acceptable for micro-interactions (drag-over highlight, file input preview before upload).

### Layout

```
┌─────────────────────────────────────────────────────┐
│  Nav: Logo + user email + logout                     │
├─────────────────────────────────────────────────────┤
│                                                     │
│  [Upload Zone]                                      │
│                                                     │
├─────────────────────────────────────────────────────┤
│  [Processing Status]          (hidden when idle)    │
├─────────────────────────────────────────────────────┤
│  [Preview Panel]              (hidden until ready)  │
├─────────────────────────────────────────────────────┤
│  [Download Button]  │  [Metadata]                   │
│                     │  filename / dimensions / time  │
├─────────────────────────────────────────────────────┤
│  [Job History]                                      │
│  Past jobs table with status, expiry, download      │
└─────────────────────────────────────────────────────┘
```

### Visibility Rules

| Section | Shown When |
|---|---|
| Upload Zone | Always (unless a job is actively processing) |
| Processing Status | `status === 'processing'` |
| Preview Panel | `status === 'completed'` |
| Download Button | `status === 'completed'` and file not expired |
| Error Banner | `status === 'failed'` |
| Job History | Always (below fold, may be empty) |

---

## 4. Upload Zone

### Behaviour

- Drag & drop or click to open the OS file picker
- Accepts: `.jpg`, `.jpeg`, `.png`, `.svg`, `.pdf`, `.ai`
- Max file size: **100 MB** — validate client-side before submission, and server-side
- One file at a time (no multi-file in MVP)
- On valid file selection: show filename + size preview inside the zone
- On submit: disable the zone and show the Processing Status section

### States

| State | Visual |
|---|---|
| Idle | Dashed border, upload icon, "Drag & drop or click to upload" label, accepted formats list |
| Drag over | Border color changes, background tint, "Drop to upload" label |
| File selected (pre-submit) | Filename + size shown, submit button active |
| Uploading / Processing | Zone disabled, spinner replaces submit button |
| Post-completion | Zone resets to idle (allows new upload) |

### Validation (client-side, before server round-trip)

| Rule | Message |
|---|---|
| Wrong extension | "Unsupported file type. Please upload a JPG, PNG, SVG, PDF, or AI file." |
| File > 100 MB | "File exceeds 100MB limit. Please reduce the file size and try again." |

---

## 5. Processing Status

Shown while `status === 'processing'`. Replaced by Preview Panel on completion.

### Elements

- Spinner (animated)
- Step label — optional, communicates pipeline progress to reduce perceived wait:
  - "Uploading..."
  - "Analysing image..."
  - "Generating cut path..."
  - "Exporting PDF..."
- Elapsed time counter — optional, shown after 5s to manage expectation

### Notes

- Steps are approximate — the backend does not emit granular events in MVP
- Progress is communicated via polling (see [Polling Contract](#11-polling-contract))
- If processing exceeds 90 seconds without a status change, show: "This is taking longer than expected. Still working..."

---

## 6. Preview Panel

Shown when `status === 'completed'`. Read-only — no interactions.

### Elements

- **Artwork thumbnail** — scaled-down render of the original uploaded file
- **CutContour overlay** — the generated cut path rendered as a semi-transparent pink line (`#ec008c` at ~60% opacity) over the thumbnail
- The overlay is visual only — it is not an editable SVG in MVP
- Thumbnail container has a fixed max height (e.g. 400px); image scales within it

### Notes

- The overlay color `#ec008c` is for visual reference only — the actual spot color in the output PDF is CMYK `0, 100, 0, 0`
- No zoom, no pan, no path selection — purely confirmational

---

## 7. Download & Metadata

Shown alongside the Preview Panel when `status === 'completed'`.

### Download Button

- Label: "Download PDF"
- On click: triggers file download of the output PDF
- Filename delivered: `{original_name}_{width}x{height}.pdf`
- Disabled with expiry notice when `status === 'expired'`

### Metadata Display

| Field | Value |
|---|---|
| Filename | Original uploaded filename |
| Dimensions | Width × Height in pixels |
| Processing time | Duration in seconds (e.g. "Processed in 8.4s") |
| Expires | Human-readable date (e.g. "Available until Jul 13, 2026") |

---

## 8. Job History

Shown below the main interaction area. Visible on first load (may be empty for new users).

### Table Columns

| Column | Notes |
|---|---|
| Filename | Original name, truncated if long |
| Status | Badge — see [Status Badge Reference](#10-status-badge-reference) |
| Dimensions | `{width} × {height}px` |
| Created | Human-readable relative date (e.g. "3 days ago") |
| Expires | Absolute date or "Expired" |
| Action | "Download" link (disabled if expired or failed) |

### Behaviour

- Sorted by `created_at` descending (most recent first)
- No pagination in MVP — show last 20 jobs
- Clicking a past completed job's "Download" link triggers PDF download
- Expired jobs show a muted "Expired" label in the Action column instead of a link
- Failed jobs show a muted "Failed" label with no download option

---

## 9. Error States

### Inline Upload Errors

Displayed beneath the upload zone. Appear on client-side validation failure (no server round-trip).

| Trigger | Message |
|---|---|
| Wrong file type | "Unsupported file type. Please upload a JPG, PNG, SVG, PDF, or AI file." |
| File too large | "File exceeds 100MB limit. Please reduce the file size and try again." |

### Processing Failure Banner

Replaces the Processing Status section when `status === 'failed'`.

> "Processing failed. This may be due to file complexity. Try a simpler or higher-contrast version of the file."

- Includes a "Try again" link that resets the upload zone
- Does not expose internal error details to the user

### Expired Download

When a completed job has passed its `expires_at`:

- Download button is disabled and labelled "Expired"
- Metadata line shows: "This file expired on {date} and has been deleted."

### Network / Polling Failure

If status polling fails three consecutive times:

> "Connection lost. Refreshing..."

Auto-retry with exponential backoff. If still failing after 30s, show:

> "Unable to reach the server. Please refresh the page."

---

## 10. Status Badge Reference

| Status | Label | Color |
|---|---|---|
| `processing` | Processing | Yellow / amber |
| `completed` | Completed | Green |
| `failed` | Failed | Red |
| `expired` | Expired | Gray |

Use Flux UI badge variants or Tailwind utility classes consistent with the rest of the app.

---

## 11. Polling Contract

The Livewire component polls the server to update job status after upload. This replaces WebSockets in the MVP.

### Mechanism

Use Livewire's `wire:poll` or a manual `$dispatch` + interval approach.

```php
// Livewire component
#[Polling(750)]
public function refreshJobStatus(): void
{
    if ($this->job && $this->job->status === 'processing') {
        $this->job->refresh();
    }
}
```

### Poll Interval

| Phase | Interval |
|---|---|
| While `processing` | Every 1–2 seconds |
| After `completed` or `failed` | Stop polling immediately |

### Response

On each poll, the Livewire component re-renders with the latest `status`, `output_path`, and `error_message`. No separate JSON endpoint needed in MVP.

---

## 12. Hard Constraints

These are not design preferences — they are product constraints from the PRD.

| Constraint | Detail |
|---|---|
| No editing tools | No brushes, path handles, shape tools, or canvas drawing |
| No canvas interactions | The preview is a static image overlay — not an interactive canvas |
| No multi-step wizard | Everything happens on one screen |
| One file at a time | No batch upload in MVP |
| No manual cutline adjustment | User re-uploads a better file if unsatisfied |
| Preview overlay is read-only | CutContour path is visual confirmation only |
