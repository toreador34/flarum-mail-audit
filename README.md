# Mail Audit

[GitHub](https://github.com/toreador34/flarum-mail-audit) · [Packagist](https://packagist.org/packages/toreador/flarum-mail-audit)

Flarum 2.x admin extension that records **every outgoing email** in the
database and lets administrators inspect exactly what was sent (or why it
failed): sender and recipients, subject, rendered HTML and plain-text bodies,
MIME headers and attachment metadata — all from the admin panel.

```
toreador/flarum-mail-audit
```

> **Version notice**
> `v2.x` supports **Flarum 2.0+** (Symfony Mailer) and requires PHP 8.3+.
> Use the `v1.0.x` releases for Flarum 1.x (Swift Mailer).

## Installation

```
composer require toreador/flarum-mail-audit
```

Then run the migration and enable the extension:

```
php flarum migrate
php flarum extension:enable toreador-mail-audit
php flarum cache:clear
```

> Local path install (development): add a path repository and require it:
> `"repositories": [{"type": "path", "url": "path/to/flarum-mail-audit"}]`
> then `composer require toreador/flarum-mail-audit:*`.

If you installed from source, rebuild the admin JS bundle with
`cd js && npm install && npm run build` (the committed `js/dist` is used by
releases).

## How it captures mail

The extension hooks into Flarum's own mail pipeline; no mail driver changes
are required.

- `Illuminate\Mail\Events\MessageSending` — the fully rendered
  `Symfony\Component\Mime\Email` is stored as a `pending` row before it is
  handed to the transport. This works for immediate sends as well as for
  queued notification emails (the queue worker boots the same hooks).
- The Symfony transport is wrapped by `AuditTransport`
  (`src/Mail/AuditTransport.php`). It finalises the row as `sent` or `failed`
  and records the exception class/message when the send throws. Because the
  wrapper sits around the transport, it also sees failures thrown while the
  transport is being used (SMTP connection refused, authentication errors, …).
  A late `MessageSent` event can never flip a failure back to `sent`.
- A `Message-ID` is generated at capture time if the message does not carry
  one yet, so the audit row references the same ID that ends up in the
  delivered email (matching it against mail server logs).
- Rows that remain `pending` (process killed, another listener cancelled the
  send, ...) are finalised as `failed` by the scheduled prune task.

Auditing is best-effort by design: if the database write fails, the email is
still delivered and the error is written to the Flarum log (at most three
times per process to avoid log flooding).

Bodies are stored according to the `body_scope` setting and token-bearing
links are masked before storage; see **Settings** and **Privacy & compliance**
below.

## Admin page

Open **Administration → Mail Audit**:

- Stats cards: total, sent, failed, pending and last-24h counts.
- Toolbar: full-text search (subject, recipients, sender, message id,
  attachment names, error), status filter, auto-refresh, clear all.
- Table: id, status badge, subject (with error preview), recipients, sender,
  date and per-row actions (view, delete).
- The **view** modal shows the metadata, the error (if any), attachment list,
  the HTML body rendered in a sandboxed iframe, the plain-text body and the
  raw MIME headers.
- **Compliance tools** (below the table): apply the retention window
  immediately, and delete every entry matching an email address (sender, to,
  cc or bcc) for GDPR Art. 17 / KVKK m. 7 erasure requests.

## Settings

| Setting | Default | Description |
|---|---|---|
| `toreador-flarum-mail-audit.enabled` | `1` | Record outgoing emails |
| `toreador-flarum-mail-audit.capture_body` | `1` | Store bodies (HTML/text); disable to keep only metadata |
| `toreador-flarum-mail-audit.body_scope` | `system` | `system` stores bodies of Flarum's own automatic mails only (notifications, password reset, email confirmation — detected via the core `List-Unsubscribe` header and `/reset`, `/confirm` link patterns); `all` stores every outgoing body |
| `toreador-flarum-mail-audit.redact_tokens` | `1` | Replace one-time links/tokens (reset, confirm, unsubscribe) in stored bodies and headers with `[redacted]` |
| `toreador-flarum-mail-audit.retention_enabled` | `1` | Run automatic retention deletion |
| `toreador-flarum-mail-audit.retention_days` | `30` | Delete entries older than this (`0` keeps everything) |

## Privacy & compliance

- The extension settings page includes a **privacy policy draft** (Turkish
  and English) that adapts to the current body scope and retention settings,
  covers the special category data notice with an explicit consent clause and
  can be copied into your privacy policy / aydınlatma metni with one click.
  It is a draft, not legal advice — have it reviewed before publishing.
- **Data minimisation**: by default only Flarum's own automatic mails have
  their bodies stored; mails sent by other extensions are recorded as
  metadata only. Token-bearing links are masked before storage, so a
  database leak cannot become account takeover material.
- **Storage limitation**: retention defaults to 30 days and the daily
  `mail-audit:prune` task enforces it; it can be disabled explicitly and
  re-applied manually from the audit page.
- **Erasure**: individual entries can be deleted from the list, and the
  compliance tools provide a per-email bulk delete for data subject
  requests.

## Pruning

A console command is registered and scheduled daily:

```
php flarum mail-audit:prune                 # stale pending cleanup + retention
php flarum mail-audit:prune --days=7        # override retention and force a one-off prune
php flarum mail-audit:prune --stale-minutes=5
```

If automatic retention is disabled, the scheduled run only finalises stale
pending rows; a one-off prune can still be forced with `--days`. If you do
not run the Flarum scheduler (`php flarum schedule:run`), call the command
from cron yourself.

## API endpoints

All endpoints require an admin session.

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/mail-audit/mails` | Paginated list + stats (`limit`, `offset`, `filter[status]`, `filter[qtext]`) |
| GET | `/api/mail-audit/mails/:id` | Full entry (bodies, headers, attachments) |
| DELETE | `/api/mail-audit/mails/:id` | Delete one entry |
| POST | `/api/mail-audit/mails/clear` | Delete all (optional `status`, `older_than_days`) |
| POST | `/api/mail-audit/mails/purge` | Delete all entries matching an email address (`email`) |
| POST | `/api/mail-audit/mails/prune` | Apply the retention window now (optional `days`) |

## Database

One table is created by the migration: `mail_audit_logs` (prefixed with your
configured `database.prefix`), storing metadata, bodies (truncated at 1 MB),
headers and attachment metadata as JSON. MySQL/MariaDB, PostgreSQL and SQLite
are supported.

## Requirements

- PHP 8.3+
- Flarum `^2.0`

## About this fork

- Composer package: `toreador/flarum-mail-audit`
- GitHub: https://github.com/toreador34/flarum-mail-audit
- Packagist: https://packagist.org/packages/toreador/flarum-mail-audit

## License

MIT. See [LICENSE](LICENSE).
