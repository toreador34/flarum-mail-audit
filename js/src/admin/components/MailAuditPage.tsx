import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Select from 'flarum/common/components/Select';
import Switch from 'flarum/common/components/Switch';
import humanTime from 'flarum/common/utils/humanTime';
import MailDetailModal from './MailDetailModal';
import MailAuditState from '../states/MailAuditState';
import { formatAddress, formatAddressList, recipientsLine, statusKey, statusLabel, truncate } from '../utils/format';

export default class MailAuditPage extends Component {
  state = new MailAuditState();
  autoRefresh = false;
  autoRefreshInterval: any = null;
  searchValue = '';
  searchTimer: any = null;

  onremove() {
    this.stopAutoRefresh();
  }

  startAutoRefresh() {
    this.stopAutoRefresh();
    this.autoRefreshInterval = setInterval(() => {
      this.state.refresh();
    }, 5000);
  }

  stopAutoRefresh() {
    if (this.autoRefreshInterval) {
      clearInterval(this.autoRefreshInterval);
      this.autoRefreshInterval = null;
    }
  }

  toggleAutoRefresh(value: boolean) {
    this.autoRefresh = value;
    if (value) {
      this.startAutoRefresh();
    } else {
      this.stopAutoRefresh();
    }
  }

  onSearchInput(event: any) {
    this.searchValue = event.target.value;
    if (this.searchTimer) clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => {
      this.state.search = this.searchValue;
      this.searchTimer = null;
      this.state.applyFilters();
    }, 350);
  }

  confirm(message: string): boolean {
    return window.confirm(message);
  }

  deleteClick(row: any) {
    if (!this.confirm(app.translator.trans('toreador-flarum-mail-audit.admin.table.delete_confirm', { id: row.id }) as string)) return;
    this.state.remove(row.id);
  }

  clearAllClick() {
    if (!this.confirm(app.translator.trans('toreador-flarum-mail-audit.admin.filters.clear_all_confirm') as string)) return;
    this.state.clear();
  }

  recordingDisabled(): boolean {
    const value = (app.data.settings as any)['toreador-flarum-mail-audit.enabled'];
    return value === false || value === '0' || value === 0;
  }

  view() {
    return (
      <div className="MailAuditPage">
        <div className="container">
          <h2>{app.translator.trans('toreador-flarum-mail-audit.admin.page.title')}</h2>
          <p>{app.translator.trans('toreador-flarum-mail-audit.admin.page.subtitle')}</p>

          {this.banner()}
          {this.stats()}
          {this.toolbar()}
          {this.table()}
          {this.pagination()}
        </div>
      </div>
    );
  }

  banner() {
    const banners: any[] = [];

    if (!this.state.tableExists) {
      banners.push(m('.alert.alert-danger', app.translator.trans('toreador-flarum-mail-audit.admin.page.table_missing')));
    }

    if (this.recordingDisabled()) {
      banners.push(m('.alert.alert-warning', app.translator.trans('toreador-flarum-mail-audit.admin.page.disabled_banner')));
    }

    if (this.state.error) {
      banners.push(m('.alert.alert-danger', this.state.error));
    }

    if (!banners.length) return null;

    return m('.MailAuditPage-banner', banners);
  }

  stats() {
    const s = this.state;
    const stats = s.stats || { total: 0, sent: 0, failed: 0, pending: 0, last_24h: 0 };

    const card = (labelKey: string, helpKey: string, value: any) =>
      m('.MailAuditPage-stat', { title: app.translator.trans(helpKey) }, [
        m('small', app.translator.trans(labelKey)),
        m('p', value),
      ]);

    return m('.MailAuditPage-stats', [
      card('toreador-flarum-mail-audit.admin.page.stats.total', 'toreador-flarum-mail-audit.admin.page.stats.total_help', stats.total),
      card('toreador-flarum-mail-audit.admin.page.stats.sent', 'toreador-flarum-mail-audit.admin.page.stats.sent_help', stats.sent),
      card('toreador-flarum-mail-audit.admin.page.stats.failed', 'toreador-flarum-mail-audit.admin.page.stats.failed_help', stats.failed),
      card('toreador-flarum-mail-audit.admin.page.stats.pending', 'toreador-flarum-mail-audit.admin.page.stats.pending_help', stats.pending),
      card('toreador-flarum-mail-audit.admin.page.stats.last_24h', 'toreador-flarum-mail-audit.admin.page.stats.last_24h_help', stats.last_24h),
    ]);
  }

  toolbar() {
    const s = this.state;

    const selectOptions: any = {
      '': app.translator.trans('toreador-flarum-mail-audit.admin.filters.all_statuses'),
      sent: app.translator.trans('toreador-flarum-mail-audit.admin.filters.status_sent'),
      failed: app.translator.trans('toreador-flarum-mail-audit.admin.filters.status_failed'),
      pending: app.translator.trans('toreador-flarum-mail-audit.admin.filters.status_pending'),
    };

    return (
      <div className="MailAuditPage-toolbar">
        <Button
          className="Button Button--icon"
          icon="fas fa-sync-alt"
          onclick={() => s.refresh()}
          disabled={s.loading}
          title={app.translator.trans('toreador-flarum-mail-audit.admin.page.refresh')}
        />
        <Switch state={this.autoRefresh} onchange={(v: boolean) => this.toggleAutoRefresh(v)}>
          {app.translator.trans('toreador-flarum-mail-audit.admin.page.auto_refresh')}
        </Switch>

        <div className="MailAuditPage-toolbar-spacer" />

        <input
          className="FormControl"
          type="search"
          placeholder={app.translator.trans('toreador-flarum-mail-audit.admin.filters.search_placeholder') as string}
          value={this.searchValue}
          oninput={(e: any) => this.onSearchInput(e)}
        />
        <Select
          options={selectOptions}
          value={s.statusFilter}
          onchange={(v: string) => {
            s.statusFilter = v;
            s.applyFilters();
          }}
        />
        <Button
          className="Button Button--danger"
          icon="fas fa-trash"
          onclick={() => this.clearAllClick()}
          disabled={s.loading || !s.total}
          title={app.translator.trans('toreador-flarum-mail-audit.admin.filters.clear_all_help')}
        >
          {app.translator.trans('toreador-flarum-mail-audit.admin.filters.clear_all')}
        </Button>
      </div>
    );
  }

  table() {
    const s = this.state;

    if (s.loading && !s.rows.length) {
      return m('.MailAuditPage-table-wrap', m(LoadingIndicator));
    }

    if (!s.rows.length) {
      return m('.MailAuditPage-table-wrap.Alert', m('.container', m('p', app.translator.trans('toreador-flarum-mail-audit.admin.table.empty'))));
    }

    return (
      <div className="MailAuditPage-table-wrap">
        <table className="MailAuditPage-table">
          <thead>
            <tr>
              <th>{app.translator.trans('toreador-flarum-mail-audit.admin.table.id')}</th>
              <th>{app.translator.trans('toreador-flarum-mail-audit.admin.table.status')}</th>
              <th>{app.translator.trans('toreador-flarum-mail-audit.admin.table.subject')}</th>
              <th>{app.translator.trans('toreador-flarum-mail-audit.admin.table.to')}</th>
              <th>{app.translator.trans('toreador-flarum-mail-audit.admin.table.from')}</th>
              <th>{app.translator.trans('toreador-flarum-mail-audit.admin.table.created_at')}</th>
              <th>{app.translator.trans('toreador-flarum-mail-audit.admin.table.actions')}</th>
            </tr>
          </thead>
          <tbody>{s.rows.map((row) => this.row(row))}</tbody>
        </table>
      </div>
    );
  }

  row(row: any) {
    const meta: any[] = [];

    if (row.mailer) meta.push(row.mailer);
    if (row.attachment_count) {
      meta.push(`${app.translator.trans('toreador-flarum-mail-audit.admin.modal.attachments')}: ${row.attachment_count}`);
    }

    return (
      <tr key={row.id}>
        <td>#{row.id}</td>
        <td>
          <span className={'MailAuditPage-status MailAuditPage-status--' + statusKey(row.status)}>{statusLabel(row.status)}</span>
        </td>
        <td className="MailAuditPage-subject">
          <p className="MailAuditPage-subject-title">{row.subject || app.translator.trans('toreador-flarum-mail-audit.admin.table.no_subject')}</p>
          {row.error ? <div className="MailAuditPage-subject-error">{truncate(row.error)}</div> : null}
          {meta.length ? <div className="MailAuditPage-subject-meta">{meta.join(' · ')}</div> : null}
        </td>
        <td className="MailAuditPage-to" title={formatAddressList(row.to)}>
          {recipientsLine(row.to, 2)}
        </td>
        <td className="MailAuditPage-from" title={formatAddressList(row.from)}>
          {row.from && row.from.length ? formatAddress(row.from[0]) : '—'}
        </td>
        <td>{row.created_at ? humanTime(row.created_at) : '—'}</td>
        <td>
          <div className="MailAuditPage-actions">
            <Button
              className="Button Button--icon"
              icon="fas fa-search"
              onclick={() => app.modal.show(MailDetailModal, { id: row.id })}
              title={app.translator.trans('toreador-flarum-mail-audit.admin.table.view_title')}
            />
            <Button
              className="Button Button--icon Button--danger"
              icon="fas fa-times"
              onclick={() => this.deleteClick(row)}
              disabled={this.state.loading}
              title={app.translator.trans('toreador-flarum-mail-audit.admin.table.delete_title')}
            />
          </div>
        </td>
      </tr>
    );
  }

  pagination() {
    const s = this.state;
    if (!s.total && s.loading) return null;

    const from = s.total ? s.offset + 1 : 0;
    const to = Math.min(s.offset + s.limit, s.total);

    return (
      <div className="MailAuditPage-pagination">
        <span>{app.translator.trans('toreador-flarum-mail-audit.admin.pagination.x_of_y', { from, to, total: s.total })}</span>
        <Button
          className="Button Button--icon"
          icon="fas fa-chevron-left"
          onclick={() => s.prevPage()}
          disabled={s.loading || s.offset === 0}
        />
        <Button
          className="Button Button--icon"
          icon="fas fa-chevron-right"
          onclick={() => s.nextPage()}
          disabled={s.loading || !s.hasMore}
        />
      </div>
    );
  }
}
