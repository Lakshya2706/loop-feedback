# Loop — Screenshot Feedback Board

A focused feedback workspace for reviewing software with developers. Attach a screenshot, describe a change, assign a developer, and keep every reply and review round attached to that feedback.

## The problem

Screenshot feedback gets buried in chat groups. Loop gives each issue a persistent place, an assignee, a discussion, and a clear review cycle:

**Open → Ready for review → Approved**

If a fix needs more work, the reviewer can reopen it with another comment while preserving the discussion history.

## Features

- Project-based feedback boards with platform links.
- Screenshot uploads through file selection, drag and drop, and clipboard paste.
- Mac clipboard workflow: Control + Shift + Command + 4, select an area, then paste with Command + V.
- Developer directory with names and email addresses.
- Assignment, search, and filters for developer and status.
- Developer replies, follow-up comments, and status history.
- Shared server-side records and screenshot files using Cloudflare R2.
- Conditional writes using ETags to detect concurrent edits.
- Responsive layout and keyboard-accessible dialogs.
- A sample workspace for exploring the interface without creating real records.

## Stack

React 19, Vite 6, JavaScript, CSS, Lucide icons, Cloudflare Workers, and Cloudflare R2. Node’s built-in test runner verifies input handling and backend behavior.

## Run locally

Requires Node.js 20.19+ or 22.12+.

```bash
npm ci
npm run dev
```

Open the URL printed by Vite. The preview API uses a local file-backed storage adapter; it does not connect to the original live workspace. The sample board is interactive demonstration data. Create a project to save local records.

## Verify and build

```bash
node --test tests/*.test.mjs
npm run build
```

The build emits the frontend in `dist/` and a self-contained Worker at `dist/server/index.js`.

## Architecture

| File | Responsibility |
| --- | --- |
| `src/main.jsx` | Application state, shared persistence, and view composition |
| `src/Board.jsx` | Five-column feedback table, project selection, search, and filters |
| `src/Detail.jsx` | Discussion history and review actions |
| `src/Forms.jsx` | Feedback, project, and developer forms |
| `src/components.jsx` | Reusable shell, dialogs, screenshot input, and visual primitives |
| `src/screenshot-input.js` | File/clipboard transfer extraction and image validation |
| `worker.js` | Shared board and image endpoints |
| `vite.config.js` | Local-only storage adapter |
| `build-worker.mjs` | Packages frontend assets into the Worker |

The Worker stores records in `workspace/board.json` and image objects under `images/`. Existing R2 data is separate from application source and build artifacts.

## Deploy your own instance

Use your own Cloudflare Worker with an R2 binding named `BUCKET`, or create a new Sites project and configure its own identity in `.openai/hosting.json`. This repository intentionally excludes the original deployment identity and all live feedback data. Never reuse another workspace’s bucket or credentials.

Register a Sites project before deploying through Sites, then add its returned `project_id` to `.openai/hosting.json`. Alternatively, configure your own Cloudflare Worker deployment around `dist/server/index.js` and an R2 bucket bound as `BUCKET`. Deployment configuration and secrets belong to your own environment.

## Access model and current limitations

This version is a shared workspace: anyone permitted to open the app can read and edit its records. It does not implement individual user authentication, role enforcement, or email invitations. The Reviewer/Developer comment selector is a display label, not verified identity. A public deployment allows anonymous edits. Add authentication and authorization before using an instance for confidential information or restricted teams.

Updates from other users appear after refresh; live subscriptions are not implemented. File validation supports PNG, JPEG, GIF, and WebP up to 8 MB. Native macOS screenshot-thumbnail dragging should be verified on the target Mac/browser combination; clipboard paste is also supported.

## Project context

Built with AI-assisted development to turn a practical client/developer communication workflow into a working web application. This repository contains the application source and tests, not uploaded client screenshots or production data.
