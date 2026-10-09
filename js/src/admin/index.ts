import app from 'flarum/admin/app';
import AdminNav from 'flarum/admin/components/AdminNav';
import LinkButton from 'flarum/common/components/LinkButton';
import { extend } from 'flarum/common/extend';
import MailAuditPage from './components/MailAuditPage';

app.initializers.add('toreador-mail-audit', () => {
  app.routes['mail-audit'] = {
    path: '/mail-audit',
    component: MailAuditPage,
  };

  extend(AdminNav.prototype, 'items', function (items) {
    items.add(
      'mail-audit',
      m(LinkButton, {
        href: app.route('mail-audit'),
        icon: 'fas fa-envelope-open-text',
        title: app.translator.trans('toreador-flarum-mail-audit.admin.nav.title'),
      }, [
        app.translator.trans('toreador-flarum-mail-audit.admin.nav.mail_audit'),
      ]),
      46
    );
  });

  app.extensionData
    .for('toreador-mail-audit')
    .registerSetting(() =>
      m(
        'div',
        { className: 'Form-group' },
        m(
          LinkButton,
          {
            className: 'Button Button--primary',
            icon: 'fas fa-envelope-open-text',
            href: app.route('mail-audit'),
          },
          app.translator.trans('toreador-flarum-mail-audit.admin.settings.open_list_button')
        ),
        m(
          'p',
          { className: 'helpText' },
          app.translator.trans('toreador-flarum-mail-audit.admin.settings.open_list_help')
        )
      )
    )
    .registerSetting({
      setting: 'toreador-flarum-mail-audit.enabled',
      type: 'bool',
      label: app.translator.trans('toreador-flarum-mail-audit.admin.settings.enabled_label'),
      help: app.translator.trans('toreador-flarum-mail-audit.admin.settings.enabled_help'),
    })
    .registerSetting({
      setting: 'toreador-flarum-mail-audit.capture_body',
      type: 'bool',
      label: app.translator.trans('toreador-flarum-mail-audit.admin.settings.capture_body_label'),
      help: app.translator.trans('toreador-flarum-mail-audit.admin.settings.capture_body_help'),
    })
    .registerSetting({
      setting: 'toreador-flarum-mail-audit.retention_days',
      type: 'number',
      label: app.translator.trans('toreador-flarum-mail-audit.admin.settings.retention_days_label'),
      help: app.translator.trans('toreador-flarum-mail-audit.admin.settings.retention_days_help'),
      min: 0,
    });
});
