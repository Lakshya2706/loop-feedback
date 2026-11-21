# BigRock deployment setup

## One-time hosting setup

The existing production app must already have `index.html`, `api.php`, a working `config.php`, and its imported MySQL database. Preserve that database and configuration during every update.

In cPanel → FTP Accounts, create a dedicated account such as `loopdeploy`. Restrict its Directory to the application's actual folder: `/home1/hexas77e/public_html/loop-feedback`. Verify the final folder shown by cPanel before creating it; cPanel may prefill a different directory. Do not use the main cPanel login. Save the generated FTP password privately.

Use Configure FTP Client for that account to obtain its exact FTP server hostname and full username. Explicit FTPS on port 21 must be supported, with a valid server certificate for that hostname. A custom app hostname may not be the correct FTPS hostname. Never disable certificate validation to work around a mismatch; use the hostname supplied by BigRock or contact support.

## GitHub repository secrets

Open repository Settings → Secrets and variables → Actions → New repository secret and add:

| Secret | Value |
| --- | --- |
| `FTP_HOST` | The FTP server hostname from cPanel, without `ftp://` or a path |
| `FTP_USERNAME` | The full FTP username shown by cPanel |
| `FTP_PASSWORD` | That dedicated FTP user's password |

The FTP account root must be the Loop application folder. The deployment starts at `/` inside that restricted account, not `/public_html/loop-feedback` again. No database password is needed in GitHub.

## First run

After adding the secrets, open Actions → Test and deploy to BigRock → Run workflow → main. Wait for the build, tests and FTPS step. If secrets are missing, the workflow reports that deployment was skipped. If FTPS fails, inspect the failed step and verify account root, credentials, server hostname, firewall access and FTPS support.

A green build with a skipped deployment does not mean files were uploaded. A successful FTPS step verifies transfer, not the application's HTTP behavior. Check the website in a browser after the first run; server security rules may block automated web probes.

Future pushes to `main` trigger the same pipeline. To stop deployment, disable the workflow under Actions, or remove the three FTP secrets. No periodic sync or automatic Git push is configured.

## What the workflow preserves

- It never uploads, reads, replaces or deletes remote `config.php`.
- It does not run SQL against the production database.
- It does not deploy private exports, migration data, user uploads or secrets.
- It never mirrors with a delete flag. Old hashed assets remain for open tabs.
- It rejects an unexpected FTP root before uploading.
- It uploads to temporary names and renames each app file; HTML is published last.
- Workflow concurrency prevents overlapping production deployments.

Keep a backup of the database and server configuration. Restoring application code does not restore data. Future database changes must use an explicitly reviewed migration compatible with the deployed code.

## Assignment emails (optional one-time setup)

The deployment includes `smtp-config.example.php`. In cPanel File Manager, rename it to `smtp-config.php` and enter the mailbox password privately in that file. This is separate from the database `config.php`; do not replace the database configuration.

The supplied settings use sender/SMTP username `lakshya.j@senzitlabs.com`, SMTP server `senzitlabs.com`, implicit SSL/TLS on port 465, and the production app URL. Save the real configuration only on the hosting server. It is ignored by Git, blocked by Apache, and never included in deployment uploads. Future deployments preserve it.

New feedback assigned to a developer and reassignment to a different developer trigger an email to that developer's saved email address. Ordinary comments, completion, approval, and unchanged assignments do not send another assignment email. The email includes the project, title, comments and a link opening that item. Existing records are not emailed retroactively by deployment.

SMTP delivery occurs after the board save. Missing configuration or a sending failure does not undo feedback; the app displays an explanation. It reports SMTP acceptance, not final inbox delivery. Failed emails are not automatically retried; correct the configuration, then unassign and reassign the relevant item if a new notification is needed. A request sends at most five assignment emails to bound processing time; use individual assignment changes for normal operation.

PHPMailer v7.2.0 is vendored under server/lib/PHPMailer with its LGPL license and version metadata. TLS certificate verification remains enabled. Mailbox credentials and SMTP diagnostics are not returned to app visitors or logged in detail. Verify your sender domain's SPF/DKIM records through cPanel Email Deliverability if messages are accepted but land in spam.
