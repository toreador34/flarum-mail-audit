import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import Button from 'flarum/common/components/Button';
import LinkButton from 'flarum/common/components/LinkButton';
import extractText from 'flarum/common/utils/extractText';
import MailAuditPage from './components/MailAuditPage';
import { isSettingOn, settingValue } from './utils/settings';

const T = (key: string, params?: any) => app.translator.trans('toreador-flarum-mail-audit.admin.settings.' + key, params);

function privacyDraft(): string {
  const retentionEnabled = isSettingOn('retention_enabled', true);
  const days = parseInt(String(settingValue('retention_days', 30)), 10) || 0;
  const captureBody = isSettingOn('capture_body', true);
  const scope = settingValue('body_scope', 'system');

  const retention =
    retentionEnabled && days > 0 ? extractText(T('privacy_retention_on', { days })) : extractText(T('privacy_retention_off'));

  let body = extractText(T('privacy_body_none'));

  if (captureBody) {
    body = extractText(T(scope === 'all' ? 'privacy_body_all' : 'privacy_body_system'));
  }

  return extractText(T('privacy_draft', { retention, body }));
}

function copyText(text: string, done: () => void) {
  const fallback = () => {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    try {
      document.execCommand('copy');
      done();
    } catch (e) {
      // ignore
    }

    document.body.removeChild(textarea);
  };

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(done).catch(fallback);
  } else {
    fallback();
  }
}

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
          T('open_list_button')
        ),
        m('p', { className: 'helpText' }, T('open_list_help'))
      )
    )
    .setting(() => ({
      setting: 'toreador-flarum-mail-audit.enabled',
      type: 'bool',
      label: T('enabled_label'),
      help: T('enabled_help'),
    }))
    .setting(() => ({
      setting: 'toreador-flarum-mail-audit.capture_body',
      type: 'bool',
      label: T('capture_body_label'),
      help: T('capture_body_help'),
    }))
    .setting(() => ({
      setting: 'toreador-flarum-mail-audit.body_scope',
      type: 'select',
      label: T('body_scope_label'),
      help: T('body_scope_help'),
      default: 'system',
      options: {
        system: T('body_scope_system'),
        all: T('body_scope_all'),
      },
    }))
    .setting(() => ({
      setting: 'toreador-flarum-mail-audit.redact_tokens',
      type: 'bool',
      label: T('redact_tokens_label'),
      help: T('redact_tokens_help'),
    }))
    .setting(() => ({
      setting: 'toreador-flarum-mail-audit.retention_enabled',
      type: 'bool',
      label: T('retention_enabled_label'),
      help: T('retention_enabled_help'),
    }))
    .setting(() => ({
      setting: 'toreador-flarum-mail-audit.retention_days',
      type: 'number',
      label: T('retention_days_label'),
      help: T('retention_days_help'),
      min: 1,
    }))
    .customSetting(() => {
      const draft = privacyDraft();

      return m(
        'div',
        { className: 'Form-group MailAuditPrivacyDraft' },
        m('label', T('privacy_label')),
        m('p', { className: 'helpText' }, T('privacy_help')),
        m('textarea', {
          className: 'FormControl',
          readonly: true,
          rows: 18,
          value: draft,
        }),
        m(
          'div',
          { className: 'MailAuditPrivacyDraft-actions' },
          m(
            Button,
            {
              className: 'Button',
              icon: 'fas fa-copy',
              onclick: () => copyText(draft, () => app.alerts.show({ type: 'success' }, T('privacy_copied'))),
            },
            T('privacy_copy')
          )
        )
      );
    }),
];
