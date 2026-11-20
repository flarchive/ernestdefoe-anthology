import Extend from 'flarum/common/extenders';
import NewChapterNotification from './components/NewChapterNotification';
import commonExtend from '../common/extend';

export default [
  ...commonExtend,

  new Extend.Routes()
    // When the directory is the home page, core moves the last route at /series to '/': this one keeps /series working.
    .add('anthology.directory', '/series', () => import('./pages/DirectoryPage'))
    .add('anthology.index', '/series', () => import('./pages/DirectoryPage'))
    .add('anthology.series', '/series/:id', () => import('./pages/SeriesPage'))
    .add('user.anthology', '/u/:username/series', () => import('./pages/UserSeriesPage')),

  new Extend.Notification().add('anthologyNewChapter', NewChapterNotification),
];
