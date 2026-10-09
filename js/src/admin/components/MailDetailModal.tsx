import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import humanTime from 'flarum/common/utils/humanTime';
import { formatAddressList, formatBytes, statusKey, statusLabel } from '../utils/format';

export default class MailDetailModal extends Modal {
  id!: number;
  detail: any = null;
  loading = true;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.id = vnode.attrs.id;
    this.fetch();
  }

  className() {
    return 'MailAuditModal Modal--large';
  }

  title() {
    return app.translator.trans('toreador-flarum-mail-audit.admin.modal.title', { id: this.id });
  }

  content() {
    if (this.loading || !this.detail) {
      return m('.Modal-body', m(LoadingIndicator));
    }

    const d = this.detail;

    return m('.Modal-body', [
      m('.MailAuditModal-status', [
        m('span', { className: 'MailAuditPage-status MailAuditPage-status--' + statusKey(d.status) }, statusLabel(d.status)),
        d.sent_at
          ? m('span.MailAuditModal-status-time', [
              app.translator.trans('toreador-flarum-mail-audit.admin.modal.sent_at'),
              ': ',
              humanTime(d.sent_at),
            ])
          : null,
      ]),

      d.error ? m('.alert.alert-danger', m('p', d.error)) : null,

      this.meta(d),
      this.attachments(d),
      this.body(d),
      this.headers(d),
    ]);
  }

  meta(d: any) {
    const item = (labelKey: string, value: any) => m('div', [m('dt', app.translator.trans('toreador-flarum-mail-audit.admin.modal.' + labelKey)), m('dd', value)]);

    return m('dl.MailAuditModal-meta', [
      item('transport', d.mailer || '—'),
      item('message_id', d.message_id ? m('code', d.message_id) : '—'),
      item('created_at', d.created_at ? humanTime(d.created_at) : '—'),
      item('from', formatAddressList(d.from)),
      item('to', formatAddressList(d.to)),
      item('cc', formatAddressList(d.cc)),
      item('bcc', formatAddressList(d.bcc)),
      item('reply_to', formatAddressList(d.reply_to)),
    ]);
  }

  attachments(d: any) {
    if (!d.attachments || !d.attachments.length) return null;

    return [
      m('h4', app.translator.trans('toreador-flarum-mail-audit.admin.modal.attachments')),
      m(
        'ul.MailAuditModal-attachments',
        d.attachments.map((a: any, i: number) =>
          m('li', [
            m('strong', a.name || '?'),
            ' — ',
            a.content_type || '?',
            a.size !== null && a.size !== undefined ? `, ${formatBytes(a.size)}` : '',
          ])
        )
      ),
    ];
  }

  body(d: any) {
    const sections: any[] = [];

    if (d.has_html && d.body_html) {
      sections.push(m('h4', app.translator.trans('toreador-flarum-mail-audit.admin.modal.preview_html')));
      sections.push(
        m('iframe', {
          className: 'MailAuditModal-preview',
          sandbox: '',
          referrerpolicy: 'no-referrer',
          srcdoc: d.body_html,
        })
      );
    }

    if (d.has_text && d.body_text) {
      sections.push(m('h4', app.translator.trans('toreador-flarum-mail-audit.admin.modal.preview_text')));
      sections.push(m('pre.MailAuditModal-pre', d.body_text));
    }

    if (!sections.length) {
      sections.push(m('.alert.alert-info', app.translator.trans('toreador-flarum-mail-audit.admin.modal.no_body')));
    }

    return sections;
  }

  headers(d: any) {
    if (!d.headers || !d.headers.length) return null;

    const text = d.headers.map((h: any) => `${h.name}: ${h.value === null || h.value === undefined ? '' : h.value}`).join('\n');

    return [
      m('h4', app.translator.trans('toreador-flarum-mail-audit.admin.modal.headers')),
      m('pre.MailAuditModal-pre', text),
    ];
  }

  async fetch() {
    try {
      const res: any = await app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/mail-audit/mails/' + this.id,
      });
      this.detail = res.data;
    } catch (e: any) {
      app.alerts.show({ type: 'error' }, e && e.statusReason ? e.statusReason : String(e));
    } finally {
      this.loading = false;
      m.redraw();
    }
  }
}
