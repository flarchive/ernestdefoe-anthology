import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';

const t = (key: string) => app.translator.trans(`ernestdefoe-anthology.admin.${key}`);
const ICON = 'fas fa-book-bookmark';

/** What to call a series, the sidebar link and three permissions. The noun's default lives in extend.php. */
export default [
  new Extend.Admin()
    .setting(() => ({
      setting: 'ernestdefoe-anthology.noun',
      type: 'select',
      options: Object.fromEntries(['series', 'novel', 'story', 'book'].map((n) => [n, t('settings.nouns.' + n)])),
      label: t('settings.noun'),
      help: t('settings.noun_help'),
    }))
    .setting(() => ({ setting: 'ernestdefoe-anthology.show_nav', type: 'boolean', label: t('settings.show_nav') }))
    .permission(() => ({ icon: ICON, label: t('permissions.view'), permission: 'anthology.view', allowGuest: true }), 'view')
    .permission(() => ({ icon: ICON, label: t('permissions.create'), permission: 'anthology.create' }), 'start')
    .permission(() => ({ icon: ICON, label: t('permissions.moderate'), permission: 'anthology.moderate' }), 'moderate'),
];
