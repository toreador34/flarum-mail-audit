import app from 'flarum/admin/app';
import { MailAddress } from '../states/MailAuditState';

const KNOWN_STATUSES = ['sent', 'failed', 'pending'];

export function statusKey(status: string): string {
  return KNOWN_STATUSES.indexOf(status) !== -1 ? status : 'unknown';
}

export function statusLabel(status: string): any {
  return app.translator.trans('toreador-flarum-mail-audit.admin.status.' + statusKey(status));
}

export function formatAddress(address?: MailAddress | null): string {
  if (!address || !address.address) return '';
  return address.name ? `${address.name} <${address.address}>` : address.address;
}

export function formatAddressList(list?: MailAddress[] | null): string {
  if (!list || !list.length) return '—';
  return list.map((a) => formatAddress(a)).join(', ');
}

export function recipientsLine(list: MailAddress[] | null, max = 2): string {
  if (!list || !list.length) return '—';

  const shown = list.slice(0, max).map((a) => formatAddress(a));

  if (list.length > max) {
    const more = list.length - max;
    shown.push(String(app.translator.trans('toreador-flarum-mail-audit.admin.table.more_recipients', { count: more })));
  }

  return shown.join(', ');
}

export function formatBytes(bytes: number | null | undefined): string {
  if (bytes === null || bytes === undefined || isNaN(bytes)) return '';

  if (bytes < 1024) return `${bytes} B`;
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;

  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

export function truncate(value: string, length = 160): string {
  if (!value) return '';
  const single = value.replace(/\s+/g, ' ').trim();
  return single.length <= length ? single : single.substring(0, length) + '…';
}
