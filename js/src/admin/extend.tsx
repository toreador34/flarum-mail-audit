import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import LinkButton from 'flarum/common/components/LinkButton';
import MailAuditPage from './components/MailAuditPage';

export default [
  new Extend.Routes().add('mail-audit', '/mail-audit', MailAuditPage),

  new Extend.Admin()
    .customSetting(() =>
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
    .setting(() => ({
      setting: 'toreador-flarum-mail-audit.enabled',
      type: 'bool',
      label: app.translator.trans('toreador-flarum-mail-audit.admin.settings.enabled_label'),
      help: app.translator.trans('toreador-flarum-mail-audit.admin.settings.enabled_help'),
    }))
    .setting(() => ({
      setting: 'toreador-flarum-mail-audit.capture_body',
      type: 'bool',
      label: app.translator.trans('toreador-flarum-mail-audit.admin.settings.capture_body_label'),
      help: app.translator.trans('toreador-flarum-mail-audit.admin.settings.capture_body_help'),
    }))
    .setting(() => ({
      setting: 'toreador-flarum-mail-audit.retention_days',
      type: 'number',
      label: app.translator.trans('toreador-flarum-mail-audit.admin.settings.retention_days_label'),
      help: app.translator.trans('toreador-flarum-mail-audit.admin.settings.retention_days_help'),
      min: 0,
    })),
];
