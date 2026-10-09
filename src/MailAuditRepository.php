<?php

/**
 * This file is part of the Toreador Mail Audit extension for Flarum.
 *
 * For detailed copyright and license information, please view the
 * LICENSE file that was distributed with this source code.
 */

namespace Toreador\MailAudit;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * All read/write access to the mail_audit_logs table.
 *
 * Rows are created by MailAuditRecorder when a message is about to be sent
 * and finalised (sent/failed) when the transport has handled it.
 */
class MailAuditRepository
{
    public const TABLE = 'mail_audit_logs';

    public const STATUSES = ['pending', 'sent', 'failed'];

    /**
     * @var ConnectionInterface
     */
    protected $db;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var bool|null
     */
    protected $tableExists;

    /**
     * @var int
     */
    protected $loggedErrors = 0;

    public function __construct(ConnectionInterface $db, ?LoggerInterface $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function tableExists(): bool
    {
        if ($this->tableExists !== null) {
            return $this->tableExists;
        }

        try {
            $this->tableExists = $this->db->getSchemaBuilder()->hasTable(self::TABLE);
        } catch (Throwable $e) {
            $this->tableExists = false;
        }

        return $this->tableExists;
    }

    /**
     * @param  array<string, mixed> $attributes
     */
    public function insert(array $attributes): ?int
    {
        try {
            $id = $this->db->table(self::TABLE)->insertGetId($attributes);

            return $id !== null ? (int) $id : null;
        } catch (Throwable $e) {
            $this->logError('Could not insert mail audit row', $e);

            return null;
        }
    }

    public function markSentById(int $id): void
    {
        $this->finalise(['id' => $id], 'sent', null);
    }

    public function markSentByMessageId(string $messageId): void
    {
        if ($messageId === '') {
            return;
        }

        $this->finalise(['message_id' => $messageId], 'sent', null);
    }

    public function markFailedById(int $id, string $error): void
    {
        $this->finalise(['id' => $id], 'failed', $error);
    }

    public function markFailedByMessageId(string $messageId, string $error): void
    {
        if ($messageId === '') {
            return;
        }

        $this->finalise(['message_id' => $messageId], 'failed', $error);
    }

    /**
     * Only rows that are still pending are finalised, so a fallback
     * MessageSent event can never overwrite a recorded failure.
     *
     * @param array<string, mixed> $where
     */
    protected function finalise(array $where, string $status, ?string $error): void
    {
        try {
            $values = ['status' => $status];

            if ($status === 'sent') {
                $values['sent_at'] = Carbon::now()->toDateTimeString();
            }

            if ($error !== null) {
                $values['error'] = $error;
            }

            $this->db->table(self::TABLE)
                ->where($where)
                ->where('status', 'pending')
                ->update($values);
        } catch (Throwable $e) {
            $this->logError('Could not finalise mail audit row', $e);
        }
    }

    /**
     * Rows that are still pending after this long were interrupted (process
     * died, sending was cancelled by another listener, ...); turn them into
     * failures so the list does not show eternal "pending" entries.
     */
    public function markStalePending(int $minutes = 15): int
    {
        if ($minutes <= 0 || ! $this->tableExists()) {
            return 0;
        }

        try {
            return (int) $this->db->table(self::TABLE)
                ->where('status', 'pending')
                ->where('created_at', '<', Carbon::now()->subMinutes($minutes)->toDateTimeString())
                ->update([
                    'status' => 'failed',
                    'error' => 'Mail Audit: the message was never handed to the mail transport (interrupted, cancelled or the process died).',
                ]);
        } catch (Throwable $e) {
            $this->logError('Could not mark stale mail audit rows', $e);

            return 0;
        }
    }

    /**
     * @param int $days Retention in days. 0 or less keeps everything.
     */
    public function prune(int $days): int
    {
        if ($days <= 0 || ! $this->tableExists()) {
            return 0;
        }

        try {
            return (int) $this->db->table(self::TABLE)
                ->where('created_at', '<', Carbon::now()->subDays($days)->toDateTimeString())
                ->delete();
        } catch (Throwable $e) {
            $this->logError('Could not prune mail audit rows', $e);

            return 0;
        }
    }

    /**
     * @return array{total: int, rows: array<int, array<string, mixed>>}
     */
    public function list(int $limit, int $offset, ?string $status, ?string $search): array
    {
        if (! $this->tableExists()) {
            return ['total' => 0, 'rows' => []];
        }

        $query = $this->db->table(self::TABLE);

        if ($status !== null && in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        $search = $search !== null ? trim($search) : '';

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($q) use ($like) {
                $columns = ['subject', 'to_json', 'cc_json', 'bcc_json', 'from_json', 'message_id', 'attachments', 'error'];

                foreach ($columns as $i => $column) {
                    if ($i === 0) {
                        $q->where($column, 'like', $like);
                    } else {
                        $q->orWhere($column, 'like', $like);
                    }
                }
            });
        }

        $total = (clone $query)->count();

        $rows = $query->orderBy('id', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->get();

        $rows = $rows ? $rows->all() : [];

        return [
            'total' => (int) $total,
            'rows' => array_map(function ($row) {
                return (array) $row;
            }, $rows),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        if (! $this->tableExists()) {
            return null;
        }

        $row = $this->db->table(self::TABLE)->where('id', (int) $id)->first();

        return $row ? (array) $row : null;
    }

    /**
     * Serialize a row for the admin list API (no bodies, keeps the payload small).
     *
     * @param  array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'status' => (string) ($row['status'] ?? 'pending'),
            'mailer' => isset($row['mailer']) && $row['mailer'] !== '' ? (string) $row['mailer'] : null,
            'subject' => isset($row['subject']) && $row['subject'] !== '' ? (string) $row['subject'] : null,
            'from' => $this->decode($row['from_json'] ?? null),
            'to' => $this->decode($row['to_json'] ?? null),
            'recipient_count' => (int) ($row['recipient_count'] ?? 0),
            'attachment_count' => (int) ($row['attachment_count'] ?? 0),
            'has_html' => (bool) ($row['has_html'] ?? false),
            'has_text' => (bool) ($row['has_text'] ?? false),
            'message_id' => isset($row['message_id']) && $row['message_id'] !== '' ? (string) $row['message_id'] : null,
            'error' => isset($row['error']) && $row['error'] !== '' ? (string) $row['error'] : null,
            'created_at' => $this->toIso(isset($row['created_at']) ? (string) $row['created_at'] : ''),
            'sent_at' => $this->toIso(isset($row['sent_at']) ? (string) $row['sent_at'] : ''),
        ];
    }

    /**
     * Full record, including bodies and headers, for the detail modal.
     *
     * @return array<string, mixed>|null
     */
    public function detail(int $id): ?array
    {
        $row = $this->find($id);

        if (! $row) {
            return null;
        }

        $detail = $this->present($row);

        $detail['cc'] = $this->decode($row['cc_json'] ?? null);
        $detail['bcc'] = $this->decode($row['bcc_json'] ?? null);
        $detail['reply_to'] = $this->decode($row['reply_to_json'] ?? null);
        $detail['body_html'] = isset($row['body_html']) && $row['body_html'] !== null ? (string) $row['body_html'] : null;
        $detail['body_text'] = isset($row['body_text']) && $row['body_text'] !== null ? (string) $row['body_text'] : null;
        $detail['headers'] = $this->decode($row['headers'] ?? null);
        $detail['attachments'] = $this->decode($row['attachments'] ?? null);

        return $detail;
    }

    /**
     * @return array{total: int, sent: int, failed: int, pending: int, last_24h: int}
     */
    public function stats(): array
    {
        $stats = ['total' => 0, 'sent' => 0, 'failed' => 0, 'pending' => 0, 'last_24h' => 0];

        if (! $this->tableExists()) {
            return $stats;
        }

        try {
            $rows = $this->db->table(self::TABLE)
                ->select('status')
                ->selectRaw('COUNT(*) AS count')
                ->groupBy('status')
                ->get();

            foreach ($rows ?: [] as $row) {
                $count = (int) $row->count;
                $stats['total'] += $count;

                $key = (string) $row->status;

                if (array_key_exists($key, $stats)) {
                    $stats[$key] = $count;
                }
            }

            $stats['last_24h'] = (int) $this->db->table(self::TABLE)
                ->where('created_at', '>=', Carbon::now()->subDay()->toDateTimeString())
                ->count();
        } catch (Throwable $e) {
            $this->logError('Could not compute mail audit stats', $e);
        }

        return $stats;
    }

    public function delete(int $id): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        try {
            return (bool) $this->db->table(self::TABLE)->where('id', (int) $id)->delete();
        } catch (Throwable $e) {
            $this->logError('Could not delete mail audit row', $e);

            return false;
        }
    }

    public function clear(?string $status = null, ?int $olderThanDays = null): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        try {
            $query = $this->db->table(self::TABLE);

            if ($status !== null && in_array($status, self::STATUSES, true)) {
                $query->where('status', $status);
            }

            if ($olderThanDays !== null && $olderThanDays > 0) {
                $query->where('created_at', '<', Carbon::now()->subDays($olderThanDays)->toDateTimeString());
            }

            return (int) $query->delete();
        } catch (Throwable $e) {
            $this->logError('Could not clear mail audit rows', $e);

            return 0;
        }
    }

    protected function decode($value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function toIso(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable $e) {
            return $value;
        }
    }

    protected function logError(string $context, Throwable $error): void
    {
        if ($this->logger === null || $this->loggedErrors >= 3) {
            return;
        }

        $this->loggedErrors++;

        $this->logger->error("Toreador Mail Audit: $context: {$error->getMessage()}", [
            'exception' => $error,
        ]);
    }
}
