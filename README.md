# Mail Audit

[GitHub](https://github.com/toreador34/flarum-mail-audit) · [Packagist](https://packagist.org/packages/toreador/flarum-mail-audit)

Flarum 1.x admin extension that records **every outgoing email** in the
database and lets administrators inspect exactly what was sent (or why it
failed): sender and recipients, subject, rendered HTML and plain-text bodies,
MIME headers and attachment metadata — all from the admin panel.

```
toreador/flarum-mail-audit
```

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
`cd js && npm run build` (the committed `js/dist` is used by releases).

## How it captures mail

The extension hooks into Flarum's own mail pipeline; no mail driver changes
are required.

- `Illuminate\Mail\Events\MessageSending` — the fully rendered message is
  stored as a `pending` row before it is handed to the transport. This works
  for immediate sends as well as for queued notification emails (the queue
  worker boots the same hooks).
- The Swift transport is wrapped by `AuditTransport`
  (`src/Mail/AuditTransport.php`). It finalises the row as `sent` or `failed`
  and records the exception class/message when the transport throws. Because
  the wrapper sits below `Swift_Mailer`, failures that Swift swallows
  internally (such as `RfcComplianceException` for invalid addresses) are
  still recorded correctly, and a late `MessageSent` event can never flip a
  failure back to `sent`.
- Rows that remain `pending` (process killed, another listener cancelled the
  send, ...) are finalised as `failed` by the scheduled prune task.

Auditing is best-effort by design: if the database write fails, the email is
still delivered and the error is written to the Flarum log (at most three
times per process to avoid log flooding).

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

## Settings

| Setting | Default | Description |
|---|---|---|
| `toreador-flarum-mail-audit.enabled` | `1` | Record outgoing emails |
| `toreador-flarum-mail-audit.capture_body` | `1` | Store HTML/text bodies (disable to keep only metadata) |
| `toreador-flarum-mail-audit.retention_days` | `30` | Delete entries older than this (`0` = keep forever) |

## Pruning

A console command is registered and scheduled daily:

```
php flarum mail-audit:prune                 # stale pending cleanup + retention
php flarum mail-audit:prune --days=7        # override retention
php flarum mail-audit:prune --stale-minutes=5
```

If you do not run the Flarum scheduler (`php flarum schedule:run`), call the
command from cron yourself.

## API endpoints

All endpoints require an admin session.

| Method | Route | Purpose |
|---|---|---|
| GET | `/api/mail-audit/mails` | Paginated list + stats (`limit`, `offset`, `filter[status]`, `filter[qtext]`) |
| GET | `/api/mail-audit/mails/:id` | Full entry (bodies, headers, attachments) |
| DELETE | `/api/mail-audit/mails/:id` | Delete one entry |
| POST | `/api/mail-audit/mails/clear` | Delete all (optional `status`, `older_than_days`) |

## Database

One table is created by the migration: `mail_audit_logs` (prefixed with your
configured `database.prefix`), storing metadata, bodies (truncated at 1 MB),
headers and attachment metadata as JSON.

## Requirements

- PHP 7.4+
- Flarum `^1.2`

## About this fork

- Composer package: `toreador/flarum-mail-audit`
- GitHub: https://github.com/toreador34/flarum-mail-audit
- Packagist: https://packagist.org/packages/toreador/flarum-mail-audit

## License

MIT. See [LICENSE](LICENSE).
