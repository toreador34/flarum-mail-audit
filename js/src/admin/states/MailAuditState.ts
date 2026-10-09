import app from 'flarum/admin/app';

export interface MailAddress {
  address: string;
  name: string | null;
}

export interface MailRow {
  id: number;
  status: string;
  mailer: string | null;
  subject: string | null;
  from: MailAddress[];
  to: MailAddress[];
  recipient_count: number;
  attachment_count: number;
  has_html: boolean;
  has_text: boolean;
  message_id: string | null;
  error: string | null;
  created_at: string | null;
  sent_at: string | null;
}

export interface MailStats {
  total: number;
  sent: number;
  failed: number;
  pending: number;
  last_24h: number;
}

export interface ListResponse {
  data: MailRow[];
  total: number;
  limit: number;
  offset: number;
  hasMore: boolean;
  stats: MailStats;
  tableExists: boolean;
}

export default class MailAuditState {
  loading = false;
  rows: MailRow[] = [];
  stats: MailStats | null = null;
  total = 0;
  hasMore = false;
  tableExists = true;
  offset = 0;
  limit = 20;
  statusFilter = '';
  search = '';
  error: string | null = null;

  constructor() {
    this.load();
  }

  get apiBase(): string {
    return app.forum.attribute('apiUrl') + '/mail-audit';
  }

  resetError() {
    this.error = null;
  }

  async load(force = true) {
    if (this.loading && !force) return;
    this.loading = true;
    m.redraw();
    try {
      const params = new URLSearchParams();
      params.set('limit', String(this.limit));
      params.set('offset', String(this.offset));
      if (this.statusFilter) params.set('filter[status]', this.statusFilter);
      if (this.search) params.set('filter[qtext]', this.search);

      const res: ListResponse = await app.request({
        method: 'GET',
        url: this.apiBase + '/mails?' + params.toString(),
      });

      this.rows = res.data;
      this.total = res.total;
      this.hasMore = res.hasMore;
      this.stats = res.stats;
      this.tableExists = res.tableExists;
      this.error = null;
    } catch (e) {
      this.error = e && (e as any).statusReason ? (e as any).statusReason : String(e);
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  refresh() {
    return this.load(true);
  }

  nextPage() {
    this.offset += this.limit;
    return this.load(true);
  }

  prevPage() {
    this.offset = Math.max(0, this.offset - this.limit);
    return this.load(true);
  }

  applyFilters() {
    this.offset = 0;
    return this.load(true);
  }

  async remove(id: number): Promise<boolean> {
    try {
      await app.request({ method: 'DELETE', url: this.apiBase + '/mails/' + id });
      app.alerts.show({ type: 'success' }, app.translator.trans('toreador-flarum-mail-audit.admin.table.deleted', { id }));
      await this.load(true);
      return true;
    } catch (e) {
      this.surfaceError(e);
      return false;
    }
  }

  async clear(): Promise<boolean> {
    try {
      const res: any = await app.request({
        method: 'POST',
        url: this.apiBase + '/mails/clear',
        body: {},
      });
      const count = (res && res.deleted) || 0;
      app.alerts.show({ type: 'success' }, app.translator.trans('toreador-flarum-mail-audit.admin.table.cleared', { count }));
      await this.load(true);
      return true;
    } catch (e) {
      this.surfaceError(e);
      return false;
    }
  }

  surfaceError(e: any) {
    const reason = e && e.statusReason ? e.statusReason : String(e && e.message ? e.message : e);
    app.alerts.show({ type: 'error' }, reason);
  }
}
