import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';
import Button from 'flarum/common/components/Button';
import UserPage from 'flarum/forum/components/UserPage';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import type Discussion from 'flarum/common/models/Discussion';
import type { DiscussionSeries } from '../common/api';
import { ChapterBar, NextChapter } from './components/ChapterBar';
import NewChapterNotification from './components/NewChapterNotification';
import { t } from './util';

export { default as extend } from './extend';

const ICON = 'fas fa-book-bookmark';

/** The series a discussion is in, on the discussion page only (undefined in lists). */
const seriesOf = (discussion: Discussion) => discussion.attribute<DiscussionSeries | null | undefined>('anthologySeries');

/*
 * Everything here runs on every page for every visitor, so it is only the
 * wiring and the chapter bar. Every page and modal is its own chunk.
 */
app.initializers.add('ernestdefoe-anthology', () => {
  // When the directory is the home page, core moves the last route at /series to '/': this one keeps /series working.
  app.routes['anthology.directory'] = { path: '/series', component: () => import('./pages/DirectoryPage') };
  app.routes['anthology.index'] = { path: '/series', component: () => import('./pages/DirectoryPage') };
  app.routes['anthology.series'] = { path: '/series/:id', component: () => import('./pages/SeriesPage') };
  app.routes['user.anthology'] = { path: '/u/:username/series', component: () => import('./pages/UserSeriesPage') };

  app.notificationComponents.anthologyNewChapter = NewChapterNotification;

  extend(IndexSidebar.prototype, 'navItems', function (items) {
    if (!app.forum.attribute('canViewSeries') || app.forum.attribute('anthologyShowNav') === false) return;

    items.add(
      'anthology',
      <LinkButton href={app.route('anthology.index')} icon={ICON}>
        {t('nav')}
      </LinkButton>,
      4
    );
  });

  extend(UserPage.prototype, 'navItems', function (this: UserPage, items) {
    const user = this.user;
    if (!user || !app.forum.attribute('canViewSeries')) return;
    const own = app.session.user === user && app.forum.attribute('canCreateSeries');
    if (!own && !user.attribute<number>('anthologySeriesCount')) return;

    items.add(
      'anthology',
      <LinkButton href={app.route('user.anthology', { username: user.slug() })} icon={ICON}>
        {t('profile.nav')}
      </LinkButton>,
      79
    );
  });

  // "Chapter 3 of 15 · Title" with previous and next, under the title.
  extend('flarum/forum/components/DiscussionHero', 'items', function (this: any, items: any) {
    const series = seriesOf(this.attrs.discussion);
    if (series && series.number) items.add('anthology', <ChapterBar series={series} />, -10);
  });

  // The chapter is the first post: "Next chapter" right after it, before the replies.
  extend('flarum/forum/components/PostStream', 'afterFirstPostItems', function (this: any, items: any) {
    const series = seriesOf(this.discussion);
    if (series && series.number) items.add('anthology', <NextChapter series={series} />);
  });

  extend(DiscussionControls, 'userControls', function (items, discussion) {
    const series = seriesOf(discussion);
    const user = app.session.user;
    if (series === undefined || !user) return;

    const mod = app.forum.attribute('canModerateSeries');
    const author = discussion.user() === user;
    if (!(mod || (author && app.forum.attribute('canCreateSeries')) || (series && (series.canEdit || author)))) return;

    items.add(
      'anthology',
      <Button icon={ICON} onclick={() => app.modal.show(() => import('./modals/AddToSeriesModal'), { discussion })}>
        {series ? t('controls.change') : t('controls.add')}
      </Button>
    );
  });

  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items: any) {
    if (!app.forum.attribute('canViewSeries')) return;
    items.add('anthologyNewChapter', { name: 'anthologyNewChapter', icon: ICON, label: t('settings.notify_new_chapter') });
  });
});
