import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import BasicsPage from 'flarum/admin/components/BasicsPage';

/** Anthology's admin: what to call a series, the sidebar link, three permissions and the directory as a home page choice. */
app.initializers.add('ernestdefoe-anthology', () => {
  const t = (key: string) => app.translator.trans(`ernestdefoe-anthology.admin.${key}`);
  const nouns: Record<string, any> = {};
  ['series', 'novel', 'story', 'book'].forEach((n) => (nouns[n] = t('settings.nouns.' + n)));

  extend(BasicsPage, 'homePageItems', (items) => {
    items.add('anthology', { path: '/series', label: t('basics.home_page') });
  });

  app.registry
    .for('ernestdefoe-anthology')
    .registerSetting({
      setting: 'ernestdefoe-anthology.noun',
      type: 'select',
      options: nouns,
      default: 'series',
      label: t('settings.noun'),
      help: t('settings.noun_help'),
    })
    .registerSetting({ setting: 'ernestdefoe-anthology.show_nav', type: 'boolean', label: t('settings.show_nav') })
    .registerPermission({ icon: 'fas fa-book-bookmark', label: t('permissions.view'), permission: 'anthology.view', allowGuest: true }, 'view')
    .registerPermission({ icon: 'fas fa-book-bookmark', label: t('permissions.create'), permission: 'anthology.create' }, 'start')
    .registerPermission({ icon: 'fas fa-book-bookmark', label: t('permissions.moderate'), permission: 'anthology.moderate' }, 'moderate');
});
