# Loop — Screenshot Feedback Board

A shared feedback workspace for reviewing software with developers. Upload or paste a screenshot, explain a change, assign a developer, and keep every reply and review round in one place.

**Open → Ready for review → Approved**, with reopening and discussion history when a fix needs another round.

## Features

- Screenshot file uploads, drag and drop, and clipboard paste (including the Mac clipboard screenshot workflow).
- Separate projects and platform links.
- Developer names/emails, assignment, search, and status filters.
- Discussion history and a complete done/review/reopen/approve cycle.
- Shared MySQL storage for feedback and screenshot bytes.
- Revision checks that reject conflicting saves.
- Responsive interface and keyboard-accessible dialogs.

## Stack

React 19, Vite 6, JavaScript, CSS, Lucide icons, PHP 8.3, MySQL/MariaDB, and GitHub Actions. Built with AI-assisted development from a practical client/developer review workflow.

This repository now contains the PHP/MySQL version used on cPanel. Earlier Cloudflare code remains in Git history. Production feedback, screenshot files, database passwords and deployment credentials are not in this repository.

## Local development

Requires Node.js 22, PHP 8.3 with `pdo_mysql` and `fileinfo`, and MySQL/MariaDB.

1. Run `npm ci`.
2. Create your own local database and import `server/schema.sql`.
3. Copy `server/config.example.php` to `server/config.php` and enter your local database credentials. The real file is ignored by Git.
4. Start `php -S 127.0.0.1:8000 -t server`.
5. In another terminal run `npm run dev`. Vite proxies the API to the PHP process.

## Tests and build

```bash
npm test
python3 -m unittest discover -s tests -p '*_test.py'
php -l server/api.php
npm run build
```

`tests/php-integration.php` needs a dedicated empty test database and the PHP server on port 8000. It creates test records, so never run it against production. GitHub Actions provisions an isolated MySQL service for these tests.

The build creates `dist/` with `index.html`, hashed `assets/`, `api.php`, `.htaccess`, and `.user.ini`. No database configuration or SQL import is packaged.

## BigRock deployment

See [deployment setup](docs/DEPLOYMENT.md). A push to `main` or a manual workflow run builds, tests, and deploys via encrypted FTPS once the hosting secrets are configured. Missing secrets cause deployment to be skipped with an explanatory notice; tests still run.

Code pushes are user-triggered. There is no periodic or automatic code-push process. Deployment automation reacts only to the configured GitHub events.

## Architecture and data

- `src/Board.jsx`: screenshot/feedback/assignee/reply/status table and filters.
- `src/Detail.jsx`: discussion, completion, approval, and reopening.
- `src/Forms.jsx`, `src/components.jsx`: forms, reusable controls, dialogs, screenshot input.
- `server/api.php`: JSON board and image endpoints.
- `server/schema.sql`: initial empty schema for a new installation only.
- `scripts/deploy-ftps.py`: explicit allowlist, encrypted transfer and per-file publish.

`loop_board` stores the workspace JSON and revision; `loop_images` stores image bytes. Database backups capture both. Routine application deployment does not access the database. Future schema changes require an explicit migration and a backup, rather than reimporting an old snapshot.

## Current access model and limitations

This is a shared workspace without individual sign-in or role enforcement: anyone who can reach the public app can read and edit its contents. Reviewer/Developer labels do not verify identity. Email addresses do not send invitations. Updates from other users appear after refresh. Use HTTPS for the screenshot clipboard and browser APIs. Supported image formats are PNG, JPEG, GIF and WebP up to 8 MB.

The deployment updates individual files, not an atomic whole-directory release. Hashed assets go first and HTML last; previous assets remain for open browser tabs. Server-side `config.php` and database data are never deployed or reset.
