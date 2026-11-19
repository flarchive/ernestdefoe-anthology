import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import BasicsPage from 'flarum/admin/components/BasicsPage';

export { default as extend } from './extend';

/** The directory as a home page choice under Basics. Settings and permissions are in extend.ts. */
app.initializers.add('ernestdefoe-anthology', () => {
  extend(BasicsPage, 'homePageItems', (items) => {
    items.add('anthology', { path: '/series', label: app.translator.trans('ernestdefoe-anthology.admin.basics.home_page') });
  });
});
