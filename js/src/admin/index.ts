import app from 'flarum/admin/app';
import AdminNav from 'flarum/admin/components/AdminNav';
import LinkButton from 'flarum/common/components/LinkButton';
import { extend } from 'flarum/common/extend';

export { default as extend } from './extend';

app.initializers.add('toreador-mail-audit', () => {
  extend(AdminNav.prototype, 'items', function (items) {
    items.add(
      'mail-audit',
      m(
        LinkButton,
        {
          href: app.route('mail-audit'),
          icon: 'fas fa-envelope-open-text',
          title: app.translator.trans('toreador-flarum-mail-audit.admin.nav.title'),
        },
        [app.translator.trans('toreador-flarum-mail-audit.admin.nav.mail_audit')]
      ),
      46
    );
  });
});
