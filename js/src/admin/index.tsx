import app from 'flarum/admin/app';

/** Anthology's admin: what to call a series, the sidebar link and three permissions. */
app.initializers.add('ernestdefoe-anthology', () => {
  const t = (key: string) => app.translator.trans(`ernestdefoe-anthology.admin.${key}`);
  const nouns: Record<string, any> = {};
  ['series', 'novel', 'story', 'book'].forEach((n) => (nouns[n] = t('settings.nouns.' + n)));

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
